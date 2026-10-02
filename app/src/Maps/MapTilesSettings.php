<?php

namespace App\Maps;

use App\Admins\MapTilesAdmin;
use SilverStripe\Control\Director;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class \App\Maps\MapTilesSettings
 *
 * Einstellungen und Stand der selbst gehosteten Kartendaten (genau ein Datensatz,
 * bearbeitet im CMS unter "Kartendaten", siehe MapTilesAdmin).
 *
 * Die Kacheln (Protomaps-Basiskarte aus OpenStreetMap-Daten) liegen als eine
 * .pmtiles-Datei unter public/tiles/ und werden vom Webserver direkt ausgeliefert;
 * Schriften und Symbole liegen daneben. Heruntergeladen werden sie vom
 * UpdateMapTilesJob über die Job-Queue — so muss nichts davon ins Repository.
 *
 * @property ?string $BBox
 * @property int $MaxZoom
 * @property bool $AutoUpdate
 * @property int $UpdateIntervalMonths
 * @property ?string $TilesBuild
 * @property ?string $TilesBBox
 * @property int $TilesMaxZoom
 * @property int $TilesSize
 * @property ?string $TilesUpdated
 * @property ?string $LastError
 */
class MapTilesSettings extends DataObject
{
    /** Ordner unter public/ — vom Webserver direkt ausgeliefert, nicht im Repository */
    private static string $directory = 'tiles';

    private static string $file_name = 'basemap.pmtiles';

    /** Vorgaben, wenn im CMS nichts eingetragen ist — siehe app/_config/maps.yml */
    private static string $default_bbox = '';
    private static int $default_max_zoom = 15;

    private static $db = [
        // Einstellungen
        "BBox"                 => "Varchar(100)",
        "MaxZoom"              => "Int",
        "AutoUpdate"           => "Boolean(1)",
        "UpdateIntervalMonths" => "Int",
        // Stand der aktuell ausgelieferten Datei (schreibt der UpdateMapTilesJob)
        "TilesBuild"   => "Varchar(20)",
        "TilesBBox"    => "Varchar(100)",
        "TilesMaxZoom" => "Int",
        "TilesSize"    => "BigInt",
        "TilesUpdated" => "Datetime",
        "LastError"    => "Text",
    ];

    private static $defaults = [
        "AutoUpdate"           => true,
        "UpdateIntervalMonths" => 3,
    ];

    private static $table_name = 'MapTilesSettings';
    private static $singular_name = "Kartendaten";
    private static $plural_name = "Kartendaten";

    /** Der eine Datensatz — ungespeichert, solange im CMS noch nichts gespeichert wurde */
    public static function current(): self
    {
        return self::get()->setUseCache(true)->first() ?? self::create();
    }

    public function getEffectiveBBox(): string
    {
        return $this->BBox ?: static::config()->get('default_bbox');
    }

    public function getEffectiveMaxZoom(): int
    {
        return $this->MaxZoom ?: static::config()->get('default_max_zoom');
    }

    /** @return float[]|null [min_lon, min_lat, max_lon, max_lat] */
    public static function parseBBox(?string $bbox): ?array
    {
        $parts = array_map('trim', explode(',', (string) $bbox));
        if (count($parts) !== 4 || array_filter($parts, fn ($part) => !is_numeric($part))) {
            return null;
        }
        [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', $parts);
        if ($minLon < -180 || $maxLon > 180 || $minLat < -90 || $maxLat > 90 || $minLon >= $maxLon || $minLat >= $maxLat) {
            return null;
        }
        return [$minLon, $minLat, $maxLon, $maxLat];
    }

    public function getDirectoryPath(): string
    {
        return PUBLIC_PATH . DIRECTORY_SEPARATOR . static::config()->get('directory');
    }

    public function getFilePath(): string
    {
        return $this->getDirectoryPath() . DIRECTORY_SEPARATOR . static::config()->get('file_name');
    }

    public function isAvailable(): bool
    {
        return $this->TilesBuild && is_file($this->getFilePath());
    }

    /**
     * Was das Frontend für die Karte braucht — oder null, wenn es keine Kartendaten
     * gibt bzw. der Punkt außerhalb des heruntergeladenen Bereichs liegt.
     */
    public function clientConfig(float $lat, float $lng): ?array
    {
        $bbox = self::parseBBox($this->TilesBBox);
        if (!$bbox || !$this->isAvailable()) {
            return null;
        }
        [$minLon, $minLat, $maxLon, $maxLat] = $bbox;
        if ($lng < $minLon || $lng > $maxLon || $lat < $minLat || $lat > $maxLat) {
            return null;
        }
        $base = Director::baseURL() . static::config()->get('directory');
        return [
            // Stand als Parameter, damit Browser nach einem Update nicht alte Kacheln aus dem Cache nehmen
            'TilesURL'  => $base . '/' . static::config()->get('file_name') . '?v=' . $this->TilesBuild,
            'AssetsURL' => $base,
            'MaxZoom'   => (int) $this->TilesMaxZoom,
        ];
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if ($this->BBox && !self::parseBBox($this->BBox)) {
            $result->addFieldError('BBox', 'Bitte im Format min_lon,min_lat,max_lon,max_lat angeben, z.B. 7.8,53.35,11.35,55.1');
        }
        if ($this->MaxZoom && ($this->MaxZoom < 10 || $this->MaxZoom > 15)) {
            $result->addFieldError('MaxZoom', 'Die Zoomstufe muss zwischen 10 und 15 liegen');
        }
        if ($this->AutoUpdate && $this->UpdateIntervalMonths < 1) {
            $result->addFieldError('UpdateIntervalMonths', 'Bitte mindestens 1 Monat angeben');
        }
        return $result;
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->ensureScheduled();
    }

    // --- Job-Queue -------------------------------------------------------------

    /** Wartende oder laufende Aktualisierungen */
    public static function pendingJobs()
    {
        return QueuedJobDescriptor::get()->filter([
            'Implementation' => UpdateMapTilesJob::class,
            'JobStatus'      => [QueuedJob::STATUS_NEW, QueuedJob::STATUS_WAIT, QueuedJob::STATUS_INIT, QueuedJob::STATUS_RUN],
        ]);
    }

    public static function runningJob(): ?QueuedJobDescriptor
    {
        return self::pendingJobs()->filter('JobStatus', [QueuedJob::STATUS_INIT, QueuedJob::STATUS_RUN])->first();
    }

    /**
     * Plant eine Aktualisierung ein (sofort ohne $startAfter) und ersetzt eine schon
     * geplante. Gibt eine Meldung für das CMS zurück.
     */
    public function queueUpdate(?string $startAfter = null): string
    {
        if (self::runningJob()) {
            return 'Die Kartendaten werden gerade schon aktualisiert.';
        }
        foreach (self::pendingJobs() as $descriptor) {
            $descriptor->delete();
        }
        QueuedJobService::singleton()->queueJob(UpdateMapTilesJob::create(), $startAfter);
        return $startAfter
            ? 'Nächste Aktualisierung geplant für ' . date('d.m.Y', strtotime($startAfter)) . '.'
            : 'Aktualisierung gestartet — das kann je nach Bereich einige Minuten dauern.';
    }

    /**
     * Hält die automatische Aktualisierung im Plan: bei AutoUpdate eine geplante
     * Aktualisierung nach Ablauf des Intervalls, sonst keine. Startet nie den ersten
     * Download — der wird im CMS bewusst ausgelöst.
     */
    public function ensureScheduled(): void
    {
        if (self::runningJob()) {
            return;
        }
        if (!$this->AutoUpdate) {
            foreach (self::pendingJobs() as $descriptor) {
                $descriptor->delete();
            }
            return;
        }
        if (!$this->TilesUpdated) {
            return;
        }
        // Eine schon fällige (z.B. per Knopf gestartete) Aktualisierung nicht wieder verschieben
        $pending = self::pendingJobs()->first();
        if ($pending && (!$pending->StartAfter || strtotime($pending->StartAfter) <= DBDatetime::now()->getTimestamp())) {
            return;
        }
        $this->queueUpdate($this->nextUpdateDate());
    }

    public function nextUpdateDate(): string
    {
        $next = strtotime($this->TilesUpdated . ' +' . max(1, $this->UpdateIntervalMonths) . ' months');
        return date('Y-m-d H:i:s', max($next, DBDatetime::now()->getTimestamp()));
    }

    // --- CMS -------------------------------------------------------------------

    public function getCMSFields()
    {
        $fields = FieldList::create(
            LiteralField::create('Status', $this->renderStatus()),
            HeaderField::create('SettingsHeader', 'Einstellungen', 3),
            TextField::create('BBox', 'Bereich')
                ->setAttribute('placeholder', static::config()->get('default_bbox'))
                ->setDescription(
                    'min_lon,min_lat,max_lon,max_lat — leer lassen für die Vorgabe (Platzhalter). '
                    . 'Auf Events außerhalb des Bereichs wird keine Karte angezeigt. '
                    . 'Eine Änderung wirkt sich bei der nächsten Aktualisierung aus.'
                ),
            DropdownField::create('MaxZoom', 'Maximale Zoomstufe', [
                15 => '15 — alle Details (Hausnummern, POIs)',
                14 => '14 — etwa halbe Dateigröße',
                13 => '13',
                12 => '12',
            ])->setEmptyString('Vorgabe (' . static::config()->get('default_max_zoom') . ')'),
            CheckboxField::create('AutoUpdate', 'Automatisch aktualisieren'),
            NumericField::create('UpdateIntervalMonths', 'Intervall in Monaten')
                ->setDescription('Die OpenStreetMap-Daten ändern sich laufend; alle paar Monate reicht für Veranstaltungsorte.')
        );
        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    public function getCMSActions()
    {
        $actions = FieldList::create();
        if ($this->canEdit()) {
            $actions->push(FormAction::create('save', 'Speichern')->addExtraClass('btn btn-primary'));
            $actions->push(
                FormAction::create('doUpdateNow', 'Speichern und jetzt aktualisieren')
                    ->addExtraClass('btn btn-outline-primary')
            );
        }
        return $actions;
    }

    private function renderStatus(): string
    {
        $rows = [];
        if ($this->isAvailable()) {
            $rows['Stand der Daten'] = date('d.m.Y', strtotime($this->TilesBuild)) . ' (OpenStreetMap via Protomaps)';
            $rows['Bereich'] = $this->TilesBBox . ', Zoom 0–' . $this->TilesMaxZoom;
            $rows['Dateigröße'] = self::formatBytes((int) $this->TilesSize);
            $rows['Heruntergeladen am'] = date('d.m.Y H:i', strtotime($this->TilesUpdated));
        } else {
            $rows['Kartendaten'] = 'Noch nicht heruntergeladen — „Speichern und jetzt aktualisieren“ startet den Download.';
        }

        $alerts = [];
        $running = self::runningJob();
        $pending = self::pendingJobs()->filter('JobStatus', [QueuedJob::STATUS_NEW, QueuedJob::STATUS_WAIT])->first();
        if ($running) {
            $log = UpdateMapTilesJob::logTail();
            $alerts[] = ['info', 'Aktualisierung läuft seit ' . date('H:i', strtotime($running->Created)) . ' Uhr.'
                . ($log ? "\n" . $log : '') . "\n(Seite neu laden für den aktuellen Stand)"];
        } elseif ($pending) {
            $startAfter = $pending->StartAfter ? strtotime($pending->StartAfter) : strtotime($pending->Created);
            $due = $startAfter <= DBDatetime::now()->getTimestamp();
            $rows['Nächste Aktualisierung'] = $due ? 'sobald die Job-Queue läuft' : date('d.m.Y H:i', $startAfter);
            // Ohne Cronjob für die Job-Queue passiert nie etwas — dann deutlich darauf hinweisen
            if ($due && DBDatetime::now()->getTimestamp() - $startAfter > 600) {
                $alerts[] = ['warning', 'Die Aktualisierung wartet seit ' . date('d.m.Y H:i', $startAfter)
                    . ' Uhr. Läuft der Cronjob für die Job-Queue (sake tasks:ProcessJobQueueTask)?'];
            }
        } elseif ($this->AutoUpdate && $this->isAvailable()) {
            $rows['Nächste Aktualisierung'] = 'wird beim Speichern geplant';
        }
        if ($this->LastError) {
            $alerts[] = ['danger', "Letzte Aktualisierung fehlgeschlagen:\n" . $this->LastError];
        }

        $html = '<h3>Stand</h3><table class="table" style="max-width: 900px">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th style="width: 220px">' . htmlspecialchars($label) . '</th><td>' . htmlspecialchars($value) . '</td></tr>';
        }
        $html .= '</table>';
        foreach ($alerts as [$type, $text]) {
            $html .= '<div class="alert alert-' . $type . '" style="max-width: 900px; white-space: pre-line">' . htmlspecialchars($text) . '</div>';
        }
        return $html;
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return number_format($size, $i > 1 ? 1 : 0, ',', '.') . ' ' . $units[$i];
    }

    public function canView($member = null)
    {
        return Permission::check(MapTilesAdmin::PERMISSION, 'any', $member);
    }

    public function canEdit($member = null)
    {
        return $this->canView($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->canView($member);
    }

    public function canDelete($member = null)
    {
        return false;
    }
}
