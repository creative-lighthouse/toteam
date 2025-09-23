<?php

namespace App\Events;

use Override;
use SilverStripe\ORM\DataObject;

class EventDayFood extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Time" => "Time"
    ];

    private static $has_one = [
        "Parent" => EventDay::class,
    ];

    private static $belongs_many = [
        "Participations" => EventDayParticipation::class,
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title" => "Titel",
        "RenderTime" => "Uhrzeit",
    ];

    private static $table_name = 'EventDayFood';
    private static $singular_name = "Mahlzeit";
    private static $plural_name = "Mahlzeiten";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');
        return $fields;
    }

    public function RenderTime()
    {
        return $this->dbObject('Time')->Format('HH:mm');
    }
}
