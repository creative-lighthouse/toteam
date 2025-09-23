<?php

namespace App\Events;

use Override;
use App\Events\EventDay;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class EventDayType extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "PluralTitle" => "Varchar(255)",
    ];

    private static $has_one = [
        "Icon" => Image::class,
    ];

    private static $owns = [
        'Icon',
    ];

    private static $belongs_many = [
        'EventDays' => EventDay::class,
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "SingularTitle"
    ];

    private static $table_name = 'EventDayType';
    private static $singular_name = "Tagestyp";
    private static $plural_name = "Tagestypen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function RenderStartDate()
    {
        return $this->dbObject('Start')->Format('d.m.Y H:i');
    }

    public function RenderEndDate()
    {
        return $this->dbObject('End')->Format('d.m.Y H:i');
    }
}
