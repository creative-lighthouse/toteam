<?php

namespace App\Maps;

use App\Maps\MapLayer;
use SilverStripe\ORM\DataObject;

class MapPOI extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "Active" => "Boolean",
        "Position" => "Varchar(100)",
    ];

    private static $has_one = [
        "Parent" => MapLayer::class,
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

    private static $table_name = 'MapPOI';
    private static $singular_name = "POI";
    private static $plural_name = "POIs";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
