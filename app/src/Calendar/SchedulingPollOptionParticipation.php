<?php

namespace App\Calendar;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Calendar\SchedulingPollOptionParticipation
 *
 * @property ?string $Type
 * @property int $ParentID
 * @property int $MemberID
 * @method \App\Calendar\SchedulingPollOption Parent()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class SchedulingPollOptionParticipation extends DataObject implements PermissionProvider
{
    private static $db = [
        "Type" => "Enum('Accept, Maybe, Decline')",
    ];

    private static $has_one = [
        "Parent" => SchedulingPollOption::class,
        "Member" => Member::class,
    ];

    private static $field_labels = [
        "Member" => "Benutzer",
        "Type"   => "Teilnahme",
    ];

    private static $summary_fields = [
        "Member.Title" => "Benutzer",
        "RenderType"   => "Teilnahme",
    ];

    private static $table_name = 'SchedulingPollOptionParticipation';
    private static $singular_name = "Terminoptions-Teilnahme";
    private static $plural_name = "Terminoptions-Teilnahmen";

    public function RenderType()
    {
        switch ($this->Type) {
            case 'Accept':
                return 'Zugesagt';
            case 'Maybe':
                return 'Vielleicht';
            case 'Decline':
                return 'Abgesagt';
            default:
                return 'Unbekannt';
        }
    }

    public function providePermissions()
    {
        return [
            'CREATE_SCHEDULINGPOLLOPTIONPARTICIPATIONS' => [
                'name' => 'Terminoptions-Teilnahmen erstellen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Erstellen von Terminoptions-Teilnahmen'
            ],
            'EDIT_SCHEDULINGPOLLOPTIONPARTICIPATIONS' => [
                'name' => 'Terminoptions-Teilnahmen bearbeiten',
                'category' => 'Termine',
                'help' => 'Erlaubt das Bearbeiten von Terminoptions-Teilnahmen'
            ],
            'VIEW_SCHEDULINGPOLLOPTIONPARTICIPATIONS' => [
                'name' => 'Terminoptions-Teilnahmen ansehen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Ansehen von Terminoptions-Teilnahmen'
            ],
            'DELETE_SCHEDULINGPOLLOPTIONPARTICIPATIONS' => [
                'name' => 'Terminoptions-Teilnahmen löschen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Löschen von Terminoptions-Teilnahmen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CREATE_SCHEDULINGPOLLOPTIONPARTICIPATIONS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('EDIT_SCHEDULINGPOLLOPTIONPARTICIPATIONS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('DELETE_SCHEDULINGPOLLOPTIONPARTICIPATIONS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        return Permission::check('VIEW_SCHEDULINGPOLLOPTIONPARTICIPATIONS', 'any', $member);
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $parent = $this->Parent();
        if ($parent && $parent->exists()) {
            $parent->write();
        }
    }

    public function onAfterDelete()
    {
        parent::onAfterDelete();
        $parent = $this->Parent();
        if ($parent && $parent->exists()) {
            $parent->write();
        }
    }
}
