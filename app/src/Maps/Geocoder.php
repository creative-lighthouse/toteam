<?php

namespace App\Maps;

use GuzzleHttp\Client;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class \App\Maps\Geocoder
 *
 * Wandelt Adressen über Nominatim (OpenStreetMap) in Koordinaten um. Läuft nur
 * serverseitig, damit keine Daten der Nutzer an Dritte gehen.
 *
 * Nutzungsbedingungen des öffentlichen Servers: höchstens eine Anfrage pro
 * Sekunde und ein User-Agent, der die Anwendung erkennbar macht.
 * https://operations.osmfoundation.org/policies/nominatim/
 */
class Geocoder
{
    use Injectable;
    use Configurable;

    private static string $endpoint = 'https://nominatim.openstreetmap.org/search';

    /** Kommagetrennte ISO-Ländercodes, auf die die Suche beschränkt wird */
    private static string $country_codes = 'de';

    /** Sekunden bis zum Abbruch — gespeichert wird das Event trotzdem */
    private static int $timeout = 5;

    /** Zeitpunkt der letzten Anfrage (für das Limit von 1/s — Geocoder wird als singleton() genutzt) */
    private float $lastRequest = 0;

    /**
     * Strukturierte Suche (street/postalcode/city) oder Freitext (q).
     *
     * @param array<string, string> $query
     * @return array{lat: float, lng: float}|null null, wenn die Adresse nicht gefunden wurde
     * @throws \GuzzleHttp\Exception\GuzzleException bei Netzwerkfehlern
     */
    public function geocode(array $query): ?array
    {
        $this->throttle();
        $client = new Client(['timeout' => static::config()->get('timeout')]);
        $params = array_merge($query, [
            'format'          => 'jsonv2',
            'limit'           => 1,
            'countrycodes'    => static::config()->get('country_codes'),
            'accept-language' => 'de',
        ]);
        // Optionale Kontaktadresse, damit uns die OSM-Betreiber bei Problemen erreichen
        if ($email = Environment::getEnv('NOMINATIM_EMAIL')) {
            $params['email'] = $email;
        }
        $response = $client->get(static::config()->get('endpoint'), [
            'query'   => $params,
            'headers' => ['User-Agent' => 'ToTeam (' . Director::absoluteBaseURL() . ')'],
        ]);

        $results = json_decode((string) $response->getBody(), true);
        if (!is_array($results) || empty($results[0]['lat'])) {
            return null;
        }
        return [
            'lat' => (float) $results[0]['lat'],
            'lng' => (float) $results[0]['lon'],
        ];
    }

    private function throttle(): void
    {
        $wait = $this->lastRequest + 1.0 - microtime(true);
        if ($wait > 0) {
            usleep((int) ceil($wait * 1000000));
        }
        $this->lastRequest = microtime(true);
    }
}
