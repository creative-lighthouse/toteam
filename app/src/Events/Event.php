<?php

namespace App\Events;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class Event extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Start" => "Datetime",
        "End" => "Datetime",
    ];

    private static $has_one = [
        "Image" => Image::class,
    ];

    private static $has_many = [
        "EventDays" => EventDay::class,
    ];

    private static $owns = [
        'Image',
        'EventDays',
    ];

    private static $field_labels = [
        "EventDays" => "Veranstaltungs-Tage",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "RenderStartDate" => "Start",
        "RenderEndDate" => "End"
    ];

    private static $table_name = 'Event';
    private static $singular_name = "Veranstaltung";
    private static $plural_name = "Veranstaltung";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function RenderStartDate()
    {
        return $this->dbObject('Start')->Format('dd.MM.YYYY H:mm');
    }

    public function RenderEndDate()
    {
        return $this->dbObject('End')->Format('dd.MM.YYYY H:mm');
    }
}
