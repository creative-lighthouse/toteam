<?php

namespace App\Photos;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Zugriff auf Bunny Storage (Hochladen, Löschen) und das Signieren von Pull-Zone-Links
 * für die {@see PhotoStorageConfig} einer Organisation.
 *
 * Signiert wird nach Bunnys "Token Authentication" (SHA256-Variante): Ablaufzeit und
 * Pfad stecken im Token, die Links funktionieren also nur bis `expires`. Die Ablaufzeit
 * wird auf volle Stunden gerundet, damit dieselbe URL eine Weile gleich bleibt und der
 * Browser die Bilder aus dem Cache nehmen kann.
 */
class BunnyStorage
{
    /** Wie lange ein ausgegebener Link mindestens gültig ist */
    private const LINK_LIFETIME = 6 * 3600;
    private const LINK_ROUNDING = 3600;

    public function __construct(private PhotoStorageConfig $config)
    {
    }

    private function http(): Client
    {
        return new Client(['timeout' => 60, 'http_errors' => true]);
    }

    private function storageURL(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));
        return sprintf('https://%s/%s/%s', $this->config->StorageRegion, rawurlencode($this->config->StorageZone), implode('/', $segments));
    }

    /**
     * Lädt eine lokale Datei hoch. Wirft eine \RuntimeException mit verständlicher Meldung.
     */
    public function upload(string $localFile, string $path, string $contentType = 'image/jpeg'): void
    {
        $handle = fopen($localFile, 'rb');
        if (!$handle) {
            throw new \RuntimeException('Datei konnte nicht gelesen werden');
        }
        try {
            $this->http()->put($this->storageURL($path), [
                'headers' => [
                    'AccessKey'    => $this->config->AccessKey,
                    'Content-Type' => $contentType,
                    'Checksum'     => strtoupper(hash_file('sha256', $localFile)),
                ],
                'body' => $handle,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException($this->describe($e));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * Inhalt einer Datei direkt aus dem Storage (mit dem Speicher-Zugang, unabhängig vom
     * Token-Schutz der Pull Zone). Wirft eine \RuntimeException mit verständlicher Meldung.
     */
    public function download(string $path): string
    {
        try {
            return (string) $this->http()->get($this->storageURL($path), [
                'headers' => ['AccessKey' => $this->config->AccessKey],
            ])->getBody();
        } catch (GuzzleException $e) {
            throw new \RuntimeException($this->describe($e));
        }
    }

    /** Löscht eine Datei; fehlt sie schon, ist das kein Fehler */
    public function delete(string $path): void
    {
        try {
            $this->http()->delete($this->storageURL($path), [
                'headers' => ['AccessKey' => $this->config->AccessKey],
            ]);
        } catch (RequestException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                return;
            }
            throw new \RuntimeException($this->describe($e));
        } catch (GuzzleException $e) {
            throw new \RuntimeException($this->describe($e));
        }
    }

    /**
     * Prüft Zugangsdaten und Storage Zone (Verzeichnis-Abruf der Wurzel).
     * @return string|null Fehlermeldung oder null, wenn alles passt
     */
    public function test(): ?string
    {
        try {
            $this->http()->get($this->storageURL('') . '/', [
                'headers' => ['AccessKey' => $this->config->AccessKey, 'Accept' => 'application/json'],
                'timeout' => 15,
            ]);
            return null;
        } catch (GuzzleException $e) {
            return $this->describe($e);
        }
    }

    private function describe(GuzzleException $e): string
    {
        $status = $e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null;
        return match ($status) {
            401     => 'Bunny lehnt den Zugang ab — stimmen Storage Zone, Region und Passwort?',
            404     => 'Storage Zone nicht gefunden — stimmen Name und Region?',
            400     => 'Bunny hat die Datei abgelehnt (Prüfsumme oder Anfrage ungültig)',
            null    => 'Bunny ist gerade nicht erreichbar',
            default => 'Fehler bei Bunny (HTTP ' . $status . ')',
        };
    }

    /**
     * Öffentliche URL einer Datei über die Pull Zone — signiert, wenn ein Token-Schlüssel
     * hinterlegt ist.
     */
    public function publicURL(string $path): string
    {
        $base = rtrim((string) $this->config->PullZoneURL, '/');
        if (!preg_match('#^https?://#i', $base)) {
            $base = 'https://' . $base;
        }
        $urlPath = '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));

        if (!$this->config->TokenKey) {
            return $base . $urlPath;
        }

        $expires = (int) (ceil((time() + self::LINK_LIFETIME) / self::LINK_ROUNDING) * self::LINK_ROUNDING);
        $hash = hash('sha256', $this->config->TokenKey . $urlPath . $expires, true);
        $token = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return $base . $urlPath . '?token=' . $token . '&expires=' . $expires;
    }
}
