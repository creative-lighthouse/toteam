<?php

namespace App\Food;

use Override;
use App\Events\EventDayMeal;
use SilverStripe\Assets\Image;
use App\HumanResources\Allergy;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

class Food extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "FoodPreference" => "Varchar(255)",
        "Notes" => "Text",
    ];

    private static $has_one = [
        "Image" => Image::class,
        "Supplier" => Member::class,
    ];

    private static $owns = [
        'Image',
    ];

    private static $many_many = [
        "Allergies" => Allergy::class,
        "Meals" => EventDayMeal::class,
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title"
    ];

    private static $table_name = 'Food';
    private static $singular_name = "Gericht";
    private static $plural_name = "Gerichte";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('FoodPreference', DropdownField::create('FoodPreference', 'Essenspräferenz', [
            'None' => 'Keine Besonderheiten',
            'Vegetarian' => 'Vegetarisch',
            'Vegan' => 'Vegan',
        ]));
        $EventDayMealConfig = $fields->dataFieldByName('Meals')->getConfig();
        $EventDayMealConfig->getComponentByType(GridFieldAddExistingAutocompleter::class)
            ->setResultsFormat('$Title - $Parent.Title');
        return $fields;
    }

    public function RenderSupplier()
    {
        if ($this->SupplierID && $this->Supplier()->exists()) {
            return $this->Supplier()->RenderName();
        }
        return 'Niemand';
    }
}
