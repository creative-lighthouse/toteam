<?php

namespace App\Maps;

use App\Teams\Organization;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use App\Notifications\PushNotificationService;

class MapLayer extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "Active" => "Boolean",
        "CoordinatesUpperLeft" => "Varchar(100)",
        "CoordinatesLowerRight" => "Varchar(100)",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
        "Image" => Image::class,
    ];

    private static $owns = [
        'Image',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "Active" => "Aktiv",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Active.Nice" => "Aktiv",
    ];

    private static $table_name = 'MapLayer';
    private static $singular_name = "Lageplan-Ebene";
    private static $plural_name = "Lageplan-Ebenen";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
