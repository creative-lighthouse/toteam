<?php

namespace App\Money;

use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Money\MoneySettlement
 *
 * Eine (ggf. teilweise) Begleichung einer Ausgaben-Buchung (MoneyHistory).
 * Eine Buchung kann mehrere Begleichungen haben (z.B. mehrere Teilzahlungen).
 *
 * @property float $Amount
 * @property ?string $Date
 * @property ?string $PaymentMethod
 * @property int $EntryID
 * @property int $UserID
 * @method \App\Money\MoneyHistory Entry()
 * @method \SilverStripe\Security\Member User()
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MoneySettlement extends DataObject implements PermissionProvider
{
    private static $db = [
        "Amount" => "Decimal(19,2)",
        "Date" => "Datetime",
        "PaymentMethod" => "Enum('Bar,EC','Bar')",
    ];

    private static $has_one = [
        "Entry" => MoneyHistory::class,
        "User" => Member::class,
    ];

    private static $field_labels = [
        "Amount" => "Betrag",
        "Date" => "Datum",
        "PaymentMethod" => "Zahlungsart",
        "Entry" => "Buchung",
        "User" => "Erfasst von",
    ];

    private static $summary_fields = [
        "Date",
        "Amount",
        "PaymentMethod",
        "User.Name",
    ];

    private static $default_sort = 'Date DESC';

    /**
     * Erfasste/gelöschte Begleichungen erscheinen im Verlauf der Buchung (siehe HistoryExtension).
     */
    private static $history_target = 'Entry';

    private static $history_target_set = 'Settlements';

    /**
     * Anzeigename im Verlauf, z. B. "20,00 € (Bar, 24.09.2026)".
     */
    public function getTitle()
    {
        $amount = number_format((float) $this->Amount, 2, ',', '.') . ' €';
        $date = $this->Date ? date('d.m.Y', strtotime((string) $this->Date)) : null;
        return $amount . ' (' . implode(', ', array_filter([$this->PaymentMethod, $date])) . ')';
    }

    private static $table_name = 'MoneySettlement';
    private static $singular_name = "Begleichung";
    private static $plural_name = "Begleichungen";

    public function providePermissions()
    {
        return [
            'CREATE_MONEYSETTLEMENT' => [
                'name' => 'Begleichungen erstellen',
                'category' => 'Begleichungen',
                'help' => 'Erlaubt das Erfassen von Begleichungen'
            ],
            'VIEW_MONEYSETTLEMENT' => [
                'name' => 'Begleichungen ansehen',
                'category' => 'Begleichungen',
                'help' => 'Erlaubt das Ansehen von Begleichungen'
            ],
            'DELETE_MONEYSETTLEMENT' => [
                'name' => 'Begleichungen löschen',
                'category' => 'Begleichungen',
                'help' => 'Erlaubt das Löschen von Begleichungen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MONEYSETTLEMENT');
    }

    public function canEdit($member = null, $context = [])
    {
        return false;
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MONEYSETTLEMENT');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MONEYSETTLEMENT');
    }
}
