<?php

namespace App\Calendar;

use App\Food\Meal;
use App\Notifications\PendingNotificationJob;
use App\Teams\Organization;
use Override;
use SilverStripe\Assets\Image;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;

// Same-namespace classes (imported explicitly for IDE/static analysis)
use App\Calendar\AppointmentAgendaPoint;
use App\Calendar\AppointmentParticipation;
use App\Calendar\AppointmentType;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\SearchableMultiDropdownField;
use SilverStripe\Forms\TimeField;

/**
 * Class \App\Calendar\Appointment
 *
 * @property ?string $Title
 * @property ?string $DateStart
 * @property ?string $DateEnd
 * @property ?string $TimeStart
 * @property ?string $TimeEnd
 * @property bool $AllDay
 * @property ?string $Location
 * @property ?string $Description
 * @property int $ICSSequence
 * @property ?string $Status
 * @property int $ImageID
 * @property int $TypeID
 * @method \SilverStripe\Assets\Image Image()
 * @method \App\Calendar\AppointmentType Type()
 * @method \SilverStripe\ORM\DataList|\App\Calendar\AppointmentParticipation[] Participations()
 * @method \SilverStripe\ORM\DataList|\App\Food\Meal[] Meals()
 * @method \SilverStripe\ORM\DataList|\App\Calendar\AppointmentAgendaPoint[] AgendaPoints()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\Organization[] Organisations()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Appointment extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar",

        "DateStart" => "Date",
        "DateEnd" => "Date",
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
        "AllDay" => "Boolean",

        "Location" => "Varchar(511)",
        "Description" => "Text",
        "ICSSequence" => "Int",
        "Status" => "Enum('Suggested,Scheduled,Cancelled','Scheduled')",
    ];

    private static $has_one = [
        "Image" => Image::class,
        "Type" => AppointmentType::class,
    ];

    private static $many_many = [
        'Organisations' => Organization::class,
    ];

    private static $has_many = [
        'Participations' => AppointmentParticipation::class,
        'Meals' => Meal::class,
        'AgendaPoints' => AppointmentAgendaPoint::class,
    ];

    private static $owns = [
        "Image",
        "Participations",
        "Meals",
        "AgendaPoints",
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "DateStart" => "Von",
        "DateEnd" => "Bis",
        "TimeStart" => "Uhrzeit von",
        "TimeEnd" => "Uhrzeit bis",
        "AllDay" => "Ganztägig",
        "Type" => "Termin-Typ",
        "Image" => "Bild",
        "Participations" => "Teilnahmen",
        "Meals" => "Mahlzeiten",
        "AgendaPoints" => "Tagesordnungspunkte",
        "Location" => "Ort",
        "Description" => "Beschreibung",
    ];

    private static $summary_fields = [
        "RenderTitle" => "Titel",
        "RenderDateWithTime" => "Datum & Zeit",
        "Status" => "Status",
    ];

    private static $table_name = 'Appointment';
    private static $singular_name = "Termin";
    private static $plural_name = "Termine";
    private static $default_sort = ['DateStart' => 'ASC'];

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(["ICSSequence", "DateStart", "DateEnd", "TimeStart", "TimeEnd", "AllDay"]);

        // Compact date row: [Von] [Bis] [☐ Ganztägig]
        $fields->addFieldsToTab('Root.Main', [
            FieldGroup::create('Datum', [
                DateField::create('DateStart', 'Von'),
                DateField::create('DateEnd', 'Bis'),
            ]),
            // Time row – only relevant when not AllDay
            FieldGroup::create('Uhrzeit', [
                TimeField::create('TimeStart', 'Von'),
                TimeField::create('TimeEnd', 'Bis'),
                CheckboxField::create('AllDay', 'Ganztägig'),
            ]),
        ]);

        // Make TypeID dropdown optional
        if ($typeField = $fields->dataFieldByName('TypeID')) {
            $typeField->setEmptyString('-- Kein Typ --');
        }

        $fields->removeByName('Organisations');
        $orgField = SearchableMultiDropdownField::create(
            'Organisations',
            'Organisationen',
            Organization::get(),
            'ID',
            'Title'
        )->setIsLazyLoaded(true);
        $fields->addFieldToTab('Root.Main', $orgField);

        //Move image to bottom
        $imageField = $fields->dataFieldByName('Image');
        $fields->removeByName('Image');
        $fields->addFieldToTab('Root.Main', $imageField);

        //Change Meal and AgendaPoints Gridfields to use GridFieldConfig_RecordEditor
        $mealField = $fields->dataFieldByName('Meals');
        if ($mealField) {
            $mealField->setConfig(GridFieldConfig_RecordEditor::create());
        }

        $participantsField = $fields->dataFieldByName('Participations');
        $fields->removeByName('Participations');
        if ($participantsField) {
            $participantsField->setConfig(GridFieldConfig_RecordEditor::create());
            $fields->addFieldToTab('Root.Teilnehmer', $participantsField);
        }

        $agendaField = $fields->dataFieldByName('AgendaPoints');
        $fields->removeByName('AgendaPoints');
        if ($agendaField) {
            $agendaField->setConfig(GridFieldConfig_RecordEditor::create());
            $fields->addFieldToTab('Root.Tagesordnung', $agendaField);
        }

        return $fields;
    }

    /**
     * Event handler called before writing to the database.
     *
     * @uses DataExtension->onAfterWrite()
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->ICSSequence = ($this->ICSSequence ?? 0) + 1;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        $changedFields = $this->getChangedFields(false, 1);
        $isNew = isset($changedFields['ID']) && empty($changedFields['ID']['before']);
        $eventType = null;

        if ($isNew) {
            $eventType = match ($this->Status) {
                'Suggested' => 'appointment_suggested',
                'Scheduled' => 'appointment_scheduled',
                default     => null,
            };
        } elseif (isset($changedFields['Status'])) {
            $eventType = match ($changedFields['Status']['after']) {
                'Scheduled' => 'appointment_scheduled',
                'Cancelled' => 'appointment_cancelled',
                default     => null,
            };
        }

        if ($eventType) {
            PendingNotificationJob::create([
                'SourceClass' => self::class,
                'SourceID'    => $this->ID,
                'EventType'   => $eventType,
            ])->write();
        }
    }

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

    public function getEvent()
    {
        return $this->Parent();
    }

    public function getParticipationOfCurrentUser()
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return null;
        }
        return AppointmentParticipation::get()->filter(['ParentID' => $this->ID, 'MemberID' => $member->ID])->first();
    }

    public function getGroupedParticipations()
    {
        // Sortiere nach fester Reihenfolge: Accept, Maybe, Decline (in PHP)
        $order = ['Accept', 'Maybe', 'Decline'];
        $participations = $this->Participations()->toArray();
        usort($participations, function ($a, $b) use ($order) {
            return array_search($a->Type, $order) <=> array_search($b->Type, $order);
        });
        $participationsdatalist = ArrayList::create($participations);
        return GroupedList::create($participationsdatalist);
    }

    public function FormatTimeStart()
    {
        if (!$this->TimeStart) {
            return '';
        }
        return $this->TimeStart ? (new \DateTime($this->TimeStart))->format('H:i') : '';
    }

    public function FormatTimeEnd()
    {
        if (!$this->TimeEnd) {
            return '';
        }
        return $this->TimeEnd ? (new \DateTime($this->TimeEnd))->format('H:i') : '';
    }

    public function RenderTime()
    {
        if ($this->TimeStart && $this->TimeEnd) {
            return $this->FormatTimeStart() . " - " . $this->FormatTimeEnd();
        } elseif ($this->TimeStart) {
            return "ab " . $this->FormatTimeStart();
        } elseif ($this->TimeEnd) {
            return "bis " . $this->FormatTimeEnd();
        } else {
            return "Kein Datum";
        }
    }

    public function getLink()
    {
        return "/app/calendar?date=" . $this->DateStart . "&eventID=" . $this->ID;
    }

    public function getFullStartDate()
    {
        return $this->DateStart . ' ' . $this->TimeStart;
    }

    public function getFullEndDate()
    {
        return ($this->DateEnd ?: $this->DateStart) . ' ' . $this->TimeEnd;
    }

    public function getAgenda()
    {
        $agendapoints = $this->AgendaPoints()->sort('StartTime', 'ASC');
        $meals = $this->Meals()->sort('Time', 'ASC');

        //Add all agendapoints and meals to a single list and sort them by time
        $agenda = ArrayList::create();
        foreach ($agendapoints as $point) {
            $agenda->push([
                'Type' => 'AgendaPoint',
                'Item' => $point,
                'Time' => $point->StartTime,
            ]);
        }
        foreach ($meals as $meal) {
            $agenda->push([
                'Type' => 'Meal',
                'Item' => $meal,
                'Time' => $meal->Time,
            ]);
        }
        return $agenda;
    }

    public function RenderTitle()
    {
        // Safely get title even if object doesn't exist yet
        $title = $this->getField('Title') ?: '';
        $status = $this->getField('Status') ?: 'Scheduled';

        if ($status == 'Cancelled') {
            return $title;
        } elseif ($status == 'Suggested') {
            return $title . ' (Vorschlag)';
        } else {
            return $title;
        }
    }

    public function getVotedYes()
    {
        return $this->Participations()->filter('Type', 'Accept')->count();
    }

    public function getVotedMaybe()
    {
        return $this->Participations()->filter('Type', 'Maybe')->count();
    }

    public function getVotedNo()
    {
        return $this->Participations()->filter('Type', 'Decline')->count();
    }

    public function getAllOfSameTitleSuggestedEvents()
    {
        $alloptions = Appointment::get()->filter([
            'Title' => $this->Title,
            'Status' => 'Suggested',
        ]);
        // Sortiere in PHP nach Accept-Teilnahmen
        $alloptionsArr = $alloptions->toArray();
        usort($alloptionsArr, function ($a, $b) {
            return $b->Participations()->filter('Type', 'Accept')->count() <=> $a->Participations()->filter('Type', 'Accept')->count();
        });
        return ArrayList::create($alloptionsArr);
    }

    public function providePermissions()
    {
        return [
            'CREATE_APPOINTMENTS' => [
                'name' => 'Termine erstellen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Erstellen von Terminen'
            ],
            'EDIT_APPOINTMENTS' => [
                'name' => 'Termine bearbeiten',
                'category' => 'Termine',
                'help' => 'Erlaubt das Bearbeiten von Terminen'
            ],
            'VIEW_APPOINTMENTS' => [
                'name' => 'Termine ansehen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Ansehen von Terminen'
            ],
            'DELETE_APPOINTMENTS' => [
                'name' => 'Termine löschen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Löschen von Terminen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_EVENTDAYS permission
        return Permission::check('CREATE_APPOINTMENTS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('EDIT_APPOINTMENTS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('DELETE_APPOINTMENTS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        return Permission::check('VIEW_APPOINTMENTS', 'any', $member);
    }
}
