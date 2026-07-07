<?php

namespace App\Tasks;

use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use App\Teams\OrgRole;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Einmalig auszuführen (sake dev/tasks/MigrateOrgRolesTask): legt für jede Organisation
 * ohne eigene Rollen die 3 Standardrollen (Administrator/Moderator/Mitglied) an und
 * überführt bestehende OrganizationMembership.Role-Werte (admin/moderator/member) in
 * Rollen-Zuweisungen, ohne dass jemand Rechte verliert. Idempotent: Organisationen,
 * die bereits eigene Rollen haben, werden übersprungen.
 */
class MigrateOrgRolesTask extends BuildTask
{
    protected string $title = 'Organisations-Rollen migrieren';
    protected static string $description = 'Legt Standardrollen an und überführt bestehende member/moderator/admin-Mitgliedschaften.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $migrated = 0;
        $skipped = 0;

        foreach (Organization::get() as $org) {
            if (OrgRole::get()->filter('OrganizationID', $org->ID)->exists()) {
                $skipped++;
                continue;
            }

            // Rohes SQL, da CanBeChangedBy zum Zeitpunkt dieser Migration bereits aus dem
            // MoneyAccount-Modell entfernt ist (die Spalte existiert aber physisch noch,
            // da dev/build Spalten nie automatisch löscht).
            $tiers = DB::query(
                "SELECT DISTINCT CanBeChangedBy FROM MoneyAccount WHERE ParentID = " . (int) $org->ID
            )->column('CanBeChangedBy');
            $hasAllTier = in_array('All', $tiers, true);
            $hasModeratorTier = in_array('Moderators', $tiers, true);

            $memberMoneyPerms = $hasAllTier
                ? [OrgPermissions::MONEY_DEPOSITS_ENTER, OrgPermissions::MONEY_WITHDRAWALS_ENTER]
                : [];
            $moderatorMoneyPerms = ($hasAllTier || $hasModeratorTier)
                ? [OrgPermissions::MONEY_DEPOSITS_ENTER, OrgPermissions::MONEY_WITHDRAWALS_ENTER]
                : [];

            $admin = OrgRole::create();
            $admin->Title = 'Administrator';
            $admin->OrganizationID = $org->ID;
            $admin->SortOrder = 0;
            $admin->setPermissionCodes([OrgPermissions::ORG_ADMIN]);
            $admin->write();

            $moderator = OrgRole::create();
            $moderator->Title = 'Moderator';
            $moderator->OrganizationID = $org->ID;
            $moderator->SortOrder = 1;
            $moderator->setPermissionCodes([
                OrgPermissions::ORG_MANAGE_MEMBERS,
                OrgPermissions::TASKS_CREATE,
                OrgPermissions::TASKS_EDIT,
                OrgPermissions::TASKS_DELETE,
                OrgPermissions::MONEY_BUDGETS_MANAGE,
                OrgPermissions::MONEY_APPROVE_ENTRIES,
                OrgPermissions::CALENDAR_MANAGE,
                OrgPermissions::CALENDAR_DELETE,
                OrgPermissions::FOOD_MANAGE_MEALS,
                OrgPermissions::LINKS_MANAGE,
                OrgPermissions::MAPS_MANAGE_MAPS,
                OrgPermissions::MAPS_MANAGE_LAYERS,
                ...$moderatorMoneyPerms,
            ]);
            $moderator->write();

            $member = OrgRole::create();
            $member->Title = 'Mitglied';
            $member->OrganizationID = $org->ID;
            $member->SortOrder = 2;
            $member->setPermissionCodes([
                OrgPermissions::TASKS_CREATE,
                OrgPermissions::TASKS_EDIT,
                OrgPermissions::TASKS_DELETE,
                ...$memberMoneyPerms,
            ]);
            $member->write();

            foreach (OrganizationMembership::get()->filter('OrganizationID', $org->ID) as $membership) {
                $roleToAssign = match ($membership->Role) {
                    'admin' => $admin,
                    'moderator' => $moderator,
                    'member' => $member,
                    default => null, // applicant: unverändert lassen
                };

                if (!$roleToAssign) {
                    continue;
                }

                $membership->Roles()->add($roleToAssign);
                if ($membership->Role !== 'member') {
                    $membership->Role = 'member';
                    $membership->write();
                }
            }

            $migrated++;
            $output->writeln("Migriert: {$org->Title} (#{$org->ID})");
        }

        $output->writeln("Fertig. Migriert: {$migrated}, übersprungen (bereits migriert): {$skipped}.");
        return Command::SUCCESS;
    }
}
