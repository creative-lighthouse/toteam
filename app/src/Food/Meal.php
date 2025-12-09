<?php

namespace App\Food;

use App\Food\Food;
use App\Food\MealEater;
use App\Events\EventDay;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

/**
 * Class \App\Food\Meal
 *
 * @property ?string $Title
 * @property ?string $Time
 * @property int $ParentID
 * @method \App\Events\EventDay Parent()
 * @method \SilverStripe\ORM\DataList|\App\Food\MealEater[] Eaters()
 * @method \SilverStripe\ORM\ManyManyList|\App\Food\Food[] Foods()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Meal extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Time" => "Time"
    ];

    private static $has_one = [
        "Parent" => EventDay::class,
    ];

    private static $has_many = [
        "Eaters" => MealEater::class,
    ];

    private static $belongs_many_many = [
        "Foods" => Food::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Time" => "Uhrzeit",
        "Eaters" => "Teilnehmer",
        "Foods" => "Gerichte",
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

    private static $table_name = 'Meal';
    private static $singular_name = "Mahlzeit";
    private static $plural_name = "Mahlzeiten";
    private static $default_sort = 'Time ASC';

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

    public function getMealParticipationOfCurrentUser()
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return null;
        }
        return MealEater::get()->filter(['ParentID' => $this->ID, 'MemberID' => $member->ID])->first();
    }

    public function getDetailsLink()
    {
        return '/food/meal/' . $this->ID;
    }
}
