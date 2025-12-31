<?php

namespace App\Teams;

use Override;
use App\Teams\Organization;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

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
class Department extends DataObject implements PermissionProvider
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

    public function providePermissions()
    {
        return [
            'CREATE_DEPARTMENTS' => [
                'name' => 'Arbeits-Bereiche erstellen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Erstellen, von Arbeits-Bereichen'
            ],
            'EDIT_DEPARTMENTS' => [
                'name' => 'Arbeits-Bereiche bearbeiten',
                'category' => 'Teams',
                'help' => 'Erlaubt das Bearbeiten von Arbeits-Bereichen'
            ],
            'VIEW_DEPARTMENTS' => [
                'name' => 'Arbeits-Bereiche ansehen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Ansehen von Arbeits-Bereichen'
            ],
            'DELETE_DEPARTMENTS' => [
                'name' => 'Arbeits-Bereiche löschen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Löschen von Arbeits-Bereichen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_DEPARTMENTS permission
        return Permission::check('CREATE_DEPARTMENTS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        //Check user for EDIT_DEPARTMENTS permission
        return Permission::check('EDIT_DEPARTMENTS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        //Check user for DELETE_DEPARTMENTS permission
        return Permission::check('DELETE_DEPARTMENTS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        //Check user for VIEW_DEPARTMENTS permission
        return Permission::check('VIEW_DEPARTMENTS', 'any', $member);
    }
}
