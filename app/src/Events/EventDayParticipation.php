<?php

namespace App\Events;

use SilverStripe\ORM\FieldType\DBField;
use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class EventDayParticipation extends DataObject
{
    private static $db = [
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
        "Type" => "Enum('Accept, Maybe, Decline')",
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
        $fields->removeByName("ParentID");
        return $fields;
    }

    public function RenderTime()
    {
        $start = $this->dbObject('TimeStart');
        $end = $this->dbObject('TimeEnd');
        if ($start && $end) {
            return $start->Format('hh:mm') . " - " . $end->Format('hh:mm');
        } elseif ($start instanceof DBField) {
            return "ab " . $start->Format('hh:mm');
        } elseif ($end instanceof DBField) {
            return "bis " . $end->Format('hh:mm');
        } else {
            return "Kein Datum";
        }
    }

    public function getDay() {
        return $this->Parent();
    }

    public function getEvent() {
        return $this->Parent()->getEvent();
    }
}
