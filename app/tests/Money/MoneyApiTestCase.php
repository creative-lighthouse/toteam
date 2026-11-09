<?php

namespace App\Tests\Money;

use App\Auth\JwtHelper;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use App\Teams\OrgRole;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * Basis für die Tests der Kasse: spricht die echte API (api/v1/money) an, damit Rechte,
 * Freigaben und die zwischengespeicherten Salden (CachedCurrentBalance) mitgeprüft werden.
 *
 * Ausgangslage je Test: ein Verein mit
 *  - $treasurer: Administrator (darf alles, u. a. freigeben und begleichen)
 *  - $member:    darf nur Einnahmen/Ausgaben erfassen
 *  - $member2:   wie $member
 */
abstract class MoneyApiTestCase extends SapphireTest
{
    protected $usesDatabase = true;

    protected Organization $org;
    protected Member $treasurer;
    protected Member $member;
    protected Member $member2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['Title' => 'Testverein']);
        $this->org->write(); // legt die Standardrollen an

        $admin = OrgRole::get()->filter(['OrganizationID' => $this->org->ID, 'Title' => 'Administrator'])->first();

        $enterOnly = OrgRole::create(['Title' => 'Kasse erfassen', 'OrganizationID' => $this->org->ID]);
        $enterOnly->setPermissionCodes([OrgPermissions::MONEY_DEPOSITS_ENTER, OrgPermissions::MONEY_WITHDRAWALS_ENTER]);
        $enterOnly->write();

        $this->treasurer = $this->createOrgMember('Klara', 'Kasse', $admin);
        $this->member = $this->createOrgMember('Anna', 'Schmidt', $enterOnly);
        $this->member2 = $this->createOrgMember('Ben', 'Weber', $enterOnly);
    }

    protected function createOrgMember(string $firstName, string $surname, OrgRole $role): Member
    {
        $member = Member::create([
            'FirstName' => $firstName,
            'Surname' => $surname,
            'Email' => strtolower($firstName . '.' . $surname) . '@example.com',
        ]);
        $member->write();

        $membership = OrganizationMembership::create([
            'MemberID' => $member->ID,
            'OrganizationID' => $this->org->ID,
            'Role' => 'member',
        ]);
        $membership->write();
        $membership->Roles()->add($role);

        return $member;
    }

    /**
     * Geldbetrag auf den Cent genau. JSON macht aus 30.0 eine 30 (int), daher kein assertSame.
     */
    protected function assertMoney(float $expected, mixed $actual, string $message = ''): void
    {
        $this->assertIsNumeric($actual, $message);
        $this->assertSame((int) round($expected * 100), (int) round($actual * 100), $message ?: "Erwartet $expected, erhalten $actual");
    }

    // ── API ─────────────────────────────────────────────────────────────

    /**
     * Ruft die API als $as auf und liefert die dekodierte Antwort samt HTTP-Status unter '_status'.
     * $form: multipart-Felder ($_POST), $json: JSON-Body
     */
    protected function api(Member $as, string $method, string $path, array $form = [], ?array $json = null): array
    {
        $response = Director::test(
            'api/v1/money/' . $path,
            $form,
            null,
            $method,
            $json === null ? null : json_encode($json),
            ['Authorization' => 'Bearer ' . JwtHelper::issueAccessToken((int) $as->ID)]
        );
        $data = json_decode((string) $response->getBody(), true) ?? [];
        $data['_status'] = $response->getStatusCode();
        return $data;
    }

    /** Wie api(), erwartet aber Erfolg (2xx) */
    protected function apiOk(Member $as, string $method, string $path, array $form = [], ?array $json = null): array
    {
        $data = $this->api($as, $method, $path, $form, $json);
        $this->assertLessThan(300, $data['_status'], "$method $path fehlgeschlagen: " . ($data['error'] ?? json_encode($data)));
        return $data;
    }

    protected function createAccount(array $fields = []): int
    {
        $data = $this->apiOk($this->treasurer, 'POST', 'accountStore', [], $fields + [
            'Title' => 'Vereinskasse',
            'OrganizationID' => $this->org->ID,
            'StartingAmount' => 0,
            'RequiresApproval' => false,
        ]);
        return (int) $data['data']['account']['ID'];
    }

    protected function createBudget(int $accountID, float $limit, bool $canBeOver = false, string $title = 'Deko'): int
    {
        $data = $this->apiOk($this->treasurer, 'POST', 'budgetStore', [], [
            'AccountID' => $accountID,
            'Title' => $title,
            'Budget' => $limit,
            'HasBudget' => true,
            'CanBeOverBudget' => $canBeOver,
        ]);
        return (int) $data['data']['budget']['ID'];
    }

    /** Erfasst eine Buchung (ChangeAmount als String, wie es das Formular schickt) und liefert ihre ID */
    protected function createEntry(int $accountID, string $type, string $amount, ?Member $as = null, array $fields = []): int
    {
        $data = $this->apiOk($as ?? $this->treasurer, 'POST', 'entryStore', $fields + [
            'AccountID' => $accountID,
            'ChangeType' => $type,
            'ChangeAmount' => $amount,
            'ChangeReason' => $type === 'Deposit' ? 'Einnahme' : 'Ausgabe',
        ]);
        return (int) $data['data']['entry']['ID'];
    }

    protected function updateEntry(int $entryID, array $fields, ?Member $as = null): array
    {
        return $this->apiOk($as ?? $this->treasurer, 'POST', 'entryUpdate/' . $entryID, $fields + [
            'ChangeReason' => 'Geändert',
        ]);
    }

    protected function approve(int $entryID, bool $approve = true): array
    {
        return $this->apiOk($this->treasurer, 'PUT', 'entryApprove/' . $entryID, [], ['approve' => $approve]);
    }

    protected function settle(int $entryID, float $amount): array
    {
        return $this->apiOk($this->treasurer, 'POST', 'entrySettle/' . $entryID, [], [
            'Amount' => $amount,
            'PaymentMethod' => 'Bar',
        ]);
    }

    protected function deleteEntry(int $entryID): array
    {
        return $this->apiOk($this->treasurer, 'DELETE', 'entry/' . $entryID);
    }

    protected function account(int $accountID, ?Member $as = null): array
    {
        return $this->apiOk($as ?? $this->treasurer, 'GET', 'account/' . $accountID)['account'];
    }

    protected function balance(int $accountID): float
    {
        return $this->account($accountID)['CachedCurrentBalance'];
    }

    protected function budget(int $accountID, int $budgetID): array
    {
        foreach ($this->account($accountID)['Budgets'] as $budget) {
            if ($budget['ID'] === $budgetID) {
                return $budget;
            }
        }
        $this->fail("Budget $budgetID nicht in Kasse $accountID");
    }

    /** Buchungsverlauf (entries-Endpunkt) mit Query-Parametern */
    protected function entries(int $accountID, array $query = [], ?Member $as = null): array
    {
        return $this->apiOk($as ?? $this->treasurer, 'GET', 'entries/' . $accountID . '?' . http_build_query($query));
    }

    /** IDs aller Buchungen eines entries-Aufrufs */
    protected function entryIDs(int $accountID, array $query = []): array
    {
        return array_column($this->entries($accountID, $query + ['limit' => 100])['entries'], 'ID');
    }
}
