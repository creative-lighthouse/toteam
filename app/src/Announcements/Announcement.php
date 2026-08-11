<?php

namespace App\Announcements;

use App\Notifications\PendingNotificationJob;
use App\Teams\Organization;
use Override;
use SilverStripe\Forms\SearchableMultiDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Announcements\Announcement
 *
 * @property ?string $Title
 * @property ?string $ShortText
 * @property ?string $LongText
 * @property ?string $ReleaseDate
 * @property ?string $ExpiryDate
 * @property int $AuthorID
 * @property int $CategoryID
 * @method \SilverStripe\Security\Member Author()
 * @method \App\Announcements\AnnouncementCategory Category()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\Organization[] Organisations()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Announcement extends DataObject implements PermissionProvider
{
    private bool $notifyAfterWrite = false;

    private static $db = [
        "Title" => "Varchar(255)",
        "ShortText" => "Text",
        "LongText" => "HTMLText",
        "ReleaseDate" => "Datetime",
        "ExpiryDate" => "Datetime",
    ];

    private static $has_one = [
        "Author" => Member::class,
        "Category" => AnnouncementCategory::class,
    ];

    private static $many_many = [
        "Organisations" => Organization::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "ShortText" => "Kurztext",
        "LongText" => "Langtext",
        "ReleaseDate" => "Veröffentlichungsdatum",
        "ExpiryDate" => "Ablaufdatum",
        "Author" => "Autor",
        "Category" => "Kategorie",
        "Organisations" => "Organisationen",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Author.Name" => "Autor",
        "Category.Title" => "Kategorie",
    ];

    // Keep existing table names to avoid DB migration
    private static $table_name = 'Announcement';
    private static $singular_name = "Ankündigung";
    private static $plural_name = "Ankündigungen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('Organisations');
        $orgField = SearchableMultiDropdownField::create(
            'Organisations',
            'Organisationen',
            Organization::get()
        )->setIsLazyLoaded(true);
        $fields->addFieldToTab('Root.Main', $orgField);

        return $fields;
    }

    public function getLink(): string
    {
        return '/app/announcements';
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->notifyAfterWrite = !$this->isInDB();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if ($this->notifyAfterWrite) {
            $this->notifyAfterWrite = false;
            PendingNotificationJob::create([
                'SourceClass' => self::class,
                'SourceID'    => $this->ID,
                'EventType'   => 'new_announcement',
            ])->write();
        }
    }

    public function providePermissions()
    {
        return [
            'CREATE_ANNOUNCEMENTS' => [
                'name' => 'Ankündigungen erstellen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Erstellen von Ankündigungen'
            ],
            'EDIT_ANNOUNCEMENTS' => [
                'name' => 'Ankündigungen bearbeiten',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Bearbeiten von Ankündigungen'
            ],
            'VIEW_ANNOUNCEMENTS' => [
                'name' => 'Ankündigungen ansehen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Ansehen von Ankündigungen'
            ],
            'DELETE_ANNOUNCEMENTS' => [
                'name' => 'Ankündigungen löschen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Löschen von Ankündigungen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_ANNOUNCEMENTS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_ANNOUNCEMENTS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_ANNOUNCEMENTS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_ANNOUNCEMENTS');
    }
}
