<?php

namespace App\Food;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

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
 * @mixin \App\History\HistoryExtension
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

    /**
     * Bestellmengen landen im Verlauf der Mahlzeit (siehe HistoryExtension).
     */
    private static $history_target = 'Meal';

    private static $history_fields = ['Quantity'];

    private static $history_field_labels = ['Quantity' => 'Bestellung'];

    /**
     * Gericht (und ggf. Besteller) für den Verlauf, z. B. "Bestellung (Pizza)".
     */
    public function getHistoryContextLabel(): ?string
    {
        $food = $this->Food();
        $parts = [$food && $food->exists() ? $food->Title : null];

        $current = Security::getCurrentUser();
        if (!$current || (int) $current->ID !== (int) $this->MemberID) {
            $member = $this->Member();
            $parts[] = $member && $member->exists() ? 'für ' . $member->getDisplayName() : null;
        }

        return implode(', ', array_filter($parts)) ?: null;
    }

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
