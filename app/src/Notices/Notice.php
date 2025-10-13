<?php

namespace App\Notices;

use App\Pages\NoticesPage;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Notices\Notice
 *
 * @property ?string $Title
 * @property ?string $ShortText
 * @property ?string $LongText
 * @property ?string $ReleaseDate
 * @property ?string $ExpiryDate
 * @property int $AuthorID
 * @property int $CategoryID
 * @method \SilverStripe\Security\Member Author()
 * @method \App\Notices\NoticeCategory Category()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
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
