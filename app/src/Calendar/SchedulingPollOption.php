<?php

namespace App\Calendar;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Calendar\SchedulingPollOption
 *
 * @property ?string $DateStart
 * @property ?string $DateEnd
 * @property ?string $TimeStart
 * @property ?string $TimeEnd
 * @property bool $AllDay
 * @property int $ParentID
 * @method \App\Calendar\SchedulingPoll Parent()
 * @method \SilverStripe\ORM\DataList|\App\Calendar\SchedulingPollOptionParticipation[] OptionParticipations()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class SchedulingPollOption extends DataObject implements PermissionProvider
{
    private static $db = [
        "DateStart" => "Date",
        "DateEnd" => "Date",
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
        "AllDay" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => SchedulingPoll::class,
    ];

    private static $has_many = [
        'OptionParticipations' => SchedulingPollOptionParticipation::class,
    ];

    private static $owns = [
        'OptionParticipations',
    ];

    private static $field_labels = [
        "DateStart" => "Von",
        "DateEnd" => "Bis",
        "TimeStart" => "Uhrzeit von",
        "TimeEnd" => "Uhrzeit bis",
        "AllDay" => "Ganztägig",
    ];

    private static $summary_fields = [
        "RenderDateWithTime" => "Datum & Zeit",
    ];

    private static $table_name = 'SchedulingPollOption';
    private static $singular_name = "Terminoption";
    private static $plural_name = "Terminoptionen";
    private static $default_sort = ['DateStart' => 'ASC'];

    public function RenderDate()
    {
        try {
            $date = $this->dbObject('DateStart');
            if ($date && $date->getValue()) {
                return $date->Format('dd.MM.yy');
            }
        } catch (\Exception $e) {
            // Silently handle errors for new records
        }
        return "Kein Datum";
    }

    public function RenderDateWithTime()
    {
        $date = $this->dbObject('DateStart');
        if ($date && $date->getValue()) {
            $dateStr = $date->Format('dd.MM.yy');
            if ($this->AllDay) {
                return $dateStr . ' (Ganztägig)';
            } elseif ($this->TimeStart && $this->TimeEnd) {
                return $dateStr . ', ' . $this->dbObject('TimeStart')->Format('HH:mm') . ' – ' . $this->dbObject('TimeEnd')->Format('HH:mm');
            } elseif ($this->TimeStart) {
                return $dateStr . ', ab ' . $this->dbObject('TimeStart')->Format('HH:mm');
            } else {
                return $dateStr;
            }
        }
        return "Kein Datum";
    }

    public function FormatTimeStart()
    {
        if (!$this->TimeStart) {
            return '';
        }
        return (new \DateTime($this->TimeStart))->format('H:i');
    }

    public function FormatTimeEnd()
    {
        if (!$this->TimeEnd) {
            return '';
        }
        return (new \DateTime($this->TimeEnd))->format('H:i');
    }

    public function RenderTime()
    {
        if ($this->AllDay) {
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

    public function getVotedYes()
    {
        return $this->OptionParticipations()->filter('Type', 'Accept')->count();
    }

    public function getVotedMaybe()
    {
        return $this->OptionParticipations()->filter('Type', 'Maybe')->count();
    }

    public function getVotedNo()
    {
        return $this->OptionParticipations()->filter('Type', 'Decline')->count();
    }

    public function providePermissions()
    {
        return [
            'CREATE_SCHEDULINGPOLLOPTIONS' => [
                'name' => 'Terminoptionen erstellen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Erstellen von Terminoptionen'
            ],
            'EDIT_SCHEDULINGPOLLOPTIONS' => [
                'name' => 'Terminoptionen bearbeiten',
                'category' => 'Termine',
                'help' => 'Erlaubt das Bearbeiten von Terminoptionen'
            ],
            'VIEW_SCHEDULINGPOLLOPTIONS' => [
                'name' => 'Terminoptionen ansehen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Ansehen von Terminoptionen'
            ],
            'DELETE_SCHEDULINGPOLLOPTIONS' => [
                'name' => 'Terminoptionen löschen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Löschen von Terminoptionen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CREATE_SCHEDULINGPOLLOPTIONS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('EDIT_SCHEDULINGPOLLOPTIONS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('DELETE_SCHEDULINGPOLLOPTIONS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        return Permission::check('VIEW_SCHEDULINGPOLLOPTIONS', 'any', $member);
    }
}
