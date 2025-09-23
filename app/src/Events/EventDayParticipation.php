<?php

namespace App\Events;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class EventDayParticipation extends DataObject
{
    private static $db = [
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
        "Notes" => "Varchar(512)"
    ];

    private static $has_one = [
        "Parent" => EventDay::class,
        "Member" => Member::class,
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Member.Title" => "Benutzer",
    ];

    private static $table_name = 'EventDayParticipation';
    private static $singular_name = "Teilnahme";
    private static $plural_name = "Teilnahmen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("Parent");
        return $fields;
    }

    public function RenderTime()
    {
        $start = $this->dbObject('TimeStart');
        $end = $this->dbObject('TimeEnd');
        if ($start && $end) {
            return $start->Format('hh:mm') . " - " . $end->Format('hh:mm');
        } elseif ($start) {
            return "ab " . $start->Format('hh:mm');
        } elseif ($end) {
            return "bis " . $end->Format('hh:mm');
        } else {
            return "Kein Datum";
        }
    }
}
