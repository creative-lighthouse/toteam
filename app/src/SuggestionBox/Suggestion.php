<?php

namespace App\SuggestionBox;

use Override;
use App\Food\Food;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class Suggestion extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "HasRecipient" => "Boolean",
        "SeenByRecipient" => "Boolean",
        "IsAnonymous" => "Boolean",
    ];

    private static $has_one = [
        "Recipient" => Member::class,
        "Sender" => Member::class,
    ];

    private static $owns = [
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title"
    ];

    private static $table_name = 'Suggestion';
    private static $singular_name = "Kritik/Vorschlag";
    private static $plural_name = "Kritik/Vorschläge";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
