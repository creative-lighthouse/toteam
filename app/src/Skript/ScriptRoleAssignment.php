<?php

namespace App\Skript;

use App\Teams\OrgEvent;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Skript\ScriptRoleAssignment
 *
 * Rollenzuteilung für ein Event: wer spielt welche Skript-Rolle an welchem Tag —
 * ganztägig (ohne Uhrzeit) oder für eine Zeitspanne an diesem Tag. Eine Rolle kann
 * pro Tag mehrere Zuteilungen haben (z.B. Schichten). Unabhängig von den
 * event-übergreifenden ScriptRole.Members, die Fokus-/Lernmodus nutzen.
 *
 * @property ?string $Date
 * @property ?string $TimeStart
 * @property ?string $TimeEnd
 * @property int $EventID
 * @property int $RoleID
 * @property int $MemberID
 * @method \App\Teams\OrgEvent Event()
 * @method \App\Skript\ScriptRole Role()
 * @method \SilverStripe\Security\Member Member()
 */
class ScriptRoleAssignment extends DataObject
{
    private static $db = [
        "Date"      => "Date",
        "TimeStart" => "Time",
        "TimeEnd"   => "Time",
    ];

    private static $has_one = [
        "Event"  => OrgEvent::class,
        "Role"   => ScriptRole::class,
        "Member" => Member::class,
    ];

    private static $default_sort = "Date ASC, TimeStart ASC";

    private static $field_labels = [
        "Date"      => "Tag",
        "TimeStart" => "Von",
        "TimeEnd"   => "Bis",
        "Event"     => "Event",
        "Role"      => "Rolle",
        "Member"    => "Mitglied",
    ];

    private static $table_name = 'ScriptRoleAssignment';
    private static $singular_name = "Rollenzuteilung";
    private static $plural_name = "Rollenzuteilungen";

    public function toApi(): array
    {
        $member = $this->Member();
        return [
            'ID'        => $this->ID,
            'RoleID'    => (int) $this->RoleID,
            'Date'      => $this->Date,
            'TimeStart' => $this->TimeStart ? substr($this->TimeStart, 0, 5) : null,
            'TimeEnd'   => $this->TimeEnd ? substr($this->TimeEnd, 0, 5) : null,
            'Member'    => $member && $member->exists() ? [
                'ID'     => $member->ID,
                'Name'   => $member->getDisplayName(),
                'Avatar' => $member->RenderProfileImage(),
            ] : null,
        ];
    }
}
