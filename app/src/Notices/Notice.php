<?php

namespace App\Notices;

use App\Pages\NoticesPage;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class Notice extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "ShortText" => "Text",
        "LongText" => "HTMLText",
        "ReleaseDate" => "Datetime",
        "ExpiryDate" => "Datetime",
    ];

    private static $has_one = [
        "Author" => Member::class,
        "Category" => NoticeCategory::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Author.Name" => "Autor",
        "Category.Title" => "Kategorie",
        "Text" => "Text",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Author.Name" => "Autor",
        "Category.Title" => "Kategorie",
    ];

    private static $table_name = 'Notice';
    private static $singular_name = "Ankündigung";
    private static $plural_name = "Ankündigungen";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getLink()
    {
        $noticespage = NoticesPage::get()->first();
        if ($noticespage) {
            return $noticespage->Link('view/' . $this->ID);
        } else {
            return null;
        }
    }
}
