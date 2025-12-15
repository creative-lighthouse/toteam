<?php

namespace App\Events;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Events\EventDayAgendaPoint
 *
 * @property ?string $Title
 * @property ?string $StartTime
 * @property ?string $EndTime
 * @property ?string $Description
 * @property int $ParentID
 * @method \App\Events\EventDay Parent()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class EventDayAgendaPoint extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "StartTime" => "Time",
        "EndTime" => "Time",
        "Description" => "Text",
    ];

    private static $has_one = [
        "Parent" => EventDay::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "StartTime" => "Startzeit",
        "EndTime" => "Endzeit",
        "Description" => "Beschreibung",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "StartTime" => "Startzeit",
        "EndTime" => "Endzeit",
    ];

    private static $table_name = 'EventDayAgendaPoint';
    private static $singular_name = "Tagesordnungspunkt";
    private static $plural_name = "Tagesordnungspunkte";

    public function RenderTime()
    {
        if ($this->StartTime && $this->StartTime != "00:00:00" && $this->EndTime && $this->EndTime != "00:00:00") {
            return $this->dbObject('StartTime')->Format('H:mm') . " - " . $this->dbObject('EndTime')->Format('H:mm');
        } elseif ($this->StartTime && $this->StartTime != "00:00:00") {
            return "Ab " . $this->dbObject('StartTime')->Format('H:mm');
        } elseif ($this->EndTime && $this->EndTime != "00:00:00") {
            return "Bis " . $this->dbObject('EndTime')->Format('H:mm');
        } else {
            return "Ganztägig";
        }
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ParentID");
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_AGENDA_POINTS' => [
                'name' => 'Tagesordnungspunkte erstellen',
                'category' => 'Tagesordnungspunkte',
                'help' => 'Erlaubt das Erstellen, von Tagesordnungspunkten'
            ],
            'EDIT_AGENDA_POINTS' => [
                'name' => 'Tagesordnungspunkte verwalten',
                'category' => 'Tagesordnungspunkte',
                'help' => 'Erlaubt das Erstellen, Bearbeiten und Löschen von Tagesordnungspunkten'
            ],
            'VIEW_AGENDA_POINTS' => [
                'name' => 'Tagesordnungspunkte ansehen',
                'category' => 'Tagesordnungspunkte',
                'help' => 'Erlaubt das Ansehen von Tagesordnungspunkten'
            ],
            'DELETE_AGENDA_POINTS' => [
                'name' => 'Tagesordnungspunkte löschen',
                'category' => 'Tagesordnungspunkte',
                'help' => 'Erlaubt das Löschen von Tagesordnungspunkten'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_AGENDA_POINTS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_AGENDA_POINTS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_AGENDA_POINTS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_AGENDA_POINTS');
    }
}
