<?php

namespace App\Teams;

use App\Calendar\Appointment;
use App\Food\Food;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

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
 * (Nicht zu verwechseln mit den älteren EventDay-Klassen in App\Events oder den
 * Kalender-"Events" im Frontend, die einzelne Termine meinen.)
 *
 * @property ?string $Title
 * @property ?string $DateStart
 * @property ?string $TimeStart
 * @property ?string $DateEnd
 * @property ?string $TimeEnd
 * @property bool $AllDay
 * @property ?string $Location
 * @property bool $IsPublic
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
 */
class OrgEvent extends DataObject
{
    private static $db = [
        "Title"     => "Varchar(255)",
        "DateStart" => "Date",
        "TimeStart" => "Time",
        "DateEnd"   => "Date",
        "TimeEnd"   => "Time",
        "AllDay"    => "Boolean(1)",
        "Location"  => "Varchar(511)",
        "IsPublic"  => "Boolean",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
        "Type"         => OrgEventType::class,
        "Image"        => Image::class,
    ];

    private static $owns = [
        "Image",
    ];

    private static $has_many = [
        "Appointments" => Appointment::class . '.Event',
        "Foods"        => Food::class . '.Event',
        "Prices"       => OrgEventPrice::class . '.Event',
    ];

    private static $many_many = [
        "AgeGroups" => OrgEventAgeGroup::class,
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
        "Location"     => "Ort",
        "IsPublic"     => "Öffentlich sichtbar",
        "Organization" => "Organisation",
        "Type"         => "Art",
        "AgeGroups"    => "Altersgruppen",
        "Image"        => "Bild",
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

    public function toApi(): array
    {
        $org = $this->Organization();
        $type = $this->Type();
        $ageGroups = $this->AgeGroups()->sort(['SortOrder' => 'ASC', 'Title' => 'ASC']);
        return [
            'ID'                  => $this->ID,
            'Title'               => $this->Title,
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
            'IsPublic'      => (bool) $this->IsPublic,
            'TypeID'        => (int) $this->TypeID ?: null,
            'TypeTitle'     => $type->exists() ? $type->Title : null,
            'AgeGroups'     => array_map(fn ($group) => $group->toApi(), $ageGroups->toArray()),
            'ImageURL'      => $this->RenderImage(),
            'Prices'        => array_map(fn ($price) => $price->toApi(), $this->Prices()->toArray()),
        ];
    }

    /** Das Bild wird im Frontend quadratisch zugeschnitten hochgeladen (wie Organisations-Logos) */
    public function RenderImage(int $size = 400): ?string
    {
        if ($this->ImageID && $this->Image()->exists()) {
            return $this->Image()->Fill($size, $size)->getURL();
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

        if (array_key_exists('location', $data)) {
            $this->Location = trim((string) $data['location']) ?: null;
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

        if (array_key_exists('prices', $data)) {
            $prices = [];
            foreach ((array) $data['prices'] as $row) {
                $label = trim((string) ($row['title'] ?? ''));
                $price = $row['price'] ?? '';
                // Komplett leere Zeilen im Formular einfach ignorieren
                if ($label === '' && ($price === '' || $price === null)) {
                    continue;
                }
                if ($label === '') {
                    return 'Bitte gib für jeden Preis eine Bezeichnung an';
                }
                if (!is_numeric($price) || $price < 0 || $price > 99999999) {
                    return "Ungültiger Preis für „{$label}“";
                }
                $prices[] = ['Title' => $label, 'Price' => round((float) $price, 2)];
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
        if ($this->ImageID && $this->Image()->exists()) {
            $this->Image()->deleteFile();
            $this->Image()->delete();
        }
    }
}
