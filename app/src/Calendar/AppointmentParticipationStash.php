<?php

namespace App\Calendar;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Calendar\AppointmentParticipationStash
 *
 * Zurückgelegte Angaben einer Teilnahme: Setzt jemand seine Antwort auf "Keine
 * Antwort" zurück, wird die Teilnahme gelöscht (sie zählt überall als Rückmeldung).
 * Notiz, eigener Zeitraum und Mitfahrgelegenheit landen dann hier und kommen bei
 * der nächsten Antwort wieder in die neue Teilnahme — so geht z.B. ein langer
 * Hinweis nicht verloren, wenn man versehentlich die eigene Zusage abwählt.
 * Angezeigt wird ein Stash nirgends. Höchstens einer je Termin und Mitglied.
 *
 * @property ?string $TimeStart
 * @property ?string $TimeEnd
 * @property bool $CustomTimeframe
 * @property ?string $Notes
 * @property ?string $RideType
 * @property int $RideSeats
 * @property int $ParentID
 * @property int $MemberID
 * @method \App\Calendar\Appointment Parent()
 * @method \SilverStripe\Security\Member Member()
 */
class AppointmentParticipationStash extends DataObject
{
    /** Die Felder, die zwischen Teilnahme und Stash wandern */
    public const FIELDS = ['TimeStart', 'TimeEnd', 'CustomTimeframe', 'Notes', 'RideType', 'RideSeats'];

    private static $db = [
        "TimeStart"       => "Time",
        "TimeEnd"         => "Time",
        "CustomTimeframe" => "Boolean(0)",
        "Notes"           => "Varchar(512)",
        "RideType"        => "Enum('None,Need,Offer','None')",
        "RideSeats"       => "Int",
    ];

    private static $has_one = [
        "Parent" => Appointment::class,
        "Member" => Member::class,
    ];

    private static $indexes = [
        "ParentMember" => [
            "type"    => "unique",
            "columns" => ["ParentID", "MemberID"],
        ],
    ];

    private static $table_name = 'AppointmentParticipationStash';
    private static $singular_name = "Zurückgelegte Teilnahme-Angaben";
    private static $plural_name = "Zurückgelegte Teilnahme-Angaben";

    /** Gibt es etwas, das sich aufzuheben lohnt? */
    public static function hasDetails(AppointmentParticipation $participation): bool
    {
        return trim((string) $participation->Notes) !== ''
            || $participation->CustomTimeframe
            || ($participation->RideType && $participation->RideType !== 'None');
    }

    /** Legt die Angaben der (gleich gelöschten) Teilnahme zurück */
    public static function stash(AppointmentParticipation $participation): void
    {
        $filter = ['ParentID' => $participation->ParentID, 'MemberID' => $participation->MemberID];
        $existing = self::get()->filter($filter)->first();
        if (!self::hasDetails($participation)) {
            $existing?->delete();
            return;
        }
        $stash = $existing ?? self::create($filter);
        foreach (self::FIELDS as $field) {
            $stash->$field = $participation->$field;
        }
        $stash->write();
    }

    /** Übernimmt zurückgelegte Angaben in eine neue (noch nicht geschriebene) Teilnahme */
    public static function restoreInto(AppointmentParticipation $participation): void
    {
        $stash = self::get()->filter([
            'ParentID' => $participation->ParentID,
            'MemberID' => $participation->MemberID,
        ])->first();
        if (!$stash) {
            return;
        }
        foreach (self::FIELDS as $field) {
            $participation->$field = $stash->$field;
        }
        $stash->delete();
    }
}
