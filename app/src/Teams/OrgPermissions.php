<?php

namespace App\Teams;

/**
 * Class \App\Teams\OrgPermissions
 *
 * Fester, entwicklerdefinierter Katalog aller Berechtigungs-Codes, die einer
 * organisationsspezifischen Rolle (App\Teams\OrgRole) zugewiesen werden können.
 * ORG_ADMIN ist ein Wildcard und schließt alle anderen Codes automatisch ein
 * (siehe OrgRole::hasPermission()).
 */
class OrgPermissions
{
    public const ORG_ADMIN = 'ORG_ADMIN';
    public const ORG_MANAGE_MEMBERS = 'ORG_MANAGE_MEMBERS';
    public const ORG_MANAGE_ROLES = 'ORG_MANAGE_ROLES';

    public const TASKS_CREATE = 'TASKS_CREATE';
    public const TASKS_EDIT = 'TASKS_EDIT';
    public const TASKS_DELETE = 'TASKS_DELETE';

    public const MONEY_ACCOUNTS_CREATE = 'MONEY_ACCOUNTS_CREATE';
    public const MONEY_ACCOUNTS_EDIT = 'MONEY_ACCOUNTS_EDIT';
    public const MONEY_ACCOUNTS_DELETE = 'MONEY_ACCOUNTS_DELETE';
    public const MONEY_BUDGETS_MANAGE = 'MONEY_BUDGETS_MANAGE';
    public const MONEY_APPROVE_ENTRIES = 'MONEY_APPROVE_ENTRIES';
    public const MONEY_DEPOSITS_ENTER = 'MONEY_DEPOSITS_ENTER';
    public const MONEY_WITHDRAWALS_ENTER = 'MONEY_WITHDRAWALS_ENTER';

    public const CALENDAR_MANAGE = 'CALENDAR_MANAGE';
    public const CALENDAR_DELETE = 'CALENDAR_DELETE';

    public const FOOD_MANAGE_MEALS = 'FOOD_MANAGE_MEALS';

    public const LINKS_MANAGE = 'LINKS_MANAGE';

    public const MAPS_MANAGE_MAPS = 'MAPS_MANAGE_MAPS';
    public const MAPS_MANAGE_LAYERS = 'MAPS_MANAGE_LAYERS';

    /**
     * Alle Berechtigungen gruppiert nach Kategorie, für die Rollen-Verwaltungs-UI.
     * @return array<string, array<string, string>> Kategorie => [Code => Label]
     */
    public static function categories(): array
    {
        return [
            'Organisation' => [
                self::ORG_ADMIN => 'Administrator (darf alles)',
                self::ORG_MANAGE_MEMBERS => 'Mitglieder verwalten',
                self::ORG_MANAGE_ROLES => 'Rollen & Berechtigungen verwalten',
            ],
            'Aufgaben' => [
                self::TASKS_CREATE => 'Aufgaben erstellen',
                self::TASKS_EDIT => 'Aufgaben bearbeiten',
                self::TASKS_DELETE => 'Aufgaben löschen',
            ],
            'Geld' => [
                self::MONEY_ACCOUNTS_CREATE => 'Kassen erstellen',
                self::MONEY_ACCOUNTS_EDIT => 'Kassen bearbeiten',
                self::MONEY_ACCOUNTS_DELETE => 'Kassen löschen',
                self::MONEY_BUDGETS_MANAGE => 'Budgets verwalten',
                self::MONEY_APPROVE_ENTRIES => 'Buchungen freigeben',
                self::MONEY_DEPOSITS_ENTER => 'Einnahmen eingeben',
                self::MONEY_WITHDRAWALS_ENTER => 'Ausgaben eingeben',
            ],
            'Kalender' => [
                self::CALENDAR_MANAGE => 'Termine verwalten',
                self::CALENDAR_DELETE => 'Termine löschen',
            ],
            'Essen' => [
                self::FOOD_MANAGE_MEALS => 'Mahlzeiten verwalten',
            ],
            'Links' => [
                self::LINKS_MANAGE => 'Links verwalten',
            ],
            'Lagepläne' => [
                self::MAPS_MANAGE_MAPS => 'Karten verwalten',
                self::MAPS_MANAGE_LAYERS => 'Ebenen verwalten',
            ],
        ];
    }

    /**
     * @return string[] Alle gültigen Codes (zur Validierung von Nutzereingaben)
     */
    public static function allCodes(): array
    {
        $codes = [];
        foreach (self::categories() as $permissions) {
            $codes = [...$codes, ...array_keys($permissions)];
        }
        return $codes;
    }
}
