<?php

namespace App\Teams;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

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
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Project extends DataObject implements PermissionProvider
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

    public function providePermissions()
    {
        return [
            "CREATE_PROJECTS" => [
                'name' => 'Projekte erstellen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Erstellen von Projekten.',
            ],
            "EDIT_PROJECTS" => [
                'name' => 'Projekte bearbeiten',
                'category' => 'Teams',
                'help' => 'Erlaubt das Erstellen, Bearbeiten und Löschen von Projekten.',
            ],
            "VIEW_PROJECTS" => [
                'name' => 'Projekte ansehen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Ansehen von Projekten.',
            ],
            "DELETE_PROJECTS" => [
                'name' => 'Projekte löschen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Löschen von Projekten.',
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_PROJECTS permission
        return Permission::checkMember($member, 'CREATE_PROJECTS');
    }

    public function canEdit($member = null, $context = [])
    {
        //Check user for EDIT_PROJECTS permission
        return Permission::checkMember($member, 'EDIT_PROJECTS');
    }

    public function canDelete($member = null, $context = [])
    {
        //Check user for DELETE_PROJECTS permission
        return Permission::checkMember($member, 'DELETE_PROJECTS');
    }

    public function canView($member = null, $context = [])
    {
        //Check user for VIEW_PROJECTS permission
        return Permission::checkMember($member, 'VIEW_PROJECTS');
    }
}
