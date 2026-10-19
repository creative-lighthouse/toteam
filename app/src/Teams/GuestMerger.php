<?php

namespace App\Teams;

use App\Calendar\AppointmentParticipation;
use App\Calendar\AppointmentParticipationStash;
use App\Calendar\SchedulingPollOptionParticipation;
use App\Events\EventDayParticipation;
use App\Food\MealEater;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;

/**
 * Übernahme eines Gast-Mitglieds ({@see \App\Extensions\MemberExtension::$IsGuest}) durch eine
 * echte Person: alles, was am Gast hängt (Zu-/Absagen, Mahlzeiten, Rollen, Aufgaben, Verlauf …),
 * wird auf das echte Konto umgehängt, danach wird der Gast gelöscht.
 *
 * Umgehängt wird generisch über das Schema: jede has_one-Beziehung auf Member und jede
 * many_many-Join-Tabelle mit Member — neue Totems sind damit automatisch abgedeckt.
 * Bei Datensätzen, die es pro Person nur einmal gibt (UNIQUE_PER_MEMBER), gewinnt der
 * Eintrag der echten Person; der des Gastes wird vorher gelöscht.
 */
class GuestMerger
{
    /**
     * Klassen mit höchstens einem Eintrag pro Person und Bezug (z.B. eine Zusage pro Termin):
     * Klasse => Feld des Bezugs
     */
    private const UNIQUE_PER_MEMBER = [
        AppointmentParticipation::class          => 'ParentID',
        AppointmentParticipationStash::class     => 'ParentID',
        MealEater::class                         => 'ParentID',
        OrgEventInterest::class                  => 'EventID',
        SchedulingPollOptionParticipation::class => 'ParentID',
        EventDayParticipation::class             => 'ParentID',
    ];

    public static function merge(Member $guest, Member $real, Organization $org): void
    {
        if (!$guest->IsGuest || (int) $guest->GuestOrganizationID !== (int) $org->ID) {
            throw new \InvalidArgumentException('Das ist kein Gast dieser Organisation');
        }
        if ($real->IsGuest || (int) $real->ID === (int) $guest->ID) {
            throw new \InvalidArgumentException('Ein Gast kann nur von einer echten Person übernommen werden');
        }

        DB::get_conn()->withTransaction(function () use ($guest, $real, $org) {
            self::mergeMembership($guest, $real, $org);
            // Andere, die denselben Gast beansprucht hatten: Vormerkung verfällt (nicht umhängen)
            DB::prepared_query('UPDATE "OrganizationMembership" SET "ClaimGuestID" = 0 WHERE "ClaimGuestID" = ?', [$guest->ID]);
            self::dropConflicts((int) $guest->ID, (int) $real->ID);
            self::reassign((int) $guest->ID, (int) $real->ID);

            // Essensvorliebe aus dem Gast übernehmen, wenn die Person selbst keine angegeben hat
            if ((!$real->FoodPreference || $real->FoodPreference === 'None') && $guest->FoodPreference && $guest->FoodPreference !== 'None') {
                $real->FoodPreference = $guest->FoodPreference;
                $real->write();
            }

            Member::get()->byID($guest->ID)?->delete();
        });
    }

    /**
     * Die Mitgliedschaft des Gastes in der Organisation geht in der der echten Person auf:
     * Rollen werden zusammengeführt, die echte Person wird Mitglied.
     */
    private static function mergeMembership(Member $guest, Member $real, Organization $org): void
    {
        $guestMembership = OrganizationMembership::get()->filter(['OrganizationID' => $org->ID, 'MemberID' => $guest->ID])->first();
        $realMembership = OrganizationMembership::get()->filter(['OrganizationID' => $org->ID, 'MemberID' => $real->ID])->first();

        if (!$realMembership) {
            if ($guestMembership) {
                $guestMembership->MemberID = $real->ID;
                $guestMembership->ClaimGuestID = 0;
                $guestMembership->write();
            }
            return;
        }

        if ($guestMembership) {
            foreach ($guestMembership->Roles() as $role) {
                $realMembership->Roles()->add($role);
            }
            $guestMembership->Roles()->removeAll();
            $guestMembership->delete();
        }
        $realMembership->ClaimGuestID = 0;
        $realMembership->approve('member');
    }

    /** Einträge des Gastes löschen, zu denen die echte Person schon einen eigenen hat */
    private static function dropConflicts(int $guestID, int $realID): void
    {
        foreach (self::UNIQUE_PER_MEMBER as $class => $scopeField) {
            $taken = $class::get()->filter('MemberID', $realID)->column($scopeField);
            if (!$taken) {
                continue;
            }
            foreach ($class::get()->filter(['MemberID' => $guestID, $scopeField => $taken]) as $record) {
                $record->delete();
            }
        }
    }

    /** Alle has_one- und many_many-Verweise vom Gast auf die echte Person umhängen */
    private static function reassign(int $guestID, int $realID): void
    {
        $schema = DataObject::getSchema();
        $handledTables = [];

        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            // has_one: nur die direkt an der Klasse (oder per Extension) definierten, damit
            // geerbte Felder nicht mehrfach behandelt werden
            foreach (Config::inst()->get($class, 'has_one', Config::UNINHERITED) ?: [] as $relation => $spec) {
                $target = is_array($spec) ? ($spec['class'] ?? null) : $spec;
                if (!$target || !is_a($target, Member::class, true)) {
                    continue;
                }
                $field = $relation . 'ID';
                $table = $schema->tableForField($class, $field);
                if (!$table || isset($handledTables[$table . '.' . $field])) {
                    continue;
                }
                $handledTables[$table . '.' . $field] = true;
                DB::prepared_query(
                    sprintf('UPDATE "%s" SET "%s" = ? WHERE "%s" = ?', $table, $field, $field),
                    [$realID, $guestID]
                );
            }

            // many_many: Join-Tabellen, in denen Member auf einer der beiden Seiten steht
            foreach (array_keys(Config::inst()->get($class, 'many_many', Config::UNINHERITED) ?: []) as $relation) {
                $component = $schema->manyManyComponent($class, $relation);
                if (!$component || ClassInfo::exists($component['join'])) {
                    continue; // many_many "through": dessen has_one wurde oben schon umgehängt
                }
                $sides = [];
                if (is_a($component['childClass'], Member::class, true)) {
                    $sides[] = $component['childField'];
                }
                if (is_a($component['parentClass'], Member::class, true)) {
                    $sides[] = $component['parentField'];
                }
                foreach ($sides as $field) {
                    $key = $component['join'] . '.' . $field;
                    if (isset($handledTables[$key])) {
                        continue;
                    }
                    $handledTables[$key] = true;
                    // IGNORE: Verknüpfungen, die die echte Person schon hat, nicht doppeln …
                    DB::prepared_query(
                        sprintf('UPDATE IGNORE "%s" SET "%s" = ? WHERE "%s" = ?', $component['join'], $field, $field),
                        [$realID, $guestID]
                    );
                    // … und die übrig gebliebenen Verknüpfungen des Gastes entfernen
                    DB::prepared_query(
                        sprintf('DELETE FROM "%s" WHERE "%s" = ?', $component['join'], $field),
                        [$guestID]
                    );
                }
            }
        }
    }
}
