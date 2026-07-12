<?php

namespace App\Calendar;

use App\Notifications\PendingNotificationJob;
use App\Teams\Organization;
use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Calendar\SchedulingPoll
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $Location
 * @property ?string $Status
 * @method \SilverStripe\ORM\DataList|\App\Calendar\SchedulingPollOption[] Options()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\Organization[] Organisations()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] InvitedMembers()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class SchedulingPoll extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar",
        "Description" => "Text",
        "Location" => "Varchar(511)",
        "Status" => "Enum('Open,Decided','Open')",
    ];

    private static $many_many = [
        'Organisations' => Organization::class,
        'InvitedMembers' => Member::class,
    ];

    private static $has_many = [
        'Options' => SchedulingPollOption::class,
    ];

    private static $owns = [
        'Options',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "Location" => "Ort",
        "Status" => "Status",
        "Options" => "Terminoptionen",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Status" => "Status",
    ];

    private static $table_name = 'SchedulingPoll';
    private static $singular_name = "Terminfindung";
    private static $plural_name = "Terminfindungen";

    #[Override]
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        $changedFields = $this->getChangedFields(false, 1);
        $isNew = isset($changedFields['ID']) && empty($changedFields['ID']['before']);

        if ($isNew) {
            PendingNotificationJob::create([
                'SourceClass' => self::class,
                'SourceID'    => $this->ID,
                'EventType'   => 'poll_created',
            ])->write();
        }
    }

    public function getLink()
    {
        return "/app/calendar?pollID=" . $this->ID;
    }

    public function providePermissions()
    {
        return [
            'CREATE_SCHEDULINGPOLLS' => [
                'name' => 'Terminfindungen erstellen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Erstellen von Terminfindungen'
            ],
            'EDIT_SCHEDULINGPOLLS' => [
                'name' => 'Terminfindungen bearbeiten',
                'category' => 'Termine',
                'help' => 'Erlaubt das Bearbeiten von Terminfindungen'
            ],
            'VIEW_SCHEDULINGPOLLS' => [
                'name' => 'Terminfindungen ansehen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Ansehen von Terminfindungen'
            ],
            'DELETE_SCHEDULINGPOLLS' => [
                'name' => 'Terminfindungen löschen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Löschen von Terminfindungen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CREATE_SCHEDULINGPOLLS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('EDIT_SCHEDULINGPOLLS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('DELETE_SCHEDULINGPOLLS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        return Permission::check('VIEW_SCHEDULINGPOLLS', 'any', $member);
    }
}
