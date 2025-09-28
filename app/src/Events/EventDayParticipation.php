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

    private static $field_labels = [
        "Member" => "Benutzer",
        "TimeStart" => "Von",
        "TimeEnd" => "Bis",
        "Type" => "Teilnahme",
        "Notes" => "Notizen",
    ];

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

    public function getDay() {
        return $this->Parent();
    }

    public function getEvent() {
        return $this->Parent()->getEvent();
    }

    public function RenderType() {
        switch ($this->Type) {
            case 'Accept':
                return 'Zugesagt';
            case 'Maybe':
                return 'Vielleicht';
            case 'Decline':
                return 'Abgesagt';
            default:
                return 'Unbekannt';
        }
    }

    public function FormatTimeStart() {
        return $this->TimeStart ? (new \DateTime($this->TimeStart))->format('H:i') : '';
    }

    public function FormatTimeEnd() {
        return $this->TimeEnd ? (new \DateTime($this->TimeEnd))->format('H:i') : '';
    }

    public function RenderTime()
    {
        if($this->TimeStart && $this->TimeEnd) {
            return $this->FormatTimeStart() . " - " . $this->FormatTimeEnd();
        } elseif ($this->TimeStart) {
            return "ab " . $this->FormatTimeStart();
        } elseif ($this->TimeEnd) {
            return "bis " . $this->FormatTimeEnd();
        } else {
            return "Kein Datum";
        }
    }
}
