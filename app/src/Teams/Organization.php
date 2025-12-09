<?php

namespace App\Teams;

use App\Teams\Department;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Teams\Organization
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property bool $AllowsSelfJoining
 * @method \SilverStripe\ORM\DataList|\App\Teams\Department[] Departments()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Members()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Organization extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "AllowsSelfJoining" => "Boolean",
    ];

    private static $has_many = [
        "Departments" => Department::class,
    ];

    private static $many_many = [
        "Members" => Member::class,
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "AllowsSelfJoining" => "Erlaubt Selbstbeitritt",
        "Departments.Count" => "Anzahl Arbeits-Bereiche",
        "Members.Count" => "Anzahl Mitglieder",
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "AllowsSelfJoining" => "Erlaubt Selbstbeitritt",
        "Departments" => "Arbeits-Bereiche",
        "Members" => "Mitglieder",
    ];

    private static $table_name = 'Organization';
    private static $singular_name = "Organisation";
    private static $plural_name = "Organisationen";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
