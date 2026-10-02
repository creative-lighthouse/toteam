<?php

namespace App\Maps;

use GuzzleHttp\Client;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Throwable;

/**
 * Class \App\Maps\UpdateMapTilesJob
 *
 * Lädt die Kartendaten für die Event-Karte herunter: einen Ausschnitt der täglich
 * neu gebauten Protomaps-Weltkarte (OpenStreetMap) als .pmtiles-Datei, dazu die
 * Schriften und Symbole des Kartenstils. Alles landet in public/tiles/ (siehe
 * MapTilesSettings) und wird vom Webserver direkt ausgeliefert.
 *
 * Der eigentliche Download dauert je nach Bereich Minuten und läuft deshalb als
 * eigener Prozess im Hintergrund (run.sh im Arbeitsordner). Der Job fragt ihn in
 * kurzen Schritten ab — die Job-Queue hält Jobs, deren Schrittzahl sich nicht
 * bewegt, sonst für hängengeblieben und startet sie neu. Erst wenn alles komplett
 * ist, wird die alte Datei ersetzt; bis dahin bleibt die Karte unverändert.
 *
 * Nach Abschluss plant sich der Job selbst wieder ein (MapTilesSettings::ensureScheduled()).
 *
 * @property string $stage prepare → start → running → finish
 * @property string $build Stand der Protomaps-Weltkarte, z.B. "20261001"
 * @property string $bbox
 * @property int $maxZoom
 * @property int $pid
 * @property int $startedAt
 * @property bool $failed
 */
class UpdateMapTilesJob extends AbstractQueuedJob
{
    use Configurable;
    use Injectable;

    /** Version der pmtiles-CLI (https://github.com/protomaps/go-pmtiles) */
    private static string $pmtiles_version = '1.31.2';

    /** Eigener Pfad zur pmtiles-CLI, falls sie auf dem Server installiert ist — sonst wird sie heruntergeladen */
    private static string $pmtiles_binary = '';

    private static string $builds_url = 'https://build-metadata.protomaps.dev/builds.json';
    private static string $build_url = 'https://build.protomaps.com/';

    /** Schriften und Symbole (Sprites) für den Stil aus @protomaps/basemaps */
    private static string $assets_url = 'https://codeload.github.com/protomaps/basemaps-assets/tar.gz/refs/heads/main';
    private static array $assets_paths = [
        'fonts/Noto Sans Regular',
        'fonts/Noto Sans Medium',
        'fonts/Noto Sans Italic',
        'sprites/v4',
    ];

    /** Danach wird der Download abgebrochen (Sekunden) */
    private static int $max_runtime = 6 * 3600;

    /** Nach einem Fehler neuer Versuch nach so vielen Tagen */
    private static int $retry_days = 1;

    /** Pause zwischen zwei Abfragen des Download-Prozesses (Sekunden) */
    private static int $poll_interval = 5;

    public function getTitle()
    {
        return 'Kartendaten aktualisieren';
    }

    /** Immer nur eine Aktualisierung in der Queue */
    public function getSignature()
    {
        return md5(static::class);
    }

    public function setup()
    {
        parent::setup();
        $this->stage = 'prepare';
        $this->totalSteps = 4;
    }

    public function process()
    {
        try {
            match ($this->stage) {
                'prepare' => $this->prepare(),
                'start'   => $this->start(),
                'running' => $this->poll(),
                'finish'  => $this->finish(),
            };
        } catch (Throwable $e) {
            $this->fail($e->getMessage());
        }
        // Jeder Aufruf muss die Schrittzahl erhöhen, sonst gilt der Job als hängengeblieben
        $this->currentStep++;
        $this->totalSteps = max($this->totalSteps, $this->currentStep + 1);
    }

    public function afterComplete()
    {
        parent::afterComplete();
        $settings = MapTilesSettings::current();
        if (!$settings->AutoUpdate) {
            return;
        }
        if ($this->failed) {
            $settings->queueUpdate(date('Y-m-d H:i:s', strtotime('+' . static::config()->get('retry_days') . ' days')));
        } else {
            $settings->ensureScheduled();
        }
    }

    /** Pfad der pmtiles-CLI ermitteln, Stand der Weltkarte wählen und den Platzbedarf prüfen */
    private function prepare(): void
    {
        $settings = MapTilesSettings::current();
        $bbox = $settings->getEffectiveBBox();
        if (!MapTilesSettings::parseBBox($bbox)) {
            throw new RuntimeException('Kein gültiger Bereich eingestellt.');
        }
        $this->bbox = $bbox;
        $this->maxZoom = $settings->getEffectiveMaxZoom();

        // Reste eines abgebrochenen Laufs aufräumen
        $pidFile = self::workPath('run.pid');
        $this->killProcess(is_file($pidFile) ? (int) file_get_contents($pidFile) : 0);
        $this->cleanup();
        foreach ([self::workPath(), self::workPath('bin'), $settings->getDirectoryPath()] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
                throw new RuntimeException("Ordner $dir kann nicht angelegt werden.");
            }
        }

        $binary = $this->ensureBinary();
        $this->build = $this->latestBuild();

        // Probelauf: lädt nur das Inhaltsverzeichnis und berechnet die Dateigröße
        [$code, $output] = self::run(sprintf(
            '%s extract %s %s --bbox=%s --maxzoom=%d --dry-run',
            escapeshellarg($binary),
            escapeshellarg($this->sourceURL()),
            escapeshellarg(self::partPath()),
            escapeshellarg($this->bbox),
            $this->maxZoom
        ));
        if ($code !== 0 || !preg_match('/archive size of ([\d.]+) ([kMGT]?B)/', $output, $m)) {
            throw new RuntimeException("Probelauf fehlgeschlagen:\n" . self::lastLines($output));
        }
        $size = (float) $m[1] * (['B' => 1, 'kB' => 1e3, 'MB' => 1e6, 'GB' => 1e9, 'TB' => 1e12][$m[2]]);
        // Die alte Datei bleibt bis zum Schluss liegen, die neue braucht also zusätzlich Platz
        $needed = (int) ($size * 1.1) + 200 * 1024 * 1024;
        $free = (int) disk_free_space($settings->getDirectoryPath());
        if ($free < $needed) {
            throw new RuntimeException(sprintf(
                'Nicht genug Speicherplatz: benötigt ca. %s, frei sind %s.',
                MapTilesSettings::formatBytes($needed),
                MapTilesSettings::formatBytes($free)
            ));
        }

        $this->addMessage(sprintf(
            'Stand %s, Bereich %s, Zoom bis %d — ca. %s',
            $this->build,
            $this->bbox,
            $this->maxZoom,
            MapTilesSettings::formatBytes((int) $size)
        ));
        $this->stage = 'start';
    }

    /** Download als eigenen Prozess starten — er überlebt auch einen Neustart der Job-Queue */
    private function start(): void
    {
        $prefix = 'basemaps-assets-main/';
        $assetPaths = implode(' ', array_map(
            fn ($path) => escapeshellarg($prefix . $path),
            static::config()->get('assets_paths')
        ));
        $script = implode("\n", [
            'set -eu',
            'echo "Lade Schriften und Symbole …"',
            'rm -rf ' . escapeshellarg(self::assetsPath()),
            'mkdir -p ' . escapeshellarg(self::assetsPath()),
            sprintf(
                'curl -fsSL --retry 3 %s | tar -xz -C %s --strip-components=1 %s',
                escapeshellarg(static::config()->get('assets_url')),
                escapeshellarg(self::assetsPath()),
                $assetPaths
            ),
            'echo "Lade Kartendaten …"',
            sprintf(
                '%s extract %s %s --bbox=%s --maxzoom=%d',
                escapeshellarg($this->ensureBinary()),
                escapeshellarg($this->sourceURL()),
                escapeshellarg(self::partPath()),
                escapeshellarg($this->bbox),
                $this->maxZoom
            ),
            'echo "Download abgeschlossen"',
        ]) . "\n";
        file_put_contents(self::workPath('run.sh'), $script);

        // setsid: eigene Prozessgruppe, damit sich der Download samt Unterprozessen beenden lässt
        $inner = sprintf(
            'sh %s > %s 2>&1; echo $? > %s',
            escapeshellarg(self::workPath('run.sh')),
            escapeshellarg(self::workPath('run.log')),
            escapeshellarg(self::workPath('run.exit'))
        );
        $pid = (int) trim((string) shell_exec(sprintf(
            'setsid nohup sh -c %s > /dev/null 2>&1 < /dev/null & echo $!',
            escapeshellarg($inner)
        )));
        if (!$pid) {
            throw new RuntimeException('Download-Prozess konnte nicht gestartet werden.');
        }
        file_put_contents(self::workPath('run.pid'), $pid);
        $this->pid = $pid;
        $this->startedAt = time();
        $this->addMessage('Download gestartet');
        $this->stage = 'running';
    }

    private function poll(): void
    {
        if (is_file(self::workPath('run.exit'))) {
            $this->finish();
            return;
        }
        if (!self::isAlive($this->pid)) {
            // Kann sich mit dem Schreiben von run.exit überschneiden
            clearstatcache();
            if (is_file(self::workPath('run.exit'))) {
                $this->finish();
                return;
            }
            throw new RuntimeException("Der Download wurde unerwartet beendet.\n" . self::logTail());
        }
        if (time() - $this->startedAt > static::config()->get('max_runtime')) {
            throw new RuntimeException('Der Download hat das Zeitlimit überschritten.');
        }
        sleep(static::config()->get('poll_interval'));
    }

    /** Neue Dateien an ihren Platz bringen — erst jetzt sieht das Frontend den neuen Stand */
    private function finish(): void
    {
        $code = (int) trim((string) file_get_contents(self::workPath('run.exit')));
        if ($code !== 0) {
            throw new RuntimeException("Download fehlgeschlagen (Code $code):\n" . self::logTail());
        }
        if (!is_file(self::partPath()) || !filesize(self::partPath())) {
            throw new RuntimeException('Die heruntergeladene Datei ist leer.');
        }

        $settings = MapTilesSettings::current();
        $dir = $settings->getDirectoryPath();
        foreach (['fonts', 'sprites'] as $name) {
            $new = self::assetsPath() . '/' . $name;
            if (!is_dir($new)) {
                throw new RuntimeException("Im Download fehlt der Ordner $name.");
            }
            $current = "$dir/$name";
            $old = "$dir/.$name-old";
            self::removeDir($old);
            if (is_dir($current)) {
                rename($current, $old);
            }
            rename($new, $current);
            self::removeDir($old);
        }
        rename(self::partPath(), $settings->getFilePath());

        $settings->TilesBuild = $this->build;
        $settings->TilesBBox = $this->bbox;
        $settings->TilesMaxZoom = $this->maxZoom;
        $settings->TilesSize = filesize($settings->getFilePath());
        $settings->TilesUpdated = DBDatetime::now()->Rfc2822();
        $settings->LastError = null;
        $settings->write();

        $this->cleanup();
        $this->addMessage('Kartendaten aktualisiert (' . MapTilesSettings::formatBytes((int) $settings->TilesSize) . ')');
        $this->isComplete = true;
    }

    private function fail(string $message): void
    {
        $this->killProcess((int) $this->pid);
        $this->cleanup();
        $settings = MapTilesSettings::current();
        $settings->LastError = DBDatetime::now()->Format('dd.MM.y HH:mm') . ': ' . $message;
        $settings->write();
        $this->addMessage($message, 'ERROR');
        $this->failed = true;
        $this->isComplete = true;
    }

    /** pmtiles-CLI: konfigurierter Pfad oder einmal von GitHub geladen */
    private function ensureBinary(): string
    {
        if ($configured = static::config()->get('pmtiles_binary')) {
            if (!is_executable($configured)) {
                throw new RuntimeException("pmtiles_binary $configured ist nicht ausführbar.");
            }
            return $configured;
        }
        $version = static::config()->get('pmtiles_version');
        $path = self::workPath("bin/pmtiles-$version");
        if (is_executable($path)) {
            return $path;
        }
        $arch = match (php_uname('m')) {
            'x86_64', 'amd64'  => 'x86_64',
            'aarch64', 'arm64' => 'arm64',
            default            => throw new RuntimeException('Keine pmtiles-CLI für ' . php_uname('m') . ' — bitte pmtiles_binary konfigurieren.'),
        };
        $url = "https://github.com/protomaps/go-pmtiles/releases/download/v$version/go-pmtiles_{$version}_Linux_$arch.tar.gz";
        $tmp = escapeshellarg("$path.tmp");
        [$code, $output] = self::run(sprintf('curl -fsSL --retry 3 %s | tar -xzO pmtiles > %s && chmod +x %s', escapeshellarg($url), $tmp, $tmp));
        if ($code !== 0) {
            self::deleteFile("$path.tmp");
            throw new RuntimeException("pmtiles-CLI konnte nicht geladen werden:\n" . self::lastLines($output));
        }
        rename("$path.tmp", $path);
        return $path;
    }

    /** Neuester Stand der Protomaps-Weltkarte — ältere Stände werden nach einigen Tagen gelöscht */
    private function latestBuild(): string
    {
        $client = new Client(['timeout' => 30]);
        $builds = json_decode((string) $client->get(static::config()->get('builds_url'))->getBody(), true);
        $keys = array_filter(array_column((array) $builds, 'key'), fn ($key) => preg_match('/^\d{8}\.pmtiles$/', $key));
        if (!$keys) {
            throw new RuntimeException('Keine Kartendaten bei Protomaps gefunden.');
        }
        sort($keys);
        return basename(end($keys), '.pmtiles');
    }

    private function sourceURL(): string
    {
        return static::config()->get('build_url') . $this->build . '.pmtiles';
    }

    private function cleanup(): void
    {
        self::deleteFile(self::partPath());
        self::removeDir(self::assetsPath());
        foreach (['run.sh', 'run.log', 'run.exit', 'run.pid'] as $file) {
            self::deleteFile(self::workPath($file));
        }
    }

    private function killProcess(int $pid): void
    {
        if ($pid > 0 && self::isAlive($pid)) {
            // Negative PID: die ganze Prozessgruppe (sh, curl, pmtiles); 15 = SIGTERM (Konstante gibt es nur mit pcntl)
            posix_kill(-$pid, 15);
        }
    }

    // --- Hilfsfunktionen ---------------------------------------------------------

    /** Arbeitsordner für CLI, Skript und Log — nicht öffentlich erreichbar */
    public static function workPath(string $file = ''): string
    {
        return TEMP_PATH . DIRECTORY_SEPARATOR . 'maptiles' . ($file ? DIRECTORY_SEPARATOR . $file : '');
    }

    /** Liegen neben der Zieldatei (gleiches Dateisystem → rename() ist atomar); Punktdateien liefert der Webserver nicht aus */
    private static function partPath(): string
    {
        return MapTilesSettings::current()->getDirectoryPath() . DIRECTORY_SEPARATOR . '.download.pmtiles';
    }

    private static function assetsPath(): string
    {
        return MapTilesSettings::current()->getDirectoryPath() . DIRECTORY_SEPARATOR . '.assets-new';
    }

    /** Die letzten Zeilen des Download-Logs — für das CMS und Fehlermeldungen */
    public static function logTail(): string
    {
        $log = self::workPath('run.log');
        if (!is_file($log)) {
            return '';
        }
        $handle = fopen($log, 'r');
        fseek($handle, max(0, filesize($log) - 4096));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);
        return self::lastLines($tail);
    }

    /** Fortschrittsbalken schreiben mit \r in dieselbe Zeile — daher auch daran trennen */
    private static function lastLines(string $text, int $count = 3): string
    {
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $text)));
        return implode("\n", array_slice($lines, -$count));
    }

    /** @return array{0: int, 1: string} Exit-Code und Ausgabe (stdout + stderr) */
    private static function run(string $command): array
    {
        exec($command . ' 2>&1', $output, $code);
        return [$code, implode("\n", $output)];
    }

    private static function isAlive(int $pid): bool
    {
        return $pid > 0 && posix_kill($pid, 0);
    }

    /** Ohne @-Unterdrückung: die Job-Queue macht auch unterdrückte Warnungen zu Fehlern */
    private static function deleteFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function removeDir(string $dir): void
    {
        if (is_dir($dir)) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}
