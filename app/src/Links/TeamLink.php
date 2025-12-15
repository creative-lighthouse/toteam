<?php

namespace App\Links;

use Override;
use App\Teams\Department;
use App\Teams\Organization;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Links\TeamLink
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @property int $ParentID
 * @property int $ButtonID
 * @property int $TypeID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\LinkField\Models\Link Button()
 * @method \App\Links\TeamLinkType Type()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class TeamLink extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
        "Button" => Link::class,
        "Type" => TeamLinkType::class,
    ];

    private static $owns = [
        'Button',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Button" => "Link",
        "Type" => "Link-Typ",
        "SortOrder" => "Sortierreihenfolge",
        "Parent" => "Organisation",
    ];

    private static $summary_fields = [
        "Title"
    ];

    private static $default_sort = 'SortOrder ASC';

    private static $table_name = 'TeamLink';
    private static $singular_name = "Link";
    private static $plural_name = "Links";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('SortOrder');
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_TEAMLINKS' => [
                'name' => 'Links erstellen',
                'category' => 'Links',
                'help' => 'Erlaubt das Erstellen, von Links'
            ],
            'EDIT_TEAMLINKS' => [
                'name' => 'Links bearbeiten',
                'category' => 'Links',
                'help' => 'Erlaubt das Bearbeiten von Links'
            ],
            'VIEW_TEAMLINKS' => [
                'name' => 'Links ansehen',
                'category' => 'Links',
                'help' => 'Erlaubt das Ansehen von Links'
            ],
            'DELETE_TEAMLINKS' => [
                'name' => 'Links löschen',
                'category' => 'Links',
                'help' => 'Erlaubt das Löschen von Links'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_TEAMLINKS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_TEAMLINKS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_TEAMLINKS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_TEAMLINKS');
    }
}
