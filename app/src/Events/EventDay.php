<?php

namespace App\Events;

use DateTime;
use Override;
use App\Events\Event;
use App\Events\EventDayType;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use App\Events\EventDayParticipation;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Security;

class EventDay extends DataObject
{
    private static $db = [
        "Title" => "Varchar",
        "Date" => "Date",
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
        "Location" => "Varchar(255)",
    ];

    private static $has_one = [
        'Parent' => Event::class,
        "Image" => Image::class,
        "Type" => EventDayType::class,
    ];

    private static $has_many = [
        'Participations' => EventDayParticipation::class,
        'Foods' => EventDayFood::class,
    ];

    private static $owns = [
        'Image',
        "Participations",
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Date" => "Datum",
        "TimeStart" => "Beginn",
        "TimeEnd" => "Ende",
        "Type" => "Tages-Typ",
        "Image" => "Bild",
        "Participations" => "Teilnahmen",
        "Foods" => "Mahlzeiten",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "RenderDate" => "Tag",
    ];

    private static $table_name = 'EventDay';
    private static $singular_name = "Veranstaltungs-Tag";
    private static $plural_name = "Veranstaltungs-Tage";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ParentID");
        return $fields;
    }

    public function RenderDate()
    {
        $date = $this->dbObject('Date');
        if ($date instanceof DBField) {
            return $this->dbObject('Date')->Format('dd.MM.YYYY');
        } else {
            return "Kein Datum";
        }
    }

    public function getEvent() {
        return $this->Parent();
    }

    public function jsonSerialize(): mixed
    {
        $backgroundColor = "#eee";
        switch ($this->getParticipationType()) {
            case 'Accept':
                $backgroundColor = "rgba(111, 206, 111, 1)";
                break;
            case 'Maybe':
                $backgroundColor = "rgba(255, 206, 111, 1)";
                break;
            case 'Deny':
                $backgroundColor = "rgba(255, 111, 111, 1)";
                break;
            default:
                // no suffix
                break;
        }

        return [
            "id" => $this->ID,
            "title" => $this->Title,
            "color" => "#000",
            "backgroundColor" => $backgroundColor,
            "attendees" => $this->getAttendees() ?? [],
            "body" => array($this->CreateBodyString()),
            "start" => $this->Date . ' ' . $this->TimeStart,
            "end" => $this->Date . ' ' . $this->TimeEnd,
            "isReadOnly" => true,
            "state" => false,
        ];
    }

    public function getParticipationType()
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return null;
        }
        $participation = $this->Participations()->filter('MemberID', $member->ID)->first();
        return $participation ? $participation->Type : null;
    }

    public function getAttendees() {
        $participations = $this->Participations()->filter('Type', 'Accept');
        $attendees = [];
        foreach ($participations as $participation) {
            if ($participation->Member() && $participation->Member()->Title) {
                $attendees[] = $participation->Member()->Title;
            }
        }
        return $attendees;
    }

    public function CreateBodyString() {
        $acceptLink = "./accept";
        $maybeLink = "./maybe";
        $denyLink = "./deny";

        $itemList = "<div>" . $this->Description;
        $itemList .= "<br><br>";
        $itemList .= "<a href=".$acceptLink." class='button button--tertiary'> Dabei </a>";
        $itemList .= "<a href=".$maybeLink." class='button button--tertiary'> Vielleicht </a>";
        $itemList .= "<a href=".$denyLink." class='button button--tertiary'> Nicht dabei </a>";
        $itemList .= "</div>";

        return $itemList;
    }
}
