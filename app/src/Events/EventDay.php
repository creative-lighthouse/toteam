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
use SilverStripe\Model\List\ArrayList;
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
 * @property int $ICSSequence
 * @property int $ParentID
 * @property int $ImageID
 * @property int $TypeID
 * @method \App\Events\Event Parent()
 * @method \SilverStripe\Assets\Image Image()
 * @method \App\Events\EventDayType Type()
 * @method \SilverStripe\ORM\DataList|\App\Events\EventDayParticipation[] Participations()
 * @method \SilverStripe\ORM\DataList|\App\Food\Meal[] Meals()
 * @method \SilverStripe\ORM\DataList|\App\Events\EventDayAgendaPoint[] AgendaPoints()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
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
        "ICSSequence" => "Int",
    ];

    private static $has_one = [
        'Parent' => Event::class,
        "Image" => Image::class,
        "Type" => EventDayType::class,
    ];

    private static $has_many = [
        'Participations' => EventDayParticipation::class,
        'Meals' => Meal::class,
        'AgendaPoints' => EventDayAgendaPoint::class,
    ];

    private static $owns = [
        "Image",
        "Participations",
        "Meals",
        "AgendaPoints",
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
        "AgendaPoints" => "Tagesordnungspunkte",
        "Location" => "Ort",
        "Description" => "Beschreibung",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "RenderDate" => "Tag",
    ];

    private static $table_name = 'EventDay';
    private static $singular_name = "Veranstaltungs-Tag";
    private static $plural_name = "Veranstaltungs-Tage";
    private static $default_sort = ['Date' => 'ASC'];

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ParentID");
        $fields->removeByName("ICSSequence");

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
