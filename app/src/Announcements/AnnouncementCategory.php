<?php

namespace App\Announcements;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Announcements\AnnouncementCategory
 *
 * @property ?string $Title
 * @method \SilverStripe\ORM\ManyManyList|\App\Announcements\Announcement[] Announcements()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class AnnouncementCategory extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
    ];

    private static $belongs_many_many = [
        'Announcements' => Announcement::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    // Keep existing table name to avoid DB migration
    private static $table_name = 'NoticeCategory';
    private static $singular_name = "Ankündigungskategorie";
    private static $plural_name = "Ankündigungskategorien";

    public function getCMSFields()
    {
        return parent::getCMSFields();
    }

    public function providePermissions()
    {
        return [
            'CREATE_ANNOUNCEMENTCATEGORIES' => [
                'name' => 'Ankündigungskategorien erstellen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Erstellen von Ankündigungskategorien'
            ],
            'EDIT_ANNOUNCEMENTCATEGORIES' => [
                'name' => 'Ankündigungskategorien bearbeiten',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Bearbeiten von Ankündigungskategorien'
            ],
            'VIEW_ANNOUNCEMENTCATEGORIES' => [
                'name' => 'Ankündigungskategorien ansehen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Ansehen von Ankündigungskategorien'
            ],
            'DELETE_ANNOUNCEMENTCATEGORIES' => [
                'name' => 'Ankündigungskategorien löschen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Löschen von Ankündigungskategorien'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_ANNOUNCEMENTCATEGORIES');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_ANNOUNCEMENTCATEGORIES');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_ANNOUNCEMENTCATEGORIES');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_ANNOUNCEMENTCATEGORIES');
    }
}
