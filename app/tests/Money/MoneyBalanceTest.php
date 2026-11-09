<?php

namespace App\Tests\Money;

/**
 * Kontostand einer Kasse (CachedCurrentBalance): Startbetrag + freigegebene Einnahmen
 * − freigegebene Ausgaben, nach jeder Änderung neu berechnet.
 */
class MoneyBalanceTest extends MoneyApiTestCase
{
    public function testBalanceIsStartingAmountPlusDepositsMinusWithdrawals(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100]);
        $this->assertMoney(100.0, $this->balance($account));

        $this->createEntry($account, 'Deposit', '50.25');
        $this->createEntry($account, 'Withdrawal', '20.10');
        $this->createEntry($account, 'Deposit', '0.10');

        $this->assertMoney(130.25, $this->balance($account));
    }

    public function testBalanceCanBecomeNegative(): void
    {
        $account = $this->createAccount(['StartingAmount' => 10]);
        $this->createEntry($account, 'Withdrawal', '25');

        $this->assertMoney(-15.0, $this->balance($account));
    }

    public function testGermanDecimalCommaIsParsed(): void
    {
        $account = $this->createAccount();
        $this->createEntry($account, 'Deposit', '12,50');

        $this->assertMoney(12.5, $this->balance($account));
    }

    public function testManySmallAmountsAddUpWithoutRoundingErrors(): void
    {
        $account = $this->createAccount();
        for ($i = 0; $i < 30; $i++) {
            $this->createEntry($account, 'Deposit', '0.10');
        }
        $this->createEntry($account, 'Withdrawal', '0.20');

        $this->assertMoney(2.8, $this->balance($account));
    }

    public function testPendingEntriesOnlyCountAfterApproval(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100, 'RequiresApproval' => true]);
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->member);

        $this->assertMoney(100.0, $this->balance($account), 'Nicht freigegebene Buchungen zählen nicht');

        $this->approve($entry);
        $this->assertMoney(60.0, $this->balance($account));
    }

    public function testRejectedEntryIsRemovedAndNeverCounts(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100, 'RequiresApproval' => true]);
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->member);

        $this->approve($entry, false);

        $this->assertMoney(100.0, $this->balance($account));
        $this->assertSame(0, $this->account($account)['EntryCount']);
    }

    public function testPendingEntryEditedBeforeApprovalCountsWithNewAmount(): void
    {
        $account = $this->createAccount(['RequiresApproval' => true]);
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->member);

        // Eigene, noch nicht freigegebene Buchungen darf man selbst ändern
        $this->updateEntry($entry, ['ChangeType' => 'Withdrawal', 'ChangeAmount' => '35'], $this->member);
        $this->approve($entry);

        $this->assertMoney(-35.0, $this->balance($account));
    }

    public function testEditingApprovedEntryRecalculates(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100]);
        $entry = $this->createEntry($account, 'Deposit', '50');
        $this->assertMoney(150.0, $this->balance($account));

        $this->updateEntry($entry, ['ChangeType' => 'Deposit', 'ChangeAmount' => '30']);
        $this->assertMoney(130.0, $this->balance($account), 'Betrag geändert');

        $this->updateEntry($entry, ['ChangeType' => 'Withdrawal', 'ChangeAmount' => '30']);
        $this->assertMoney(70.0, $this->balance($account), 'Einnahme zu Ausgabe geändert');
    }

    public function testDeletingApprovedEntryRecalculates(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100]);
        $keep = $this->createEntry($account, 'Withdrawal', '30');
        $delete = $this->createEntry($account, 'Withdrawal', '20');
        $this->assertMoney(50.0, $this->balance($account));

        $this->deleteEntry($delete);

        $this->assertMoney(70.0, $this->balance($account));
        $this->assertSame([$keep], $this->entryIDs($account));
    }

    public function testChangingStartingAmountRecalculates(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100]);
        $this->createEntry($account, 'Withdrawal', '30');

        $this->apiOk($this->treasurer, 'PUT', 'accountUpdate/' . $account, [], ['StartingAmount' => 250]);

        $this->assertMoney(220.0, $this->balance($account));
    }

    public function testAccountsAreCalculatedIndependently(): void
    {
        $a = $this->createAccount(['Title' => 'A', 'StartingAmount' => 10]);
        $b = $this->createAccount(['Title' => 'B', 'StartingAmount' => 20]);

        $this->createEntry($a, 'Deposit', '5');
        $this->createEntry($b, 'Withdrawal', '7');

        $this->assertMoney(15.0, $this->balance($a));
        $this->assertMoney(13.0, $this->balance($b));
    }

    public function testSettlementsDoNotChangeBalance(): void
    {
        // Die Ausgabe hat den Kontostand schon beim Buchen gesenkt; die Begleichung
        // ist nur die Auszahlung an die Person, die das Geld ausgelegt hat
        $account = $this->createAccount(['StartingAmount' => 100]);
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->member);

        $this->settle($entry, 40);

        $this->assertMoney(60.0, $this->balance($account));
    }

    public function testOverviewShowsSameBalanceAsDetail(): void
    {
        $account = $this->createAccount(['StartingAmount' => 5]);
        $this->createEntry($account, 'Deposit', '7.5');

        $overview = $this->apiOk($this->member, 'GET', '')['accounts'];
        $this->assertCount(1, $overview);
        $this->assertMoney(12.5, $overview[0]['CachedCurrentBalance']);
        $this->assertMoney(12.5, $this->account($account, $this->member)['CachedCurrentBalance']);
    }

    public function testMembersWithoutRightsCannotChangeTheBalance(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100]);
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->member);

        // Freigegebene Buchungen darf nur ändern/löschen, wer freigeben darf
        $this->assertSame(403, $this->api($this->member, 'DELETE', 'entry/' . $entry)['_status']);
        $this->assertSame(403, $this->api($this->member, 'POST', 'entryUpdate/' . $entry, [
            'ChangeType' => 'Withdrawal', 'ChangeAmount' => '1', 'ChangeReason' => 'x',
        ])['_status']);

        $this->assertMoney(60.0, $this->balance($account));
    }

    public function testInvalidAmountsAreRejected(): void
    {
        $account = $this->createAccount();
        foreach (['0', '-5', 'abc', '1000000000'] as $amount) {
            $response = $this->api($this->treasurer, 'POST', 'entryStore', [
                'AccountID' => $account, 'ChangeType' => 'Deposit', 'ChangeAmount' => $amount, 'ChangeReason' => 'x',
            ]);
            $this->assertSame(400, $response['_status'], "Betrag \"$amount\" müsste abgelehnt werden");
        }
        $this->assertMoney(0.0, $this->balance($account));
    }

    // ── Mehrere auf einmal löschen (entryRemoveBulk) ────────────────────

    private function removeBulk(int $account, array $ids, $as = null): array
    {
        return $this->api($as ?? $this->treasurer, 'POST', 'entryRemoveBulk', [], ['AccountID' => $account, 'EntryIDs' => $ids]);
    }

    public function testBulkRemoveDeletesEntriesAndRecalculates(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100]);
        $budget = $this->createBudget($account, 500);
        $a = $this->createEntry($account, 'Withdrawal', '30', $this->member, ['BudgetID' => $budget]);
        $b = $this->createEntry($account, 'Deposit', '20');
        $keep = $this->createEntry($account, 'Withdrawal', '5');
        $this->settle($a, 10);

        $response = $this->removeBulk($account, [$a, $b]);

        $this->assertSame(200, $response['_status']);
        $this->assertSame([$a, $b], $response['data']['removed']);
        $this->assertSame(0, $response['data']['skipped']);
        $this->assertMoney(95, $this->balance($account));
        $this->assertMoney(0, $this->budget($account, $budget)['Spent']);
        $this->assertSame([$keep], $this->entryIDs($account));
        $this->assertSame(0, \App\Money\MoneySettlement::get()->filter('EntryID', $a)->count(), 'Begleichungen mitgelöscht');
    }

    public function testBulkRemoveOnlyWhatOneMayDelete(): void
    {
        // Mitglieder dürfen nur eigene, noch nicht freigegebene Buchungen löschen
        $account = $this->createAccount(['RequiresApproval' => true]);
        $ownPending = $this->createEntry($account, 'Withdrawal', '10', $this->member);
        $ownApproved = $this->createEntry($account, 'Withdrawal', '20', $this->member);
        $this->approve($ownApproved);
        $othersPending = $this->createEntry($account, 'Withdrawal', '30', $this->member2);
        $foreign = $this->createEntry($this->createAccount(['Title' => 'Andere']), 'Withdrawal', '40', $this->member);

        $response = $this->removeBulk($account, [$ownPending, $ownApproved, $othersPending, $foreign], $this->member);

        $this->assertSame([$ownPending], $response['data']['removed']);
        $this->assertSame(3, $response['data']['skipped']);
        $remaining = $this->entryIDs($account);
        sort($remaining);
        $this->assertSame([$ownApproved, $othersPending], $remaining);
        $this->assertMoney(-20, $this->balance($account));
        $this->assertNotNull(\App\Money\MoneyHistory::get()->byID($foreign), 'Buchung einer anderen Kasse bleibt');
    }

    public function testBulkRemoveRejectsOutsidersAndEmptySelection(): void
    {
        $account = $this->createAccount();
        $entry = $this->createEntry($account, 'Withdrawal', '10');
        $outsider = \SilverStripe\Security\Member::create(['FirstName' => 'Fremd', 'Email' => 'fremd@example.com']);
        $outsider->write();

        $this->assertSame(403, $this->removeBulk($account, [$entry], $outsider)['_status']);
        $this->assertSame(400, $this->removeBulk($account, [])['_status']);
        $this->assertSame([$entry], $this->entryIDs($account));
    }
}
