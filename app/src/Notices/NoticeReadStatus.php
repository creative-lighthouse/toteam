<?php

namespace App\Notices;

use App\Notices\Notice;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;

/**
 * Class \App\Notices\NoticeReadStatus
 *
 * @property ?string $DateTime
 * @property int $ParentID
 * @property int $MemberID
 * @method \App\Notices\Notice Parent()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class NoticeReadStatus extends DataObject
{
    private static $db = [
        "DateTime" => "Datetime",
    ];

    private static $has_one = [
        "Parent" => Notice::class,
        "Member" => Member::class,
    ];

    private static $field_labels = [
        "DateTime" => "Gelesen am",
        "Member" => "Mitglied",
        "Parent" => "Mitteilung",
    ];

    private static $table_name = 'NoticeReadStatus';
    private static $singular_name = "Lesestatus";
    private static $plural_name = "Lesestatus";

    private static $summary_fields = [
        "Member.Name" => "Mitglied",
        "DateTime.Nice" => "Gelesen am",
    ];

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        return $fields;
    }
}
