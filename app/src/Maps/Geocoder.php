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
 * Wandelt Adressen über Nominatim (OpenStreetMap) in Koordinaten um und
 * umgekehrt. Läuft nur serverseitig, damit keine Daten der Nutzer an Dritte gehen.
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

    private static string $reverse_endpoint = 'https://nominatim.openstreetmap.org/reverse';

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
        $results = $this->request(static::config()->get('endpoint'), array_merge($query, [
            'limit'        => 1,
            'countrycodes' => static::config()->get('country_codes'),
        ]));
        if (!is_array($results) || empty($results[0]['lat'])) {
            return null;
        }
        return [
            'lat' => (float) $results[0]['lat'],
            'lng' => (float) $results[0]['lon'],
        ];
    }

    /**
     * Freitextsuche mit mehreren Treffern, für Aufrufer, die selbst
     * entscheiden, ob ein Treffer eindeutig genug ist.
     *
     * @param array{0: float, 1: float, 2: float, 3: float}|null $bounds
     *        [minLat, minLng, maxLat, maxLng] — nur Treffer innerhalb
     * @return list<array{lat: float, lng: float, rank: int, postcode: string, localities: list<string>, address: array{street: string, postcode: string, city: string, district: string}}>
     * @throws \GuzzleHttp\Exception\GuzzleException bei Netzwerkfehlern
     */
    public function search(string $text, int $limit = 10, ?array $bounds = null): array
    {
        $params = [
            'q'              => $text,
            'limit'          => $limit,
            'countrycodes'   => static::config()->get('country_codes'),
            'addressdetails' => 1,
        ];
        if ($bounds) {
            [$minLat, $minLng, $maxLat, $maxLng] = $bounds;
            $params['viewbox'] = implode(',', [$minLng, $maxLat, $maxLng, $minLat]);
            $params['bounded'] = 1;
        }

        $results = $this->request(static::config()->get('endpoint'), $params);
        if (!is_array($results)) {
            return [];
        }

        $hits = [];
        foreach ($results as $result) {
            if (empty($result['lat'])) {
                continue;
            }
            $address = $result['address'] ?? [];
            $localities = [];
            foreach (['city', 'town', 'village', 'municipality', 'suburb', 'hamlet'] as $key) {
                if (!empty($address[$key])) {
                    $localities[] = $address[$key];
                }
            }
            $hits[] = [
                'lat'        => (float) $result['lat'],
                'lng'        => (float) $result['lon'],
                'rank'       => (int) ($result['place_rank'] ?? 0),
                'postcode'   => (string) ($address['postcode'] ?? ''),
                'localities' => $localities,
                'address'    => self::addressParts($address),
            ];
        }
        return $hits;
    }

    /** Entfernung in Kilometern (Haversine) */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Umgekehrte Suche: Koordinaten → lesbare Adresse, z.B.
     * "Rathaus, Marktplatz 1, 24103 Kiel". Ein benannter Ort (Geschäft,
     * Haltestelle…) steht vorne, sofern er nicht nur die Straße wiederholt.
     *
     * @return string|null null, wenn an der Stelle nichts gefunden wurde
     * @throws \GuzzleHttp\Exception\GuzzleException bei Netzwerkfehlern
     */
    public function reverse(float $lat, float $lng): ?string
    {
        return $this->reverseLookup($lat, $lng)['label'] ?? null;
    }

    /**
     * Umgekehrte Suche mit lesbarer Adresse (`label`, siehe reverse()) und
     * ihren Einzelteilen (`address`, siehe addressParts()).
     *
     * @return array{label: string, address: array{street: string, postcode: string, city: string, district: string}}|null
     * @throws \GuzzleHttp\Exception\GuzzleException bei Netzwerkfehlern
     */
    public function reverseLookup(float $lat, float $lng): ?array
    {
        $result = $this->request(static::config()->get('reverse_endpoint'), [
            'lat'            => $lat,
            'lon'            => $lng,
            'zoom'           => 18,
            'addressdetails' => 1,
        ]);
        if (!is_array($result) || empty($result['address'])) {
            return null;
        }

        $address = self::addressParts($result['address']);
        $city = trim($address['postcode'] . ' ' . $address['city']);

        $name = trim((string) ($result['name'] ?? ''));
        if ($name && str_starts_with($address['street'], $name)) {
            $name = '';
        }

        $parts = array_values(array_filter([$name, $address['street'], $city]));
        return [
            'label'   => $parts ? implode(', ', $parts) : (string) ($result['display_name'] ?? ''),
            'address' => $address,
        ];
    }

    /**
     * Die Adressteile aus Nominatims `address`-Objekt, jeweils '' wenn unbekannt.
     * `city` ist die Gemeinde (Stadt/Dorf), `district` der Stadtteil.
     *
     * @param array<string, string> $address
     * @return array{street: string, postcode: string, city: string, district: string}
     */
    public static function addressParts(array $address): array
    {
        $road = $address['road'] ?? $address['pedestrian'] ?? $address['footway'] ?? $address['square'] ?? '';
        return [
            'street'   => trim($road . ' ' . ($road ? ($address['house_number'] ?? '') : '')),
            'postcode' => (string) ($address['postcode'] ?? ''),
            'city'     => (string) ($address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? ''),
            'district' => (string) ($address['suburb'] ?? $address['city_district'] ?? $address['quarter'] ?? $address['hamlet'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return mixed dekodierte JSON-Antwort
     */
    private function request(string $endpoint, array $params): mixed
    {
        $this->throttle();
        $client = new Client(['timeout' => static::config()->get('timeout')]);
        $params = array_merge($params, [
            'format'          => 'jsonv2',
            'accept-language' => 'de',
        ]);
        // Optionale Kontaktadresse, damit uns die OSM-Betreiber bei Problemen erreichen
        if ($email = Environment::getEnv('NOMINATIM_EMAIL')) {
            $params['email'] = $email;
        }
        $response = $client->get($endpoint, [
            'query'   => $params,
            'headers' => ['User-Agent' => 'ToTeam (' . Director::absoluteBaseURL() . ')'],
        ]);

        return json_decode((string) $response->getBody(), true);
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
