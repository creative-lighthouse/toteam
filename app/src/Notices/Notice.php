<?php

namespace App\Notices;

use SilverStripe\ORM\DataObject;
use App\Notices\NoticeReadStatus;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

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
 * @method \SilverStripe\ORM\DataList|\App\Notices\NoticeReadStatus[] ReadStatuses()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
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

    private static $has_many = [
        "ReadStatuses" => NoticeReadStatus::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "ShortText" => "Kurztext",
        "LongText" => "Langtext",
        "ReleaseDate" => "Veröffentlichungsdatum",
        "ExpiryDate" => "Ablaufdatum",
        "Author" => "Autor",
        "Category" => "Kategorie",
        "ReadStatuses" => "Gelesen-Stati",
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
        return '/notices/view/' . $this->ID;
    }

    public function IsNewForUser()
    {
        $currentUser = Security::getCurrentUser();
        if (!$currentUser) {
            return false;
        }

        $readStatus = $this->ReadStatuses()->filter('MemberID', $currentUser->ID);
        return $readStatus->count() === 0;
    }
}
