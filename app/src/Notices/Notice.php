<?php

namespace App\Notices;

use App\Notifications\PendingNotificationJob;
use App\Teams\Organization;
use Override;
use SilverStripe\Forms\SearchableMultiDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Notices\Notice
 *
 * @property ?string $Title
 * @property ?string $ShortText
 * @property ?string $LongText
 * @property ?string $ReleaseDate
 * @property ?string $ExpiryDate
 * @property int $AuthorID
 * @property int $CategoryID
 * @method \SilverStripe\Security\Member Author()
 * @method \App\Notices\NoticeCategory Category()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\Organization[] Organisations()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Notice extends DataObject implements PermissionProvider
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
        "Category" => NoticeCategory::class,
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

    private static $table_name = 'Notice';
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
        return '/app/notices';
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
                'EventType'   => 'new_notice',
            ])->write();
        }
    }

    public function providePermissions()
    {
        return [
            'CREATE_NOTICES' => [
                'name' => 'Ankündigungen erstellen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Erstellen, von Ankündigungen'
            ],
            'EDIT_NOTICES' => [
                'name' => 'Ankündigungen bearbeiten',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Bearbeiten von Ankündigungen'
            ],
            'VIEW_NOTICES' => [
                'name' => 'Ankündigungen ansehen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Ansehen von Ankündigungen'
            ],
            'DELETE_NOTICES' => [
                'name' => 'Ankündigungen löschen',
                'category' => 'Ankündigungen',
                'help' => 'Erlaubt das Löschen von Ankündigungen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_NOTICES');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_NOTICES');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_NOTICES');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_NOTICES');
    }
}
