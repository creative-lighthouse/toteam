<?php

namespace App\HumanResources;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class FoodPreferences extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Icon" => Image::class,
    ];

    private static $owns = [
        'Icon',
    ];

    private static $belongs_many = [
        "Members" => Member::class,
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title"
    ];

    private static $table_name = 'FoodPreference';
    private static $singular_name = "Essenspräferenz";
    private static $plural_name = "Essenspräferenzen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
