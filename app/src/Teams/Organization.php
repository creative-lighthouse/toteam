<?php

namespace App\Teams;

use App\Teams\Department;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

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
class Organization extends DataObject implements PermissionProvider
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

    public function providePermissions()
    {
        return [
            'CREATE_ORGANIZATIONS' => [
                'name' => 'Organisationen erstellen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Erstellen, von Organisationen'
            ],
            'DELETE_ORGANIZATIONS' => [
                'name' => 'Organisationen löschen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Löschen, von Organisationen'
            ],
            'EDIT_ORGANIZATIONS' => [
                'name' => 'Organisationen bearbeiten',
                'category' => 'Teams',
                'help' => 'Erlaubt das Bearbeiten, von Organisationen'
            ],
            'VIEW_ORGANIZATIONS' => [
                'name' => 'Organisationen ansehen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Ansehen, von Organisationen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_ORGANIZATIONS permission
        return Permission::check('CREATE_ORGANIZATIONS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        //Check user for EDIT_ORGANIZATIONS permission
        return Permission::check('EDIT_ORGANIZATIONS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        //Check user for DELETE_ORGANIZATIONS permission
        return Permission::check('DELETE_ORGANIZATIONS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        //Check user for VIEW_ORGANIZATIONS permission
        return Permission::check('VIEW_ORGANIZATIONS', 'any', $member);
    }
}
