<?php

namespace App\Notices;

use App\Teams\Organization;
use SilverStripe\ORM\DataObject;
use App\Notices\NoticeReadStatus;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use App\Notifications\PushNotificationService;

/**
 * Class \App\Notices\Notice
 *
 * @property ?string $Title
 * @property ?string $ShortText
 * @property ?string $LongText
 * @property ?string $ReleaseDate
 * @property ?string $ExpiryDate
 * @property int $ParentID
 * @property int $AuthorID
 * @property int $CategoryID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\Security\Member Author()
 * @method \App\Notices\NoticeCategory Category()
 * @method \SilverStripe\ORM\DataList|\App\Notices\NoticeReadStatus[] ReadStatuses()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Notice extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "ShortText" => "Text",
        "LongText" => "HTMLText",
        "ReleaseDate" => "Datetime",
        "ExpiryDate" => "Datetime",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
        "Author" => Member::class,
        "Category" => NoticeCategory::class,
    ];

    private static $has_many = [
        "ReadStatuses" => NoticeReadStatus::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "ShortText" => "Kurztext",
        "LongText" => "Langtext",
        "ReleaseDate" => "Veröffentlichungsdatum",
        "ExpiryDate" => "Ablaufdatum",
        "Author" => "Autor",
        "Category" => "Kategorie",
        "ReadStatuses" => "Gelesen-Stati",
        "Parent" => "Organisation",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Author.Name" => "Autor",
        "Category.Title" => "Kategorie",
    ];

    private static $table_name = 'Notice';
    private static $singular_name = "Ankündigung";
    private static $plural_name = "Ankündigungen";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getLink()
    {
        return '/notices/view/' . $this->ID;
    }

    public function IsNewForUser()
    {
        $currentUser = Security::getCurrentUser();
        if (!$currentUser) {
            return false;
        }

        $readStatus = $this->ReadStatuses()->filter('MemberID', $currentUser->ID);
        return $readStatus->count() === 0;
    }

    /**
     * Send push notification for new notices
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Only send notification for newly created notices
        $changedFields = $this->getChangedFields(false, 1);
        $isNew = isset($changedFields['ID']) && empty($changedFields['ID']['before']);

        if ($isNew) {
            PushNotificationService::notifyNewNotice($this);
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
