<?php

namespace App\Food;

use Override;
use App\Food\Meal;
use SilverStripe\Assets\Image;
use App\HumanResources\Allergy;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Class \App\Food\Food
 *
 * @property ?string $Title
 * @property ?string $FoodPreference
 * @property ?string $Notes
 * @property int $ImageID
 * @property int $SupplierID
 * @method \SilverStripe\Assets\Image Image()
 * @method \SilverStripe\Security\Member Supplier()
 * @method \SilverStripe\ORM\ManyManyList|\App\HumanResources\Allergy[] Allergies()
 * @method \SilverStripe\ORM\ManyManyList|\App\Food\Meal[] Meals()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
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
        "Meals" => Meal::class,
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

        $mealsField = $fields->dataFieldByName('Meals');
        if ($mealsField) {
            $EventDayMealConfig = $mealsField->getConfig();
            $EventDayMealConfig->getComponentByType(GridFieldAddExistingAutocompleter::class)
                ->setResultsFormat('$Title - $Parent.Title');
        }
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
