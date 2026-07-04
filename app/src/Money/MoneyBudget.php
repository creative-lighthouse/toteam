<?php

namespace App\Money;

use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Money\MoneyBudget
 *
 * @property ?string $Title
 * @property float $Budget
 * @property bool $HasBudget
 * @property float $CachedCurrentBalance
 * @property bool $CanBeOverBudget
 * @property int $ParentID
 * @method \App\Money\MoneyAccount Parent()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MoneyBudget extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Budget" => "Decimal(19,2)",
        "HasBudget" => "Boolean",
        "CachedCurrentBalance" => "Decimal(19,2)",
        "CanBeOverBudget" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => MoneyAccount::class,
    ];

    private static $owns = [
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Budget" => "Budget",
        "HasBudget" => "Hat Budget",
        "Parent" => "Konto",
        "CanBeOverBudget" => "Kann über Budget gehen",
    ];

    private static $summary_fields = [
        "Title",
        "Budget",
        "HasBudget",
        "CachedCurrentBalance"
    ];

    private static $default_sort = 'Title DESC';

    private static $table_name = 'MoneyBudget';
    private static $singular_name = "Budget";
    private static $plural_name = "Budgets";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');
        $fields->removeByName('SortOrder');
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_MONEYBUDGET' => [
                'name' => 'Budgets erstellen',
                'category' => 'Budgets',
                'help' => 'Erlaubt das Erstellen von Budgets'
            ],
            'EDIT_MONEYBUDGET' => [
                'name' => 'Budgets bearbeiten',
                'category' => 'Budgets',
                'help' => 'Erlaubt das Bearbeiten von Budgets'
            ],
            'VIEW_MONEYBUDGET' => [
                'name' => 'Budgets ansehen',
                'category' => 'Budgets',
                'help' => 'Erlaubt das Ansehen von Budgets'
            ],
            'DELETE_MONEYBUDGET' => [
                'name' => 'Budgets löschen',
                'category' => 'Budgets',
                'help' => 'Erlaubt das Löschen von Budgets'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MONEYBUDGET');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MONEYBUDGET');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MONEYBUDGET');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MONEYBUDGET');
    }
}
