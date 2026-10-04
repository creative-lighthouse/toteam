<?php

namespace App\Marketing;

use App\Maps\Geocoder;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;

/**
 * Class \App\Marketing\PosterDistribution
 *
 * Ein Eintrag "wo wurden wie viele Plakate welcher Größe verteilt", optional
 * mit Koordinaten. `DistributedAt` ist der (editierbare, auf "jetzt"
 * vorausgefüllte) Zeitpunkt der Verteilung und dient als Zeitstempel für die
 * jahresweise Rückschau im Frontend.
 *
 * Koordinaten stammen entweder vom Gerät (`CoordinatesSource` = GPS) oder
 * werden beim Speichern aus dem Ort-Text ermittelt (Address, siehe geocode()).
 * Street/PostalCode/City/District füllt ebenfalls nur das Geocoding (z.B. für
 * Auswertungen nach Ort) — sie stehen nicht im Formular.
 *
 * @property ?string $Location
 * @property int $Quantity
 * @property ?string $Latitude
 * @property ?string $Longitude
 * @property string $CoordinatesSource
 * @property ?string $GeocodedLocation
 * @property ?string $Street
 * @property ?string $PostalCode
 * @property ?string $City
 * @property ?string $District
 * @property ?string $Note
 * @property ?string $DistributedAt
 * @property int $PosterSizeID
 * @property int $OrganizationID
 * @property int $MemberID
 * @method \App\Marketing\PosterSize PosterSize()
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class PosterDistribution extends DataObject
{
    private static $db = [
        "Location"      => "Varchar(255)",
        "Quantity"      => "Int",
        "Latitude"      => "Varchar(30)",
        "Longitude"     => "Varchar(30)",
        // Woher die Koordinaten stammen — GPS-Werte werden nie durch Geocoding ersetzt
        "CoordinatesSource" => "Enum('None,GPS,Address','None')",
        // Ort-Text, für den zuletzt geocodiert wurde (auch ohne Treffer), damit
        // nur nach einer Änderung erneut bei Nominatim gefragt wird
        "GeocodedLocation"  => "Varchar(255)",
        // Aufgeteilte Adresse aus dem Geocoding (nicht im Formular)
        "Street"            => "Varchar(255)",
        "PostalCode"        => "Varchar(10)",
        "City"              => "Varchar(255)",
        "District"          => "Varchar(255)",
        "Note"          => "Text",
        "DistributedAt" => "Datetime",
    ];

    private static $has_one = [
        "PosterSize"   => PosterSize::class,
        "Organization" => Organization::class,
        "Member"       => Member::class,
    ];

    private static $default_sort = "DistributedAt DESC";

    private static $field_labels = [
        "Location"      => "Ort",
        "Quantity"      => "Anzahl",
        "Latitude"      => "Breitengrad",
        "Longitude"     => "Längengrad",
        "CoordinatesSource" => "Herkunft der Koordinaten",
        "Street"        => "Straße",
        "PostalCode"    => "PLZ",
        "City"          => "Ort (Gemeinde)",
        "District"      => "Stadtteil",
        "Note"          => "Notiz",
        "DistributedAt" => "Zeitpunkt",
        "PosterSize"    => "Größe",
        "Organization"  => "Organisation",
        "Member"        => "Erfasst von",
    ];

    private static $summary_fields = [
        "Location"        => "Ort",
        "Quantity"        => "Anzahl",
        "PosterSize.Title" => "Größe",
        "Member.Name"     => "Erfasst von",
        "DistributedAt"   => "Datum",
    ];

    private static $table_name = 'PosterDistribution';
    private static $singular_name = "Plakat-Verteilung";
    private static $plural_name = "Plakat-Verteilungen";

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->DistributedAt) {
            $this->DistributedAt = date('Y-m-d H:i:s');
        }
        $this->geocode();
    }

    public function hasCoordinates(): bool
    {
        return $this->Latitude && $this->Longitude;
    }

    /**
     * Koordinaten und Adressteile ermitteln, sobald sich die Eingabe geändert
     * hat: bei GPS-Position die Adresse dazu (umgekehrte Suche), sonst
     * Koordinaten und Adresse aus dem Ort-Text. `GeocodedLocation` merkt sich
     * die zuletzt verwendete Eingabe. Ist Nominatim nicht erreichbar, wird
     * trotzdem gespeichert und beim nächsten Speichern erneut versucht.
     */
    public function geocode(): void
    {
        if ($this->CoordinatesSource === 'GPS') {
            $this->geocodeGps();
            return;
        }
        $location = trim((string) $this->Location);
        if ($location === (string) $this->GeocodedLocation) {
            return;
        }
        $result = null;
        if ($location) {
            try {
                $result = $this->locate($location);
            } catch (\Throwable $e) {
                $this->logGeocodingError($e);
                return;
            }
        }
        $this->Latitude = $result ? number_format($result['lat'], 6, '.', '') : null;
        $this->Longitude = $result ? number_format($result['lng'], 6, '.', '') : null;
        $this->CoordinatesSource = $result ? 'Address' : 'None';
        $this->GeocodedLocation = $location ?: null;
        $this->setAddressParts($result['address'] ?? null);
    }

    private function geocodeGps(): void
    {
        $key = 'GPS ' . $this->Latitude . ',' . $this->Longitude;
        if ($key === (string) $this->GeocodedLocation) {
            return;
        }
        try {
            $result = Geocoder::singleton()->reverseLookup((float) $this->Latitude, (float) $this->Longitude);
        } catch (\Throwable $e) {
            $this->logGeocodingError($e);
            return;
        }
        $this->GeocodedLocation = $key;
        $this->setAddressParts($result['address'] ?? null);
    }

    /** @param array{street: string, postcode: string, city: string, district: string}|null $address */
    private function setAddressParts(?array $address): void
    {
        $this->Street = ($address['street'] ?? '') ?: null;
        $this->PostalCode = ($address['postcode'] ?? '') ?: null;
        $this->City = ($address['city'] ?? '') ?: null;
        $this->District = ($address['district'] ?? '') ?: null;
    }

    private function logGeocodingError(\Throwable $e): void
    {
        Injector::inst()->get(LoggerInterface::class)->warning('Geocoding für Plakat-Eintrag #' . $this->ID . ' fehlgeschlagen: ' . $e->getMessage());
    }

    /** Die Adressteile kommen aus dem Geocoding und sind im CMS nur lesbar */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('GeocodedLocation');
        foreach (['CoordinatesSource', 'Street', 'PostalCode', 'City', 'District'] as $name) {
            if ($field = $fields->dataFieldByName($name)) {
                $fields->replaceField($name, $field->performReadonlyTransformation());
            }
        }
        return $fields;
    }

    /**
     * Ein Treffer wird nur übernommen, wenn er eindeutig ist — ein falscher
     * Pin ist schlimmer als keiner. Nur Treffer ab Straßen-Genauigkeit zählen
     * (place_rank >= 26; Orte und Stadtteile allein sind zu ungenau).
     *
     * 1. Steht ein Ort oder eine PLZ im Text, die zu einem Treffer passt
     *    ("Rathaus Ahrensburg", "Hauptstraße 5, 22926"), wird der genommen.
     * 2. Sonst wird nur im Gebiet gesucht, in dem die Organisation bisher per
     *    GPS plakatiert hat, und es zählt nur, wenn alle Treffer dort an
     *    derselben Stelle liegen.
     * 3. Ohne bisherige GPS-Einträge muss der Treffer deutschlandweit eindeutig sein.
     *
     * Nominatim findet bei Freitext nur etwas, wenn alle Wörter passen — ein in
     * OSM unbekannter Name vorne ("Firma XY, Rondeel 1, Ahrensburg") verhindert
     * sonst jeden Treffer. Ohne Ergebnis werden daher die vorderen, durch Komma
     * getrennten Teile nacheinander weggelassen. Ein Ort im weggelassenen Teil
     * zählt für Regel 1 weiterhin.
     *
     * @return array{lat: float, lng: float, address: array{street: string, postcode: string, city: string, district: string}}|null
     */
    private function locate(string $location): ?array
    {
        $geocoder = Geocoder::singleton();
        $parts = array_values(array_filter(array_map('trim', explode(',', $location))));

        $query = null;
        $results = [];
        for ($i = 0; $i < count($parts) && !$results; $i++) {
            $query = implode(', ', array_slice($parts, $i));
            $results = $geocoder->search($query);
        }
        if (!$results) {
            return null;
        }
        $hits = $this->preciseHits($results);

        $text = mb_strtolower($location);
        foreach ($hits as $hit) {
            if ($hit['postcode'] && preg_match('/\b' . preg_quote($hit['postcode'], '/') . '\b/u', $text)) {
                return $hit;
            }
            foreach ($hit['localities'] as $locality) {
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote(mb_strtolower($locality), '/') . '(?![\p{L}\p{N}])/u', $text)) {
                    return $hit;
                }
            }
        }

        if ($bounds = $this->distributionArea()) {
            $hits = $this->preciseHits($geocoder->search($query, 10, $bounds));
        }
        return $this->singleSpot($hits);
    }

    /**
     * @param list<array{lat: float, lng: float, rank: int, postcode: string, localities: list<string>}> $hits
     * @return list<array{lat: float, lng: float, rank: int, postcode: string, localities: list<string>}>
     */
    private function preciseHits(array $hits): array
    {
        return array_values(array_filter($hits, fn ($hit) => $hit['rank'] >= 26));
    }

    /**
     * Der erste Treffer, wenn alle innerhalb von 1,5 km liegen — eine Straße
     * kommt bei Nominatim oft als mehrere Abschnitte zurück.
     *
     * @param list<array{lat: float, lng: float}> $hits
     * @return array{lat: float, lng: float}|null
     */
    private function singleSpot(array $hits): ?array
    {
        if (!$hits) {
            return null;
        }
        $first = $hits[0];
        foreach ($hits as $hit) {
            if (Geocoder::distance($first['lat'], $first['lng'], $hit['lat'], $hit['lng']) > 1.5) {
                return null;
            }
        }
        return $first;
    }

    /**
     * Gebiet, in dem die Organisation bisher per GPS plakatiert hat
     * (die letzten 200 Einträge), um rund 10 km erweitert.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null [minLat, minLng, maxLat, maxLng]
     */
    private function distributionArea(): ?array
    {
        $points = PosterDistribution::get()
            ->filter(['OrganizationID' => $this->OrganizationID, 'CoordinatesSource' => 'GPS'])
            ->exclude('ID', $this->ID ?: 0)
            ->limit(200);
        $lats = array_map('floatval', $points->column('Latitude'));
        $lngs = array_map('floatval', $points->column('Longitude'));
        if (!$lats) {
            return null;
        }
        return [min($lats) - 0.09, min($lngs) - 0.15, max($lats) + 0.09, max($lngs) + 0.15];
    }

    /** Vorhandene Einträge mit Koordinaten stammen alle vom GPS */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        DB::query("UPDATE \"PosterDistribution\" SET \"CoordinatesSource\" = 'GPS'"
            . " WHERE \"CoordinatesSource\" = 'None' AND \"Latitude\" IS NOT NULL AND \"Latitude\" != ''"
            . " AND \"GeocodedLocation\" IS NULL");
    }

    public function isViewableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->isActiveMemberOfOrg($org);
    }

    /**
     * Ein Eintrag darf von seinem Autor oder von jemandem mit
     * MARKETING_MANAGE_ENTRIES bearbeitet/gelöscht werden.
     */
    public function isEditableBy(Member $member): bool
    {
        $org = $this->Organization();
        if (!$org || !$org->exists()) {
            return false;
        }
        if ((int) $this->MemberID === (int) $member->ID) {
            return true;
        }
        return $member->hasOrgPermission($org, OrgPermissions::MARKETING_MANAGE_ENTRIES);
    }

    public function isDeletableBy(Member $member): bool
    {
        return $this->isEditableBy($member);
    }
}
