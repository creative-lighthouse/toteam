<?php

namespace App\HumanResources;

use Override;
use App\Food\Food;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;

/**
 * Class \App\HumanResources\Allergy
 *
 * @property ?string $Title
 * @property ?string $Category
 * @property int $IconID
 * @method \SilverStripe\Assets\Image Icon()
 * @method \SilverStripe\ORM\ManyManyList|\App\Food\Food[] Foods()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Members()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Allergy extends DataObject
{
    public const CATEGORY_FOOD = 'Essen';
    public const CATEGORY_ANIMAL = 'Tiere';
    public const CATEGORY_OTHER = 'Sonstiges';

    private static $db = [
        "Title" => "Varchar(255)",
        "Category" => "Enum('Essen,Tiere,Sonstiges','Essen')",
    ];

    private static $has_one = [
        "Icon" => Image::class,
    ];

    private static $owns = [
        'Icon',
    ];

    private static $belongs_many_many = [
        'Foods' => Food::class,
        "Members" => Member::class,
    ];

    private static $field_labels = [
        "Category" => "Kategorie",
    ];

    private static $summary_fields = [
        "Title",
        "RenderCategory" => "Kategorie",
    ];

    private static $default_sort = 'Title ASC';

    private static $table_name = 'Allergy';
    private static $singular_name = "Allergie";
    private static $plural_name = "Allergien";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('Category', DropdownField::create('Category', 'Kategorie', [
            self::CATEGORY_FOOD => 'Essen',
            self::CATEGORY_ANIMAL => 'Tiere',
            self::CATEGORY_OTHER => 'Sonstiges',
        ]));
        return $fields;
    }

    public function RenderCategory()
    {
        switch ($this->Category) {
            case self::CATEGORY_FOOD:
                return 'Essen';
            case self::CATEGORY_ANIMAL:
                return 'Tiere';
            case self::CATEGORY_OTHER:
                return 'Sonstiges';
            default:
                return $this->Category;
        }
    }

    public function getIsInFood($foodid)
    {
        $foods = $this->Foods()->filter('ID', $foodid);
        if ($foods->count() > 0) {
            return true;
        }
        return false;
    }

    public function providePermissions()
    {
        return [
            'CREATE_ALLERGIES' => [
                'name' => 'Allergien erstellen',
                'category' => 'Administration',
                'help' => 'Erlaubt das Erstellen, von Allergien'
            ],
            'EDIT_ALLERGIES' => [
                'name' => 'Allergien bearbeiten',
                'category' => 'Administration',
                'help' => 'Erlaubt das Bearbeiten von Allergien'
            ],
            'VIEW_ALLERGIES' => [
                'name' => 'Allergien ansehen',
                'category' => 'Administration',
                'help' => 'Erlaubt das Ansehen von Allergien'
            ],
            'DELETE_ALLERGIES' => [
                'name' => 'Allergien löschen',
                'category' => 'Administration',
                'help' => 'Erlaubt das Löschen von Allergien'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_ALLERGIES');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_ALLERGIES');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_ALLERGIES');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_ALLERGIES');
    }
}
