<?php

namespace App\Calendar;

use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Events\AppointmentParticipation
 *
 * @property ?string $TimeStart
 * @property ?string $TimeEnd
 * @property bool $CustomTimeframe
 * @property ?string $Type
 * @property ?string $Notes
 * @property int $ParentID
 * @property int $MemberID
 * @method \App\Calendar\Appointment Parent()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class AppointmentParticipation extends DataObject implements PermissionProvider
{
    private static $db = [
        "TimeStart"      => "Time",
        "TimeEnd"        => "Time",
        "CustomTimeframe" => "Boolean(0)",
        "Type"           => "Enum('Accept, Maybe, Decline')",
        "Notes"          => "Varchar(512)",
    ];

    private static $has_one = [
        "Parent" => Appointment::class,
        "Member" => Member::class,
    ];

    private static $field_labels = [
        "Member"          => "Benutzer",
        "TimeStart"       => "Von",
        "TimeEnd"         => "Bis",
        "CustomTimeframe" => "Eigene Uhrzeit",
        "Type"            => "Teilnahme",
        "Notes"           => "Notizen",
    ];

    private static $summary_fields = [
        "Member.Title" => "Benutzer",
        "RenderType" => "Teilnahme",
        "RenderTime" => "Zeit",
    ];

    private static $table_name = 'AppointmentParticipation';
    private static $singular_name = "Teilnahme";
    private static $plural_name = "Teilnahmen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ParentID");
        return $fields;
    }

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

    public function renderICSType()
    {
        switch ($this->Type) {
            case 'Accept':
                return 'ACCEPTED';
            case 'Maybe':
                return 'TENTATIVE';
            case 'Decline':
                return 'DECLINED';
            default:
                return 'NEEDS-ACTION';
        }
    }

    public function FormatTimeStart()
    {
        return $this->TimeStart ? (new \DateTime($this->TimeStart))->format('H:i') : '';
    }

    public function FormatTimeEnd()
    {
        return $this->TimeEnd ? (new \DateTime($this->TimeEnd))->format('H:i') : '';
    }

    public function RenderTime()
    {
        if (!$this->CustomTimeframe) {
            return 'Ganztägig';
        }
        if ($this->TimeStart && $this->TimeEnd) {
            return $this->FormatTimeStart() . " - " . $this->FormatTimeEnd();
        } elseif ($this->TimeStart) {
            return "ab " . $this->FormatTimeStart();
        } elseif ($this->TimeEnd) {
            return "bis " . $this->FormatTimeEnd();
        }
        return 'Ganztägig';
    }

    public function providePermissions()
    {
        return [
            'CREATE_APPOINTMENTPARTICIPATIONS' => [
                'name' => 'Teilnahmen erstellen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Erstellen von Teilnahmen'
            ],
            'EDIT_APPOINTMENTPARTICIPATIONS' => [
                'name' => 'Teilnahmen bearbeiten',
                'category' => 'Termine',
                'help' => 'Erlaubt das Bearbeiten von Teilnahmen'
            ],
            'VIEW_APPOINTMENTPARTICIPATIONS' => [
                'name' => 'Teilnahmen ansehen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Ansehen von Teilnahmen'
            ],
            'DELETE_APPOINTMENTPARTICIPATIONS' => [
                'name' => 'Teilnahmen löschen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Löschen von Teilnahmen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CREATE_APPOINTMENTPARTICIPATIONS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('EDIT_APPOINTMENTPARTICIPATIONS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('DELETE_APPOINTMENTPARTICIPATIONS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        return Permission::check('VIEW_APPOINTMENTPARTICIPATIONS', 'any', $member);
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
