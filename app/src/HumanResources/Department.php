<?php

namespace App\HumanResources;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class Department extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
    ];

    private static $has_one = [
        "Image" => Image::class,
    ];

    private static $owns = [
        'Image',
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'Department';
    private static $singular_name = "Arbeits-Bereich";
    private static $plural_name = "Arbeits-Bereiche";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
