<?php

namespace App\Events;

use SilverStripe\ORM\DataObject;

/**
 * Class \App\Events\EventDayAgendaPoint
 *
 * @property ?string $Title
 * @property ?string $StartTime
 * @property ?string $EndTime
 * @property ?string $Description
 * @property int $ParentID
 * @method \App\Events\EventDay Parent()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class EventDayAgendaPoint extends DataObject
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
}
