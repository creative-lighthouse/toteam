<?php

namespace App\Extensions;

use SilverStripe\Forms\FieldList;
use App\Tasks\Task;
use SilverStripe\Assets\Image;
use App\HumanResources\Allergy;
use SilverStripe\Core\Extension;
use App\HumanResources\Department;
use App\Events\EventDayParticipation;
use SilverStripe\Forms\DropdownField;

class MemberExtension extends Extension
{
    private static $db = [
        "Joindate" => "Date",
        "FoodPreference" => "Varchar(255)",
        "DateOfBirth" => "Date",
    ];

    private static $has_one = [
        "ProfileImage" => Image::class,
    ];

    private static $many_many = [
        "Allergies" => Allergy::class,
    ];

    private static $belongs_many = [
        "Departments" => Department::class,
        "Tasks" => Task::class,
    ];

    private static $owns = [
        'ProfileImage',
    ];

    private static $field_labels = [
        'Joindate' => 'Mitglied seit',
        'FoodPreference' => 'Essenspräferenz',
        'Allergies' => 'Allergien',
    ];

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->replaceField('FoodPreference', DropdownField::create('FoodPreference', 'Essenspräferenz', [
            'None' => 'Keine Besonderheiten',
            'Vegetarian' => 'Vegetarisch',
            'Vegan' => 'Vegan',
        ]));
        return $fields;
    }

    public function getParticipations()
    {
        return EventDayParticipation::get()->filter('MemberID', $this->owner->ID);
    }

    public function RenderFoodPreference()
    {
        switch ($this->owner->FoodPreference) {
            case 'Vegetarian':
                return 'Vegetarisch';
            case 'Vegan':
                return 'Vegan';
            default:
                return 'Keine Besonderheiten';
        }
    }

    public function RenderAllergies()
    {
        $allergies = $this->owner->Allergies();
        if ($allergies->count() == 0) {
            return 'Keine Allergien';
        }

        $titles = [];
        foreach ($allergies as $allergy) {
            $titles[] = $allergy->Title;
        }
        return implode(', ', $titles);
    }

    public function getGravatar($size = 200)
    {
        //Generate a Gravatar for the user
        $s = $size; //Size in pixels (max 2048)
        $d = 'identicon'; //Default replacement for missing image
        $r = 'g'; //Rating
        $img = false; //Returning full image tag
        $atts = array(); //Extra attributes to add

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($this->owner->Email)));
        $url .= "?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val) {
                $url .= ' ' . $key . '="' . $val . '"';
            }
            $url .= ' />';
        }
        return $url;
    }

    public function RenderName()
    {
        return $this->owner->FirstName;
    }

    public function RenderProfileImage()
    {
        if ($this->owner->ProfileImageID && $this->owner->ProfileImage()->exists()) {
            return $this->owner->ProfileImage()->ScaleWidth(200)->getURL();
        }
        return $this->getGravatar(200);
    }
}
