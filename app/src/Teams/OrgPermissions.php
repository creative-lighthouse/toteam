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
    public const ORG_MANAGE_SETTINGS = 'ORG_MANAGE_SETTINGS';

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
    public const CALENDAR_RECORD_RSVP = 'CALENDAR_RECORD_RSVP';

    public const FOOD_MANAGE_MEALS = 'FOOD_MANAGE_MEALS';
    public const FOOD_APPROVE_SUGGESTIONS = 'FOOD_APPROVE_SUGGESTIONS';
    public const FOOD_RECORD_RSVP = 'FOOD_RECORD_RSVP';

    public const LINKS_MANAGE = 'LINKS_MANAGE';

    public const MAPS_MANAGE_MAPS = 'MAPS_MANAGE_MAPS';
    public const MAPS_MANAGE_LAYERS = 'MAPS_MANAGE_LAYERS';

    public const ROOMS_CREATE = 'ROOMS_CREATE';
    public const ROOMS_EDIT = 'ROOMS_EDIT';
    public const ROOMS_VIEW = 'ROOMS_VIEW';
    public const ROOMS_DELETE = 'ROOMS_DELETE';

    public const SCRIPT_EDIT = 'SCRIPT_EDIT';
    public const SCRIPT_MANAGE_ROLES = 'SCRIPT_MANAGE_ROLES';

    public const MARKETING_MANAGE_SIZES = 'MARKETING_MANAGE_SIZES';
    public const MARKETING_MANAGE_ENTRIES = 'MARKETING_MANAGE_ENTRIES';

    public const ANNOUNCEMENTS_CREATE = 'ANNOUNCEMENTS_CREATE';

    public const INVENTORY_CREATE = 'INVENTORY_CREATE';
    public const INVENTORY_EDIT = 'INVENTORY_EDIT';
    public const INVENTORY_DELETE = 'INVENTORY_DELETE';
    public const INVENTORY_MANAGE_TYPES = 'INVENTORY_MANAGE_TYPES';
    public const INVENTORY_REQUEST_RENTAL = 'INVENTORY_REQUEST_RENTAL';
    public const INVENTORY_APPROVE_RENTALS = 'INVENTORY_APPROVE_RENTALS';

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
                self::ORG_MANAGE_SETTINGS => 'Organisationsprofil (Logo etc.) bearbeiten',
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
                self::CALENDAR_RECORD_RSVP => 'Zu-/Absagen für andere eintragen',
            ],
            'Essen' => [
                self::FOOD_MANAGE_MEALS => 'Mahlzeiten verwalten',
                self::FOOD_APPROVE_SUGGESTIONS => 'Essens-Vorschläge bestätigen',
                self::FOOD_RECORD_RSVP => 'Zu-/Absagen für andere eintragen',
            ],
            'Links' => [
                self::LINKS_MANAGE => 'Links verwalten',
            ],
            'Lagepläne' => [
                self::MAPS_MANAGE_MAPS => 'Karten verwalten',
                self::MAPS_MANAGE_LAYERS => 'Ebenen verwalten',
            ],
            'Räume' => [
                self::ROOMS_CREATE => 'Räume erstellen',
                self::ROOMS_EDIT => 'Räume bearbeiten',
                self::ROOMS_VIEW => 'Räume ansehen',
                self::ROOMS_DELETE => 'Räume löschen',
            ],
            'Skript' => [
                self::SCRIPT_EDIT => 'Skripte bearbeiten',
                self::SCRIPT_MANAGE_ROLES => 'Skript-Rollen verwalten & zuweisen',
            ],
            'Marketing' => [
                self::MARKETING_MANAGE_SIZES => 'Plakat-Größen verwalten',
                self::MARKETING_MANAGE_ENTRIES => 'Fremde Verteil-Einträge bearbeiten/löschen',
            ],
            'Mitteilungen' => [
                self::ANNOUNCEMENTS_CREATE => 'Mitteilungen erstellen',
            ],
            'Inventar' => [
                self::INVENTORY_CREATE => 'Inventar anlegen',
                self::INVENTORY_EDIT => 'Inventar bearbeiten',
                self::INVENTORY_DELETE => 'Inventar löschen',
                self::INVENTORY_MANAGE_TYPES => 'Inventar-Arten verwalten',
                self::INVENTORY_REQUEST_RENTAL => 'Ausleihe beantragen',
                self::INVENTORY_APPROVE_RENTALS => 'Ausleih-Anträge genehmigen & verwalten',
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
