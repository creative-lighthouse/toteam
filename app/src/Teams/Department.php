<?php

namespace App\Teams;

use Override;
use App\Teams\Organization;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Teams\Department
 *
 * @property ?string $Title
 * @property bool $AllowsSelfJoining
 * @property int $ParentID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Members()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Department extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "AllowsSelfJoining" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
    ];

    private static $many_many = [
        "Members" => Member::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "AllowsSelfJoining" => "Erlaubt Selbstbeitritt",
        "Parent" => "Organisation",
        "Members" => "Mitglieder",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "AllowsSelfJoining" => "Erlaubt Selbstbeitritt",
        "Members.Count" => "Anzahl Mitglieder",
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
