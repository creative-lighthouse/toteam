<?php

namespace App\Teams;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Teams\OrgEventInterest
 *
 * Markierung eines Events durch ein Mitglied: "Interessiert" oder "Ich bin dabei"
 * (Going) — höchstens eine pro Mitglied und Event. Damit erscheint das Event auf dem
 * Dashboard ("Deine anstehenden Events") und im Profil. Markieren kann jede
 * angemeldete Person, die das Event sehen darf (bei öffentlichen Events also auch
 * Mitglieder anderer Organisationen).
 *
 * @property ?string $Type
 * @property int $EventID
 * @property int $MemberID
 * @method \App\Teams\OrgEvent Event()
 * @method \SilverStripe\Security\Member Member()
 */
class OrgEventInterest extends DataObject
{
    public const TYPE_INTERESTED = 'Interested';
    public const TYPE_GOING = 'Going';

    private static $db = [
        "Type" => "Enum('Interested,Going','Interested')",
    ];

    private static $has_one = [
        "Event"  => OrgEvent::class,
        "Member" => Member::class,
    ];

    private static $indexes = [
        "EventMember" => [
            "type"    => "unique",
            "columns" => ["EventID", "MemberID"],
        ],
    ];

    private static $field_labels = [
        "Type"   => "Art",
        "Event"  => "Event",
        "Member" => "Mitglied",
    ];

    private static $table_name = 'OrgEventInterest';
    private static $singular_name = "Event-Interesse";
    private static $plural_name = "Event-Interessen";
}
