<?php

namespace App\Notices;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Notices\NoticeCategory
 *
 * @property ?string $Title
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class NoticeCategory extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
    ];

    private static $belongs_many = [
        'Notices' => Notice::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'NoticeCategory';
    private static $singular_name = "Ankündigungskategorie";
    private static $plural_name = "Ankündigungskategorien";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_NOTICECATEGORIES' => [
                'name' => 'Ankündigungskategorien erstellen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Erstellen, von Ankündigungskategorien'
            ],
            'EDIT_NOTICECATEGORIES' => [
                'name' => 'Ankündigungskategorien bearbeiten',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Bearbeiten von Ankündigungskategorien'
            ],
            'VIEW_NOTICECATEGORIES' => [
                'name' => 'Ankündigungskategorien ansehen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Ansehen von Ankündigungskategorien'
            ],
            'DELETE_NOTICECATEGORIES' => [
                'name' => 'Ankündigungskategorien löschen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Löschen von Ankündigungskategorien'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_NOTICECATEGORIES');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_NOTICECATEGORIES');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_NOTICECATEGORIES');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_NOTICECATEGORIES');
    }
}
