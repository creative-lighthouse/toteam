<?php

namespace App\Events;

use Override;
use App\Food\Meal;
use App\Events\Event;
use App\Events\EventDayType;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use App\Events\EventDayParticipation;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Model\List\GroupedList;

/**
 * Class \App\Events\EventDay
 *
 * @property ?string $Title
 * @property ?string $Date
 * @property ?string $TimeStart
 * @property ?string $TimeEnd
 * @property ?string $Location
 * @property ?string $Description
 * @property int $ParentID
 * @property int $ImageID
 * @property int $TypeID
 * @method \App\Events\Event Parent()
 * @method \SilverStripe\Assets\Image Image()
 * @method \App\Events\EventDayType Type()
 * @method \SilverStripe\ORM\DataList|\App\Events\EventDayParticipation[] Participations()
 * @method \SilverStripe\ORM\DataList|\App\Events\EventDayMeal[] Meals()
 * @method \SilverStripe\ORM\DataList|\App\Food\Meal[] MealsNew()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class EventDay extends DataObject
{
    private static $db = [
        "Title" => "Varchar",
        "Date" => "Date",
        "TimeStart" => "Time",
        "TimeEnd" => "Time",
        "Location" => "Varchar(511)",
        "Description" => "Text",
    ];

    private static $has_one = [
        'Parent' => Event::class,
        "Image" => Image::class,
        "Type" => EventDayType::class,
    ];

    private static $has_many = [
        'Participations' => EventDayParticipation::class,
        'Meals' => EventDayMeal::class,
        'MealsNew' => Meal::class,
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
        "MealsNew" => "Mahlzeiten",
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
        if ($event) {
            return "/calendar" . "?date=" . $this->Date . "&eventID=" . $this->ID;
        }
        return null;
    }

    public function getFullStartDate()
    {
        return $this->Date . ' ' . $this->TimeStart;
    }

    public function getFullEndDate()
    {
        return $this->Date . ' ' . $this->TimeEnd;
    }
}
