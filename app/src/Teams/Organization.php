<?php

namespace App\Teams;

use App\Teams\OrganizationMembership;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Teams\Organization
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $JoinMode
 * @property int $LogoID
 * @property int $CoverImageID
 * @method \SilverStripe\Assets\Image Logo()
 * @method \SilverStripe\Assets\Image CoverImage()
 * @method \SilverStripe\ORM\DataList|\App\Teams\OrganizationMembership[] Memberships()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Organization extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title"       => "Varchar(255)",
        "Description" => "Text",
        "JoinMode"    => "Enum('open,application,invite_only,hidden','invite_only')",
    ];

    private static $has_one = [
        "Logo"       => Image::class,
        "CoverImage" => Image::class,
    ];

    private static $owns = [
        "Logo",
        "CoverImage",
    ];

    private static $has_many = [
        "Memberships" => OrganizationMembership::class,
    ];

    private static $summary_fields = [
        "Title"            => "Titel",
        "Description"      => "Beschreibung",
        "JoinMode"         => "Beitrittsmodus",
        "Memberships.Count" => "Anzahl Mitglieder",
    ];

    private static $field_labels = [
        "Title"       => "Titel",
        "Description" => "Beschreibung",
        "JoinMode"    => "Beitrittsmodus",
        "CoverImage"  => "Coverbild",
        "Memberships" => "Mitgliedschaften",
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
            'DELETE_ORGANIZATIONS' => [
                'name' => 'Organisationen löschen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Löschen, von Organisationen'
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
