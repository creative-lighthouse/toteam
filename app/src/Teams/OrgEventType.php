<?php

namespace App\Teams;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Class \App\Teams\OrgEventType
 *
 * Art eines Events (z.B. Fest, Aufführung, Sonstiges) — im CMS unter "Kalender" gepflegt.
 *
 * @property ?string $Title
 * @property int $SortOrder
 */
class OrgEventType extends DataObject
{
    private static $db = [
        "Title"     => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $default_sort = "SortOrder ASC, Title ASC";

    private static $field_labels = [
        "Title" => "Titel",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'OrgEventType';
    private static $singular_name = "Event-Art";
    private static $plural_name = "Event-Arten";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('SortOrder');
        return $fields;
    }

    /** Legt beim ersten dev/build ein paar Standard-Arten an */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if (self::get()->exists()) {
            return;
        }
        foreach (['Fest', 'Aufführung', 'Sonstiges'] as $sort => $title) {
            self::create(['Title' => $title, 'SortOrder' => $sort])->write();
        }
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
