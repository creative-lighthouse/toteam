<?php

namespace App\Events;

use Override;
use App\Events\Event;
use App\Events\EventDayType;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use App\Events\EventDayParticipation;
use App\Pages\ParticipationPage;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

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

        //Make Meals Inline-Editable entries with gridfield-extensions
        $mealsGrid = GridField::create(
            'MealsGrid',
            'Mahlzeiten',
            $this->Meals(),
            GridFieldConfig::create()
                ->addComponent(GridFieldButtonRow::create('before'))
                ->addComponent(GridFieldToolbarHeader::create())
                ->addComponent(GridFieldTitleHeader::create())
                ->addComponent(GridFieldEditableColumns::create())
                ->addComponent(GridFieldDeleteAction::create())
                ->addComponent(GridFieldAddNewInlineButton::create())
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
            if($this->TimeStart && $this->TimeEnd) {
                return $this->dbObject('Date')->Format('dd.MM.YY') . ', ' . $this->dbObject('TimeStart')->Format('HH:mm') . ' - ' . $this->dbObject('TimeEnd')->Format('HH:mm');
            } else if($this->TimeStart) {
                return $this->dbObject('Date')->Format('dd.MM.YY') . ', Ab' . $this->dbObject('TimeStart')->Format('HH:mm');
            } else {
                return $this->dbObject('Date')->Format('dd.MM.YY');
            }
        } else {
            return "Kein Datum";
        }
    }

    public function getEvent() {
        return $this->Parent();
    }

    public function getParticipationOfCurrentUser() {
        $member = Security::getCurrentUser();
        if(!$member) {
            return null;
        }
        return EventDayParticipation::get()->filter(['ParentID' => $this->ID, 'MemberID' => $member->ID])->first();
    }

    public function getGroupedParticipations() {
        return GroupedList::create($this->Participations());
    }

    public function FormatTimeStart() {
        return $this->TimeStart ? (new \DateTime($this->TimeStart))->format('H:i') : '';
    }

    public function FormatTimeEnd() {
        return $this->TimeEnd ? (new \DateTime($this->TimeEnd))->format('H:i') : '';
    }

    public function RenderTime()
    {
        if($this->TimeStart && $this->TimeEnd) {
            return $this->FormatTimeStart() . " - " . $this->FormatTimeEnd();
        } elseif ($this->TimeStart) {
            return "ab " . $this->FormatTimeStart();
        } elseif ($this->TimeEnd) {
            return "bis " . $this->FormatTimeEnd();
        } else {
            return "Kein Datum";
        }
    }

    public function getLink() {
        $event = $this->Parent();
        $participationPage = ParticipationPage::get()->first();
        if($event && $participationPage) {
            return $participationPage->Link() . "?date=" . $this->Date . "&eventID=" . $this->ID;
        }
        return null;
    }
}
