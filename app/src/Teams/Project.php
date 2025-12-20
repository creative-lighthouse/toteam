<?php

namespace App\Teams;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Teams\Project
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property bool $AllowsSelfJoining
 * @property int $ParentID
 * @method \App\Teams\Department Parent()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Heads()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Members()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Project extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "AllowsSelfJoining" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => Department::class,
    ];

    private static $many_many = [
        "Heads" => Member::class,
        "Members" => Member::class,
    ];

    private static $owns = [
        "SubProjects",
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "AllowsSelfJoining" => "Erlaubt Selbstbeitritt",
        "Parent" => "Arbeits-Bereich",
        "Heads" => "Leiter",
        "Members" => "Mitglieder",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "AllowsSelfJoining" => "Erlaubt Selbstbeitritt",
        "Heads.Count" => "Anzahl Leiter",
        "Members.Count" => "Anzahl Mitglieder",
    ];

    private static $table_name = 'Project';
    private static $singular_name = "Projekt";
    private static $plural_name = "Projekte";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
