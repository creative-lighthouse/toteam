<?php

namespace App\Money;

use Override;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Money\MoneyAccount
 *
 * @property ?string $Title
 * @property ?string $IBAN
 * @property int $SortOrder
 * @property float $StartingAmount
 * @property float $TargetAmount
 * @property bool $RequiresApproval
 * @property bool $RequiresReceiptDeposit
 * @property bool $RequiresReceiptWithdrawal
 * @property float $CachedCurrentBalance
 * @property int $ParentID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\ORM\DataList|\App\Money\MoneyHistory[] MoneyHistory()
 * @method \SilverStripe\ORM\DataList|\App\Money\MoneyBudget[] MoneyBudget()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MoneyAccount extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "IBAN" => "Varchar(34)",
        "SortOrder" => "Int",
        "StartingAmount" => "Decimal(19,2)",
        "TargetAmount" => "Decimal(19,2)",
        "RequiresApproval" => "Boolean",
        "RequiresReceiptDeposit" => "Boolean",
        "RequiresReceiptWithdrawal" => "Boolean",
        "CachedCurrentBalance" => "Decimal(19,2)",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
    ];

    private static $has_many = [
        "MoneyHistory" => MoneyHistory::class,
        "MoneyBudget" => MoneyBudget::class,
    ];

    private static $owns = [
        "MoneyHistory",
        "MoneyBudget"
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "TargetAmount" => "Zielbetrag",
        "IBAN" => "IBAN",
        "SortOrder" => "Sortierreihenfolge",
        "Parent" => "Organisation",
        "StartingAmount" => "Startbetrag",
        "RequiresApproval" => "Änderungen müssen genehmigt werden",
        "RequiresReceiptDeposit" => "Beleg für Einnahmen erforderlich",
        "RequiresReceiptWithdrawal" => "Beleg für Ausgaben erforderlich",
        "CachedCurrentBalance" => "Zwischengespeicherter Kontostand",
        "MoneyHistory" => "Änderungsverlauf",
        "MoneyBudget" => "Budgets",
    ];

    private static $summary_fields = [
        "Title"
    ];

    private static $default_sort = 'SortOrder ASC';

    private static $table_name = 'MoneyAccount';
    private static $singular_name = "Konto";
    private static $plural_name = "Konten";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('SortOrder');
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_MONEYACCOUNTS' => [
                'name' => 'Konten erstellen',
                'category' => 'Konten',
                'help' => 'Erlaubt das Erstellen von Konten'
            ],
            'EDIT_MONEYACCOUNTS' => [
                'name' => 'Konten bearbeiten',
                'category' => 'Konten',
                'help' => 'Erlaubt das Bearbeiten von Konten'
            ],
            'VIEW_MONEYACCOUNTS' => [
                'name' => 'Konten ansehen',
                'category' => 'Konten',
                'help' => 'Erlaubt das Ansehen von Konten'
            ],
            'DELETE_MONEYACCOUNTS' => [
                'name' => 'Konten löschen',
                'category' => 'Konten',
                'help' => 'Erlaubt das Löschen von Konten'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MONEYACCOUNTS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MONEYACCOUNTS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MONEYACCOUNTS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MONEYACCOUNTS');
    }

    /**
     * Ob der Nutzer den Kontostand und Buchungsverlauf dieser Kasse sehen darf.
     * Jedes aktive Mitglied der Organisation.
     */
    public function canViewInApp(Member $member): bool
    {
        return $member->isActiveMemberOfOrg($this->Parent());
    }

    /**
     * Ob der Nutzer Einnahmen auf dieser Kasse erfassen darf.
     */
    public function canEnterDepositInApp(Member $member): bool
    {
        return $member->hasOrgPermission($this->Parent(), OrgPermissions::MONEY_DEPOSITS_ENTER);
    }

    /**
     * Ob der Nutzer Ausgaben auf dieser Kasse erfassen darf.
     */
    public function canEnterWithdrawalInApp(Member $member): bool
    {
        return $member->hasOrgPermission($this->Parent(), OrgPermissions::MONEY_WITHDRAWALS_ENTER);
    }

    /**
     * Ob der Nutzer diese Kasse selbst (Titel, IBAN, Einstellungen) bearbeiten darf.
     */
    public function canEditInApp(Member $member): bool
    {
        return $member->hasOrgPermission($this->Parent(), OrgPermissions::MONEY_ACCOUNTS_EDIT);
    }

    /**
     * Ob der Nutzer diese Kasse löschen darf.
     */
    public function canDeleteInApp(Member $member): bool
    {
        return $member->hasOrgPermission($this->Parent(), OrgPermissions::MONEY_ACCOUNTS_DELETE);
    }

    /**
     * Ob der Nutzer Budgets dieser Kasse anlegen/bearbeiten darf.
     */
    public function canManageBudgetsInApp(Member $member): bool
    {
        return $member->hasOrgPermission($this->Parent(), OrgPermissions::MONEY_BUDGETS_MANAGE);
    }

    /**
     * Ob der Nutzer offene Buchungen dieser Kasse freigeben/ablehnen darf.
     */
    public function canApproveEntriesInApp(Member $member): bool
    {
        return $member->hasOrgPermission($this->Parent(), OrgPermissions::MONEY_APPROVE_ENTRIES);
    }
}
