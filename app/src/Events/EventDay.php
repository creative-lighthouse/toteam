<?php

namespace App\Events;

use Override;
use App\Events\Event;
use App\Events\EventDayType;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use App\Events\EventDayParticipation;

class EventDay extends DataObject
{
    private static $db = [
        "Title" => "Varchar",
        "Date" => "Date",
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
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
        $fields->removeByName("Parent");
        return $fields;
    }

    public function RenderDate()
    {
        $date = $this->dbObject('Date');
        if ($date) {
            return $this->dbObject('Date')->Format('dd.MM.YYYY');
        } else {
            return "Kein Datum";
        }
    }
}
