<?php

namespace App\Links;

use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Links\TeamLinkType
 *
 * @property ?string $Title
 * @property ?string $Description
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class TeamLinkType extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title"
    ];

    private static $default_sort = 'Title ASC';

    private static $table_name = 'TeamLinkType';
    private static $singular_name = "Link-Typ";
    private static $plural_name = "Link-Typen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_LINKTYPES' => [
                'name' => 'Link-Typen erstellen',
                'category' => 'Links',
                'help' => 'Erlaubt das Erstellen, von Link-Typen'
            ],
            'EDIT_LINKTYPES' => [
                'name' => 'Link-Typen bearbeiten',
                'category' => 'Links',
                'help' => 'Erlaubt das Bearbeiten von Link-Typen'
            ],
            'VIEW_LINKTYPES' => [
                'name' => 'Link-Typen ansehen',
                'category' => 'Links',
                'help' => 'Erlaubt das Ansehen von Link-Typen'
            ],
            'DELETE_LINKTYPES' => [
                'name' => 'Link-Typen löschen',
                'category' => 'Links',
                'help' => 'Erlaubt das Löschen von Link-Typen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_LINKTYPES');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_LINKTYPES');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_LINKTYPES');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_LINKTYPES');
    }
}
