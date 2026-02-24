<?php

namespace App\Food;

use Override;
use App\Food\Meal;
use App\Teams\Organization;
use SilverStripe\Assets\Image;
use App\HumanResources\Allergy;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Class \App\Food\Food
 *
 * @property ?string $Title
 * @property ?string $FoodPreference
 * @property ?string $Notes
 * @property ?string $Status
 * @property int $ParentID
 * @property int $ImageID
 * @property int $SupplierID
 * @method \App\Teams\Organization Parent()
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
class Food extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "FoodPreference" => "Varchar(255)",
        "Notes" => "Text",
        "Status" => "Enum('Accepted, New, Rejected', 'New')",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
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

    private static $field_labels = [
        "Title" => "Titel",
        "FoodPreference" => "Essenspräferenz",
        "Notes" => "Notizen",
        "Status" => "Status",
        "Image" => "Bild",
        "Supplier" => "Anbieter",
        "Allergies" => "Allergien",
        "Meals" => "Mahlzeiten",
        "Parent" => "Organisation",
    ];

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
            'None' => 'Nicht vegetarisch',
            'Vegetarian' => 'Vegetarisch',
            'Vegan' => 'Vegan',
        ]));

        $mealsField = $fields->dataFieldByName('Meals');
        if ($mealsField) {
            $EventDayMealConfig = $mealsField->getConfig();
            $EventDayMealConfig->getComponentByType(GridFieldAddExistingAutocompleter::class)
                ->setResultsFormat('$Title - $Parent.Title ($Foods.Count Gerichte)');
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

    public function RenderAllergies()
    {
        $allergies = $this->Allergies();
        if ($allergies->count() == 0) {
            return 'Keine';
        }
        $titles = [];
        foreach ($allergies as $allergy) {
            $titles[] = $allergy->Title;
        }
        return implode(', ', $titles);
    }

    public function RenderFoodPreference()
    {
        switch ($this->FoodPreference) {
            case 'None':
                return 'Nicht vegetarisch';
            case 'Vegetarian':
                return 'Vegetarisch';
            case 'Vegan':
                return 'Vegan';
            default:
                return $this->FoodPreference;
        }
    }

    public function RenderStatus()
    {
        switch ($this->Status) {
            case 'New':
                return 'Neu angeboten';
            case 'Accepted':
                return 'Akzeptiert';
            case 'Rejected':
                return 'Abgelehnt';
            default:
                return $this->Status;
        }
    }

    public function providePermissions()
    {
        return [
            'CREATE_FOODS' => [
                'name' => 'Gerichte erstellen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Erstellen, von Gerichten'
            ],
            'EDIT_FOODS' => [
                'name' => 'Gerichte bearbeiten',
                'category' => 'Essen',
                'help' => 'Erlaubt das Bearbeiten von Gerichten'
            ],
            'VIEW_FOODS' => [
                'name' => 'Gerichte ansehen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Ansehen von Gerichten'
            ],
            'DELETE_FOODS' => [
                'name' => 'Gerichte löschen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Löschen von Gerichten'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_FOODS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_FOODS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_FOODS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_FOODS');
    }
}
