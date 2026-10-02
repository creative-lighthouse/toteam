<?php

namespace App\Teams;

use App\Calendar\Appointment;
use App\Food\Food;
use App\Maps\Geocoder;
use App\Maps\MapTilesSettings;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Class \App\Teams\OrgEvent
 *
 * Ein Event einer Organisation (z.B. "Halloweenhaus 2026"), das mehrere Termine
 * bündelt — Aufbau, Shows, Abbau. Vorerst dient es vor allem dem Essen: Gerichte
 * werden für ein Event vorgeschlagen und vom Essensplaner dann einer Mahlzeit
 * eines seiner Termine zugeordnet. Verwaltet wird es im Events-Totem.
 *
 * Beginn und Ende sind optional; ohne eigene Angabe ergibt sich der Zeitraum
 * aus den zugeordneten Terminen (siehe toApiSummary()).
 *
 * Jedes Event hat eine eigene Seite unter /app/events/{URLSegment}: Mitglieder
 * der Organisation sehen dort alles, alle anderen (auch ohne Anmeldung) nur die
 * Eckdaten — und das nur, wenn das Event öffentlich ist (siehe isViewableBy()).
 *
 * (Nicht zu verwechseln mit den älteren EventDay-Klassen in App\Events oder den
 * Kalender-"Events" im Frontend, die einzelne Termine meinen.)
 *
 * @property ?string $Title
 * @property ?string $URLSegment
 * @property ?string $DateStart
 * @property ?string $TimeStart
 * @property ?string $DateEnd
 * @property ?string $TimeEnd
 * @property bool $AllDay
 * @property ?string $Location
 * @property ?string $Street
 * @property ?string $PostalCode
 * @property ?string $City
 * @property float $Latitude
 * @property float $Longitude
 * @property ?string $GeocodedAddress
 * @property bool $IsPublic
 * @property ?string $PriceMode
 * @property int $OrganizationID
 * @property int $TypeID
 * @property int $ImageID
 * @method \App\Teams\Organization Organization()
 * @method \App\Teams\OrgEventType Type()
 * @method \SilverStripe\Assets\Image Image()
 * @method \SilverStripe\ORM\DataList|\App\Calendar\Appointment[] Appointments()
 * @method \SilverStripe\ORM\DataList|\App\Food\Food[] Foods()
 * @method \SilverStripe\ORM\DataList|\App\Teams\OrgEventPrice[] Prices()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\OrgEventAgeGroup[] AgeGroups()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Assets\Image[] Images()
 */
class OrgEvent extends DataObject
{
    private static $db = [
        "Title"      => "Varchar(255)",
        "URLSegment" => "Varchar(255)",
        "DateStart"  => "Date",
        "TimeStart" => "Time",
        "DateEnd"   => "Date",
        "TimeEnd"   => "Time",
        "AllDay"    => "Boolean(1)",
        // Adresse: Location ist der Name des Veranstaltungsorts (z.B. "Gemeindehaus");
        // City erscheint auf der OrgEventCard, der Rest nur auf der Event-Seite
        "Location"   => "Varchar(511)",
        "Street"     => "Varchar(255)",
        "PostalCode" => "Varchar(10)",
        "City"       => "Varchar(255)",
        // Koordinaten für die Karte auf der Event-Seite — beim Speichern aus der Adresse
        // ermittelt (siehe geocode()); GeocodedAddress merkt sich, für welche Adresse
        "Latitude"        => "Decimal(9,6)",
        "Longitude"       => "Decimal(9,6)",
        "GeocodedAddress" => "Varchar(1023)",
        "IsPublic"  => "Boolean",
        // Fixed: ein Preis ohne Bezeichnung, Tiered: Preistabelle (z.B. Kinder/Erwachsene),
        // Free/Donation: kostenfrei bzw. gegen Spende, ohne Preise — alle nutzen Prices()
        "PriceMode" => "Enum('Fixed,Tiered,Free,Donation','Fixed')",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
        "Type"         => OrgEventType::class,
        "Image"        => Image::class,
    ];

    private static $indexes = [
        "URLSegment" => true,
    ];

    // Galerie zusätzlich zum Hauptbild — heißt "Images", damit der
    // AttachmentUploads-Trait der API-Controller sie direkt befüllen kann
    private static $owns = [
        "Image",
        "Images",
    ];

    private static $has_many = [
        "Appointments" => Appointment::class . '.Event',
        "Foods"        => Food::class . '.Event',
        "Prices"       => OrgEventPrice::class . '.Event',
    ];

    private static $many_many = [
        "AgeGroups" => OrgEventAgeGroup::class,
        "Images"    => Image::class,
    ];

    private static $cascade_deletes = [
        "Prices",
    ];

    private static $default_sort = "Title ASC";

    private static $field_labels = [
        "Title"        => "Titel",
        "DateStart"    => "Von",
        "TimeStart"    => "Uhrzeit von",
        "DateEnd"      => "Bis",
        "TimeEnd"      => "Uhrzeit bis",
        "AllDay"       => "Ganztägig",
        "Location"     => "Veranstaltungsort",
        "Street"       => "Straße und Hausnummer",
        "PostalCode"   => "PLZ",
        "City"         => "Ort",
        "IsPublic"     => "Öffentlich sichtbar",
        "PriceMode"    => "Preisangabe",
        "Organization" => "Organisation",
        "Type"         => "Art",
        "AgeGroups"    => "Altersgruppen",
        "Image"        => "Bild",
        "Images"       => "Galerie",
        "URLSegment"   => "URL-Segment",
        "Appointments" => "Termine",
        "Foods"        => "Gerichte",
        "Prices"       => "Preise",
    ];

    private static $summary_fields = [
        "Title"              => "Titel",
        "Organization.Title" => "Organisation",
    ];

    private static $table_name = 'OrgEvent';
    private static $singular_name = "Event";
    private static $plural_name = "Events";

    /** Von applyApiData() übergebene Preistabelle — wird in onAfterWrite() gespeichert */
    private ?array $pendingPrices = null;

    /** Von applyApiData() übergebene Altersgruppen — werden in onAfterWrite() gespeichert */
    private ?array $pendingAgeGroupIDs = null;

    /** Termine anlegen/zuordnen darf, wer in der Organisation Termine verwalten darf */
    public function canBeManagedBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::CALENDAR_MANAGE);
    }

    /** Sehen darf ein Event, wer in der Organisation Mitglied ist — oder jeder, wenn es öffentlich ist */
    public function isViewableBy(?Member $member): bool
    {
        return $this->IsPublic || $this->isInternalFor($member);
    }

    /** Mitglieder der Organisation sehen auch die internen Inhalte (Termine usw.) */
    public function isInternalFor(?Member $member): bool
    {
        return $member && in_array((int) $this->OrganizationID, array_map('intval', $member->getOrganizationIDs()), true);
    }

    /** Pfad der Event-Seite im Frontend */
    public function Link(): string
    {
        return '/app/events/' . $this->URLSegment;
    }

    /** Lesbares, eindeutiges URL-Segment aus dem Titel ("halloweenhaus-2026", bei Bedarf "-2" usw.) */
    public function generateURLSegment(): string
    {
        $base = URLSegmentFilter::create()->filter((string) $this->Title) ?: 'event';
        // Rein numerisch würde die API das Segment als ID lesen (z.B. Titel "2026")
        if (ctype_digit($base)) {
            $base = 'event-' . $base;
        }
        $segment = $base;
        for ($i = 2; self::get()->filter('URLSegment', $segment)->exclude('ID', $this->ID ?: 0)->exists(); $i++) {
            $segment = $base . '-' . $i;
        }
        return $segment;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        // Einmal vergeben und dann stabil, damit geteilte Links auch nach dem Umbenennen funktionieren
        if (!$this->URLSegment) {
            $this->URLSegment = $this->generateURLSegment();
        }
        $this->geocode();
    }

    public function hasCoordinates(): bool
    {
        return (float) $this->Latitude !== 0.0 || (float) $this->Longitude !== 0.0;
    }

    /**
     * Suchanfrage für den Geocoder: mit Straße strukturiert, sonst über den Namen des
     * Veranstaltungsorts. Nur der Ort allein reicht nicht — ein Pin in der Stadtmitte
     * würde einen falschen Treffpunkt suggerieren.
     */
    public function geocoderQuery(): ?array
    {
        if ($this->Street && ($this->PostalCode || $this->City)) {
            return array_filter([
                'street'     => $this->Street,
                'postalcode' => $this->PostalCode,
                'city'       => $this->City,
            ]);
        }
        if ($this->Location && ($this->PostalCode || $this->City)) {
            return ['q' => $this->Location . ', ' . trim($this->PostalCode . ' ' . $this->City)];
        }
        return null;
    }

    /**
     * Koordinaten über Nominatim ermitteln, wenn sich die Adresse geändert hat. Läuft
     * serverseitig (keine Nutzerdaten an Dritte). Ist der Dienst nicht erreichbar, wird
     * trotzdem gespeichert und beim nächsten Speichern erneut versucht; eine nicht
     * gefundene Adresse wird sich gemerkt und erst nach einer Änderung neu gesucht.
     */
    public function geocode(): void
    {
        $query = $this->geocoderQuery();
        $key = $query ? implode(' | ', $query) : '';
        if ($key === (string) $this->GeocodedAddress) {
            return;
        }
        $result = null;
        if ($query) {
            try {
                $result = Geocoder::singleton()->geocode($query);
                // Mit Straße nichts gefunden — vielleicht kennt OSM den Ort beim Namen
                if (!$result && $this->Location && isset($query['street'])) {
                    $result = Geocoder::singleton()->geocode(['q' => $this->Location . ', ' . trim($this->PostalCode . ' ' . $this->City)]);
                }
            } catch (\Throwable $e) {
                Injector::inst()->get(LoggerInterface::class)->warning('Geocoding für Event #' . $this->ID . ' fehlgeschlagen: ' . $e->getMessage());
                return;
            }
        }
        $this->Latitude = $result['lat'] ?? 0;
        $this->Longitude = $result['lng'] ?? 0;
        $this->GeocodedAddress = $key ?: null;
    }

    /** Vorhandene Events ohne URL-Segment beim dev/build nachziehen */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        foreach (self::get()->filter('URLSegment', [null, '']) as $event) {
            $event->write();
        }
    }

    public function toApi(): array
    {
        $org = $this->Organization();
        $type = $this->Type();
        $ageGroups = $this->AgeGroups()->sort(['SortOrder' => 'ASC', 'Title' => 'ASC']);
        return [
            'ID'                  => $this->ID,
            'Title'               => $this->Title,
            'URLSegment'          => $this->URLSegment,
            'Link'                => $this->Link(),
            'OrganizationID'      => (int) $this->OrganizationID,
            'OrganizationTitle'   => $org->exists() ? $org->Title : null,
            'OrganizationLogoURL' => $org->exists() ? $org->RenderLogo(80) : null,
            // Eigene Angaben des Events (alle optional)
            'DateStart'     => $this->DateStart ?: null,
            'TimeStart'     => $this->AllDay ? null : ($this->TimeStart ?: null),
            'DateEnd'       => $this->DateEnd ?: null,
            'TimeEnd'       => $this->AllDay ? null : ($this->TimeEnd ?: null),
            'AllDay'        => (bool) $this->AllDay,
            'Location'      => $this->Location ?: null,
            'Street'        => $this->Street ?: null,
            'PostalCode'    => $this->PostalCode ?: null,
            'City'          => $this->City ?: null,
            'Latitude'      => $this->hasCoordinates() ? (float) $this->Latitude : null,
            'Longitude'     => $this->hasCoordinates() ? (float) $this->Longitude : null,
            // Selbst gehostete Kartendaten (null, wenn keine vorhanden oder der Ort außerhalb liegt)
            'Map'           => $this->hasCoordinates()
                ? MapTilesSettings::current()->clientConfig((float) $this->Latitude, (float) $this->Longitude)
                : null,
            'IsPublic'      => (bool) $this->IsPublic,
            'TypeID'        => (int) $this->TypeID ?: null,
            'TypeTitle'     => $type->exists() ? $type->Title : null,
            'AgeGroups'     => array_map(fn ($group) => $group->toApi(), $ageGroups->toArray()),
            'ImageURL'      => $this->RenderImage(),
            'Gallery'       => $this->galleryToApi(),
            'PriceMode'     => $this->PriceMode ?: 'Fixed',
            'Prices'        => array_map(fn ($price) => $price->toApi(), $this->Prices()->toArray()),
        ];
    }

    /** Galeriebilder im selben Format wie AttachmentUploads::formatImages() */
    public function galleryToApi(): array
    {
        $images = [];
        foreach ($this->Images() as $image) {
            $images[] = [
                'ID'        => $image->ID,
                'URL'       => $image->getURL(),
                'Thumbnail' => $image->Fill(300, 300)->getURL(),
                'Name'      => $image->Name,
            ];
        }
        return $images;
    }

    /**
     * Eckdaten für Besucher ohne Mitgliedschaft (öffentliche Events). Ohne
     * Termin-Zeitraum, Zähler und IDs — die verraten interne Planung.
     */
    public function toApiPublic(): array
    {
        $data = $this->toApi();
        unset($data['OrganizationID'], $data['TypeID']);
        return array_merge($data, [
            'RangeStart' => $this->DateStart ?: null,
            'RangeEnd'   => $this->DateStart ? ($this->DateEnd ?: $this->DateStart) : null,
            'CanManage'  => false,
        ]);
    }

    /** Das Bild wird im Frontend im Format 16:9 zugeschnitten hochgeladen (passend zur OrgEventCard) */
    public function RenderImage(int $width = 1280): ?string
    {
        if ($this->ImageID && $this->Image()->exists()) {
            return $this->Image()->Fill($width, (int) round($width * 9 / 16))->getURL();
        }
        return null;
    }

    /**
     * Übernimmt die Formularfelder aus dem Events-Totem (POST/PUT). Nur übergebene
     * Schlüssel werden geändert; `prices` ersetzt die komplette Preistabelle.
     * Schreibt nicht — gibt bei ungültigen Angaben eine Fehlermeldung zurück.
     */
    public function applyApiData(array $data): ?string
    {
        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                return 'Titel ist erforderlich';
            }
            $this->Title = $title;
        }

        foreach (['dateStart' => 'DateStart', 'dateEnd' => 'DateEnd'] as $key => $field) {
            if (array_key_exists($key, $data)) {
                $date = trim((string) $data[$key]);
                if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    return 'Ungültiges Datum';
                }
                $this->$field = $date ?: null;
            }
        }
        if (array_key_exists('allDay', $data)) {
            $this->AllDay = (bool) $data['allDay'];
        }
        foreach (['timeStart' => 'TimeStart', 'timeEnd' => 'TimeEnd'] as $key => $field) {
            if (array_key_exists($key, $data)) {
                $time = trim((string) $data[$key]);
                if ($time !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
                    return 'Ungültige Uhrzeit';
                }
                $this->$field = $time ?: null;
            }
        }
        // Uhrzeiten nur zu einem Datum; ohne Enddatum gilt TimeEnd für den Starttag
        if ($this->AllDay || !$this->DateStart) {
            $this->TimeStart = null;
            $this->TimeEnd = null;
        }
        if (!$this->DateStart && $this->DateEnd) {
            return 'Bitte gib auch ein Startdatum an';
        }
        $start = $this->DateStart . ' ' . ($this->TimeStart ?: '00:00');
        $end = $this->DateEnd . ' ' . ($this->TimeEnd ?: '00:00');
        if ($this->DateEnd && $end < $start) {
            return 'Das Ende darf nicht vor dem Beginn liegen';
        }

        foreach (['location' => 'Location', 'street' => 'Street', 'postalCode' => 'PostalCode', 'city' => 'City'] as $key => $field) {
            if (array_key_exists($key, $data)) {
                $this->$field = trim((string) $data[$key]) ?: null;
            }
        }
        if ($this->PostalCode && mb_strlen($this->PostalCode) > 10) {
            return 'Die PLZ ist zu lang';
        }
        if (array_key_exists('isPublic', $data)) {
            $this->IsPublic = (bool) $data['isPublic'];
        }
        if (array_key_exists('typeId', $data)) {
            $typeID = (int) $data['typeId'];
            if ($typeID && !OrgEventType::get()->byID($typeID)) {
                return 'Unbekannte Event-Art';
            }
            $this->TypeID = $typeID;
        }
        if (array_key_exists('ageGroupIds', $data)) {
            $ageGroupIDs = array_values(array_unique(array_filter(array_map('intval', (array) $data['ageGroupIds']))));
            if ($ageGroupIDs && OrgEventAgeGroup::get()->byIDs($ageGroupIDs)->count() !== count($ageGroupIDs)) {
                return 'Unbekannte Altersgruppe';
            }
            // many_many braucht eine ID — wird in onAfterWrite() gespeichert
            $this->pendingAgeGroupIDs = $ageGroupIDs;
            $this->forceChange();
        }

        if (array_key_exists('priceMode', $data)) {
            if (!in_array($data['priceMode'], ['Fixed', 'Tiered', 'Free', 'Donation'], true)) {
                return 'Ungültige Preisangabe';
            }
            $this->PriceMode = $data['priceMode'];
            // Kostenfrei/gegen Spende hat keine Preise
            if (in_array($this->PriceMode, ['Free', 'Donation'], true)) {
                $data['prices'] = [];
            }
        }
        $isFixed = ($this->PriceMode ?: 'Fixed') === 'Fixed';

        if (array_key_exists('prices', $data)) {
            $prices = [];
            foreach ((array) $data['prices'] as $row) {
                $label = trim((string) ($row['title'] ?? ''));
                $price = $row['price'] ?? '';
                // Komplett leere Zeilen im Formular einfach ignorieren
                if ($label === '' && ($price === '' || $price === null)) {
                    continue;
                }
                // Beim festen Preis gibt es genau einen Betrag, eine Bezeichnung braucht er nicht
                if ($label === '' && !$isFixed) {
                    return 'Bitte gib für jeden Preis eine Bezeichnung an';
                }
                if (!is_numeric($price) || $price < 0 || $price > 99999999) {
                    return $label === '' ? 'Ungültiger Preis' : "Ungültiger Preis für „{$label}“";
                }
                $prices[] = ['Title' => $isFixed ? '' : $label, 'Price' => round((float) $price, 2)];
                if ($isFixed) {
                    break;
                }
            }
            $this->pendingPrices = $prices;
            // Sonst schreibt write() nichts, wenn sich nur die Preise geändert haben,
            // und onAfterWrite() würde nicht aufgerufen
            $this->forceChange();
        }

        return null;
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->pendingAgeGroupIDs !== null) {
            $this->AgeGroups()->setByIDList($this->pendingAgeGroupIDs);
            $this->pendingAgeGroupIDs = null;
        }
        if ($this->pendingPrices === null) {
            return;
        }
        $prices = $this->pendingPrices;
        $this->pendingPrices = null;
        foreach ($this->Prices() as $price) {
            $price->delete();
        }
        foreach ($prices as $sort => $price) {
            OrgEventPrice::create(array_merge($price, ['EventID' => $this->ID, 'SortOrder' => $sort]))->write();
        }
    }

    /**
     * toApi() plus Terminanzahl, Zeitraum und Verwaltungsrecht — für die Events-Übersicht.
     * RangeStart/RangeEnd sind die eigenen Daten des Events oder, ohne eigene Angabe,
     * der Zeitraum seiner Termine.
     */
    public function toApiSummary(Member $member): array
    {
        $appointments = $this->Appointments();
        $first = $appointments->sort('DateStart', 'ASC')->first();
        $dateEnd = null;
        foreach ($appointments as $appointment) {
            $end = $appointment->DateEnd ?: $appointment->DateStart;
            if ($end && (!$dateEnd || $end > $dateEnd)) {
                $dateEnd = $end;
            }
        }
        $ownStart = $this->DateStart ?: null;
        return array_merge($this->toApi(), [
            'AppointmentCount' => $appointments->count(),
            'RangeStart'       => $ownStart ?? ($first ? $first->DateStart : null),
            'RangeEnd'         => $ownStart ? ($this->DateEnd ?: $ownStart) : $dateEnd,
            'FoodCount'        => $this->Foods()->count(),
            'CanManage'        => $this->canBeManagedBy($member),
        ]);
    }

    /** Die Termine des Events, chronologisch, mit der Teilnahme des Mitglieds */
    public function appointmentsToApi(Member $member): array
    {
        $appointments = [];
        foreach ($this->Appointments()->sort(['DateStart' => 'ASC', 'TimeStart' => 'ASC']) as $appointment) {
            $participation = $appointment->Participations()->filter('MemberID', $member->ID)->first();
            $appointments[] = [
                'ID'        => $appointment->ID,
                'Title'     => $appointment->Title,
                'DateStart' => $appointment->DateStart,
                'DateEnd'   => $appointment->DateEnd ?: $appointment->DateStart,
                'TimeStart' => $appointment->TimeStart,
                'TimeEnd'   => $appointment->TimeEnd,
                'AllDay'    => (bool) $appointment->AllDay,
                'Location'  => $appointment->Location ?: null,
                'Status'    => $appointment->Status,
                'EventType' => $appointment->Type()->exists() ? $appointment->Type()->Title : null,
                'UserResponse' => $participation ? $participation->Type : null,
            ];
        }
        return $appointments;
    }

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        // has_one-Verweise werden nicht automatisch gelöst
        foreach ($this->Appointments() as $appointment) {
            $appointment->EventID = 0;
            $appointment->write();
        }
        foreach ($this->Foods() as $food) {
            $food->EventID = 0;
            $food->write();
        }
        $this->AgeGroups()->removeAll();
        // Dateien sind versioniert: doArchive() entfernt sie aus Entwurf und Live
        if ($this->ImageID && $this->Image()->exists()) {
            $this->Image()->deleteFile();
            $this->Image()->doArchive();
        }
        foreach ($this->Images() as $image) {
            $image->deleteFile();
            $image->doArchive();
        }
    }
}
