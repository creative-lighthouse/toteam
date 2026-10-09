<?php

namespace App\Teams;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Class \App\Teams\OrgEventAgeGroup
 *
 * Altersgruppe, für die ein Event empfohlen wird (z.B. "Für Kinder", "Ab 16") —
 * im CMS unter "Kalender" gepflegt.
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\OrgEvent[] Events()
 */
class OrgEventAgeGroup extends DataObject
{
    private static $db = [
        "Title"     => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $belongs_many_many = [
        "Events" => OrgEvent::class . '.AgeGroups',
    ];

    private static $default_sort = "SortOrder ASC, Title ASC";

    private static $field_labels = [
        "Title" => "Titel",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'OrgEventAgeGroup';
    private static $singular_name = "Altersgruppe";
    private static $plural_name = "Altersgruppen";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['SortOrder', 'Events']);
        return $fields;
    }

    public function toApi(): array
    {
        return ['ID' => $this->ID, 'Title' => $this->Title];
    }

    public function canView($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_App\Admins\CalendarAdmin', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_App\Admins\CalendarAdmin', 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_App\Admins\CalendarAdmin', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_App\Admins\CalendarAdmin', 'any', $member);
    }
}
