<?php

namespace App\Events;

use Override;
use App\Events\Event;
use App\Events\EventDayType;
use SilverStripe\Assets\Image;
use App\Pages\CalendarPage;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Security;
use App\Events\EventDayParticipation;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;

class EventDay extends DataObject
{
    private static $db = [
        "Title" => "Varchar",
        "Date" => "Date",
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
        "Location" => "Varchar(511)",
    ];

    private static $has_one = [
        'Parent' => Event::class,
        "Image" => Image::class,
        "Type" => EventDayType::class,
    ];

    private static $has_many = [
        'Participations' => EventDayParticipation::class,
        'Meals' => EventDayMeal::class,
    ];

    private static $owns = [
        'Image',
        "Participations",
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Date" => "Datum",
        "TimeStart" => "Beginn",
        "TimeEnd" => "Ende",
        "Type" => "Tages-Typ",
        "Image" => "Bild",
        "Participations" => "Teilnahmen",
        "Meals" => "Mahlzeiten",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "RenderDate" => "Tag",
    ];

    private static $table_name = 'EventDay';
    private static $singular_name = "Veranstaltungs-Tag";
    private static $plural_name = "Veranstaltungs-Tage";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ParentID");

        // Simple GridField with inline editing for Meals
        $editableColumns = GridFieldEditableColumns::create();
        $editableColumns->setDisplayFields([
            'Title' => [
                'title' => 'Titel',
                'callback' => function ($record, $column, $grid) {
                    return TextField::create($column);
                }
            ],
            'Description' => [
                'title' => 'Beschreibung',
                'callback' => function ($record, $column, $grid) {
                    return TextField::create($column);
                }
            ]
        ]);

        // Add delete and edit buttons
        $mealsGridConfig = GridFieldConfig_RecordEditor::create()
            ->addComponent($editableColumns);

        $mealsGrid = GridField::create(
            'Meals',
            'Mahlzeiten',
            $this->Meals(),
            $mealsGridConfig
        );
        $fields->removeByName('Meals');
        $fields->addFieldToTab('Root.Main', $mealsGrid);

        return $fields;
    }

    public function RenderDate()
    {
        $date = $this->dbObject('Date');
        if ($date instanceof DBField) {
            return $this->dbObject('Date')->Format('dd.MM.YYYY');
        } else {
            return "Kein Datum";
        }
    }

    public function RenderDateWithTime()
    {
        $date = $this->dbObject('Date');
        if ($date instanceof DBField) {
            if ($this->TimeStart && $this->TimeEnd) {
                return $this->dbObject('Date')->Format('dd.MM.YY') . ', ' . $this->dbObject('TimeStart')->Format('HH:mm') . ' - ' . $this->dbObject('TimeEnd')->Format('HH:mm');
            } elseif ($this->TimeStart) {
                return $this->dbObject('Date')->Format('dd.MM.YY') . ', Ab' . $this->dbObject('TimeStart')->Format('HH:mm');
            } else {
                return $this->dbObject('Date')->Format('dd.MM.YY');
            }
        } else {
            return "Kein Datum";
        }
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
        return EventDayParticipation::get()->filter(['ParentID' => $this->ID, 'MemberID' => $member->ID])->first();
    }

    public function getGroupedParticipations()
    {
        return GroupedList::create($this->Participations());
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
        $event = $this->Parent();
        $calendarPage = CalendarPage::get()->first();
        if ($event && $calendarPage) {
            return $calendarPage->Link() . "?date=" . $this->Date . "&eventID=" . $this->ID;
        }
        return null;
    }

    public function getFullStartDate(){
        return $this->Date . ' ' . $this->TimeStart;
    }

    public function getFullEndDate(){
        return $this->Date . ' ' . $this->TimeEnd;
    }
}
