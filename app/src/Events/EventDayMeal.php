<?php

namespace App\Events;

use App\Food\Food;
use App\Events\EventDay;
use SilverStripe\ORM\DataObject;
use App\Events\EventDayMealEater;
use SilverStripe\Security\Security;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Class \App\Events\EventDayMeal
 *
 * @property ?string $Title
 * @property ?string $Time
 * @property int $ParentID
 * @method \App\Events\EventDay Parent()
 * @method \SilverStripe\ORM\DataList|\App\Events\EventDayMealEater[] Eaters()
 * @method \SilverStripe\ORM\ManyManyList|\App\Food\Food[] Foods()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class EventDayMeal extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Time" => "Time"
    ];

    private static $has_one = [
        "Parent" => EventDay::class,
    ];

    private static $has_many = [
        "Eaters" => EventDayMealEater::class,
    ];

    private static $many_many = [
        "Foods" => Food::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Time" => "Uhrzeit",
        "Eaters" => "Teilnehmer",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Time" => "Uhrzeit",
        "Parent.Title" => "Tag",
        "Parent.Date.Nice" => "Datum",
    ];

    private static $searchable_fields = [
        "Title",
        "Time",
        "Parent.Title",
        "Parent.Date",
    ];

    private static $table_name = 'EventDayMeal';
    private static $singular_name = "Mahlzeit";
    private static $plural_name = "Mahlzeiten";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');

        $eatersGridfield = $fields->dataFieldByName('Eaters');
        $fields->removeByName('Eaters');
        if ($eatersGridfield) {
            $eatersGridfield->setConfig(GridFieldConfig_RecordEditor::create());
            $fields->addFieldToTab('Root.Main', $eatersGridfield);
        }
        return $fields;
    }

    public function RenderTime()
    {
        return $this->dbObject('Time')->Format('HH:mm');
    }

    public function getMealParticipationOfCurrentUser() {
        $member = Security::getCurrentUser();
        if(!$member) {
            return null;
        }
        return EventDayMealEater::get()->filter(['ParentID' => $this->ID, 'MemberID' => $member->ID])->first();
    }
}
