<?php

namespace App\Food;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * Class \App\Food\MealProductOrder
 *
 * @property int $Quantity
 * @property int $FoodID
 * @property int $MealID
 * @property int $MemberID
 * @method \App\Food\Food Food()
 * @method \App\Food\Meal Meal()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MealProductOrder extends DataObject
{
    private static $db = [
        'Quantity' => 'Int',
    ];

    private static $has_one = [
        'Food'   => Food::class,
        'Meal'   => Meal::class,
        'Member' => Member::class,
    ];

    private static $field_labels = [
        'Quantity' => 'Menge',
        'Food'     => 'Gericht',
        'Meal'     => 'Mahlzeit',
        'Member'   => 'Benutzer',
    ];

    private static $summary_fields = [
        'Member.Title' => 'Benutzer',
        'Food.Title'   => 'Gericht',
        'Meal.Title'   => 'Mahlzeit',
        'Quantity'     => 'Menge',
    ];

    private static $table_name = 'MealProductOrder';
    private static $singular_name = 'Mahlzeit-Produktbestellung';
    private static $plural_name = 'Mahlzeit-Produktbestellungen';

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MEALS');
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MEALS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MEALS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MEALS');
    }
}
