<?php

namespace App\Links;

use Override;
use App\Teams\Organization;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Links\TeamLink
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @property string $LinkKind
 * @property ?string $ExternalUrl
 * @property bool $OpenInNew
 * @property int $ParentID
 * @property int $FileID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\Assets\File File()
 */
class TeamLink extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "SortOrder" => "Int",
        "LinkKind" => "Enum('external,file', 'external')",
        "ExternalUrl" => "Varchar(255)",
        "OpenInNew" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
        "File" => File::class,
    ];

    private static $owns = [
        'File',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "ExternalUrl" => "Link",
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
            'CREATE_LINKS' => [
                'name' => 'Links erstellen',
                'category' => 'Links',
                'help' => 'Erlaubt das Erstellen, von Links'
            ],
            'EDIT_LINKS' => [
                'name' => 'Links bearbeiten',
                'category' => 'Links',
                'help' => 'Erlaubt das Bearbeiten von Links'
            ],
            'VIEW_LINKS' => [
                'name' => 'Links ansehen',
                'category' => 'Links',
                'help' => 'Erlaubt das Ansehen von Links'
            ],
            'DELETE_LINKS' => [
                'name' => 'Links löschen',
                'category' => 'Links',
                'help' => 'Erlaubt das Löschen von Links'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_LINKS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_LINKS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_LINKS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_LINKS');
    }
}
