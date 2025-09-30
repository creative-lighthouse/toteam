<?php

namespace App\Notices;

use SilverStripe\ORM\DataObject;

class NoticeCategory extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
    ];

    private static $belongs_many = [
        'Notices' => Notice::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'NoticeCategory';
    private static $singular_name = "Ankündigungskategorie";
    private static $plural_name = "Ankündigungskategorien";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
