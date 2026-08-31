<?php

namespace App\Money;

use Override;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Money\MoneyHistory
 *
 * @property ?string $ChangeReason
 * @property float $ChangeAmount
 * @property ?string $ChangeType
 * @property ?string $ChangeDate
 * @property ?string $Notes
 * @property bool $Approved
 * @property int $ParentID
 * @property int $UserID
 * @property int $ReceiptID
 * @property int $BudgetID
 * @method \App\Money\MoneyAccount Parent()
 * @method \SilverStripe\Security\Member User()
 * @method \SilverStripe\Assets\File Receipt()
 * @method \App\Money\MoneyBudget Budget()
 * @method \SilverStripe\ORM\DataList|\App\Money\MoneySettlement[] Settlements()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MoneyHistory extends DataObject implements PermissionProvider
{
    private static $db = [
        "ChangeReason" => "Varchar(255)",
        "ChangeAmount" => "Decimal(19,2)",
        "ChangeType" => "Enum('Deposit,Withdrawal','Deposit')",
        "ChangeDate" => "Datetime",
        "Notes" => "Text",
        "Approved" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => MoneyAccount::class,
        "User" => Member::class,
        "Receipt" => File::class,
        "Budget" => MoneyBudget::class,
    ];

    private static $has_many = [
        "Settlements" => MoneySettlement::class,
    ];

    private static $owns = [
        "Receipt"
    ];

    private static $field_labels = [
        "ChangeReason" => "Änderungsgrund",
        "ChangeAmount" => "Änderungsbetrag",
        "ChangeType" => "Änderungstyp",
        "ChangeDate" => "Änderungsdatum",
        "Notes" => "Anmerkungen",
        "Parent" => "Konto",
        "User" => "Benutzer",
        "Receipt" => "Beleg",
    ];

    private static $summary_fields = [
        "ChangeDate",
        "ChangeReason",
        "ChangeAmount",
        "ChangeType",
        "Approved",
        "User.Name",
    ];

    private static $default_sort = 'ChangeDate DESC';

    private static $table_name = 'MoneyHistory';
    private static $singular_name = "Geld-Änderung";
    private static $plural_name = "Geld-Änderungen";

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
            'CREATE_MONEYHISTORY' => [
                'name' => 'Geld-Änderungen erstellen',
                'category' => 'Geld-Änderungen',
                'help' => 'Erlaubt das Erstellen von Geld-Änderungen'
            ],
            'EDIT_MONEYHISTORY' => [
                'name' => 'Geld-Änderungen bearbeiten',
                'category' => 'Geld-Änderungen',
                'help' => 'Erlaubt das Bearbeiten von Geld-Änderungen'
            ],
            'VIEW_MONEYHISTORY' => [
                'name' => 'Geld-Änderungen ansehen',
                'category' => 'Geld-Änderungen',
                'help' => 'Erlaubt das Ansehen von Geld-Änderungen'
            ],
            'DELETE_MONEYHISTORY' => [
                'name' => 'Geld-Änderungen löschen',
                'category' => 'Geld-Änderungen',
                'help' => 'Erlaubt das Löschen von Geld-Änderungen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MONEYHISTORY');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MONEYHISTORY');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MONEYHISTORY');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MONEYHISTORY');
    }
}
