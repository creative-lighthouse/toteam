<?php

namespace App\Tests\Money;

/**
 * Schulden der Kasse gegenüber Mitgliedern (account.Debts): freigegebene Ausgaben, die
 * jemand ausgelegt hat, minus Begleichungen — sowie der Filter status=open im Verlauf.
 */
class MoneyDebtsTest extends MoneyApiTestCase
{
    private function debts(int $account, $as = null): array
    {
        return $this->account($account, $as)['Debts'];
    }

    private function sums(float $total, float $paid, float $open): array
    {
        return ['Total' => $total, 'Paid' => $paid, 'Open' => $open];
    }

    public function testOwnDebtIsApprovedWithdrawalsMinusSettlements(): void
    {
        $account = $this->createAccount();
        $a = $this->createEntry($account, 'Withdrawal', '40', $this->member);
        $this->createEntry($account, 'Withdrawal', '60', $this->member);

        $this->assertEquals($this->sums(100, 0, 100), $this->debts($account, $this->member)['Own']);

        $this->settle($a, 25);
        $this->assertEquals($this->sums(100, 25, 75), $this->debts($account, $this->member)['Own']);

        $this->settle($a, 15);
        $this->assertEquals($this->sums(100, 40, 60), $this->debts($account, $this->member)['Own']);
    }

    public function testDepositsAndPendingEntriesAreNoDebt(): void
    {
        $account = $this->createAccount(['RequiresApproval' => true]);
        $this->approve($this->createEntry($account, 'Deposit', '50', $this->member));
        $pending = $this->createEntry($account, 'Withdrawal', '30', $this->member);

        $this->assertEquals($this->sums(0, 0, 0), $this->debts($account, $this->member)['Own']);

        $this->approve($pending);
        $this->assertEquals($this->sums(30, 0, 30), $this->debts($account, $this->member)['Own']);
    }

    public function testOverpaymentIsCappedPerEntry(): void
    {
        // Zu viel ausgezahlt bei einer Buchung verrechnet sich nicht mit einer anderen
        $account = $this->createAccount();
        $a = $this->createEntry($account, 'Withdrawal', '40', $this->member);
        $this->createEntry($account, 'Withdrawal', '60', $this->member);

        $this->settle($a, 50);

        $this->assertEquals($this->sums(100, 40, 60), $this->debts($account, $this->member)['Own']);
    }

    public function testDebtFollowsTheBookedPerson(): void
    {
        // Wer erfasst, ist egal — es zählt, für wen die Buchung erfasst wurde
        $account = $this->createAccount();
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->treasurer, ['UserID' => $this->member->ID]);

        $this->assertMoney(40.0, $this->debts($account, $this->member)['Own']['Open']);
        $this->assertMoney(0.0, $this->debts($account)['Own']['Open']);

        $this->updateEntry($entry, ['ChangeType' => 'Withdrawal', 'ChangeAmount' => '40', 'UserID' => $this->member2->ID]);
        $this->assertMoney(0.0, $this->debts($account, $this->member)['Own']['Open']);
        $this->assertMoney(40.0, $this->debts($account, $this->member2)['Own']['Open']);
    }

    public function testAllMembersOnlyForApprovers(): void
    {
        $account = $this->createAccount();
        $this->createEntry($account, 'Withdrawal', '40', $this->member);

        $forMember = $this->debts($account, $this->member);
        $this->assertArrayNotHasKey('Members', $forMember);
        $this->assertArrayNotHasKey('Total', $forMember);

        $this->assertArrayHasKey('Members', $this->debts($account));
    }

    public function testAllMembersSortedByOpenAmountAndTotalIncludesFullyPaid(): void
    {
        $account = $this->createAccount();
        $this->createEntry($account, 'Withdrawal', '20', $this->member);
        $this->createEntry($account, 'Withdrawal', '70', $this->member2);
        $paid = $this->createEntry($account, 'Withdrawal', '10', $this->treasurer);
        $this->settle($paid, 10);

        $debts = $this->debts($account);

        $this->assertSame(
            [$this->member2->ID, $this->member->ID],
            array_map(fn ($row) => $row['User']['ID'], $debts['Members']),
            'Nur Personen mit offenem Betrag, größter zuerst'
        );
        $this->assertMoney(70.0, $debts['Members'][0]['Open']);
        $this->assertEquals($this->sums(100, 10, 90), $debts['Total']);
    }

    public function testRejectingOrDeletingEntryRemovesDebtAndSettlements(): void
    {
        $account = $this->createAccount(['RequiresApproval' => true]);
        $deleted = $this->createEntry($account, 'Withdrawal', '40', $this->member);
        $this->approve($deleted);
        $this->settle($deleted, 10);
        $kept = $this->createEntry($account, 'Withdrawal', '25', $this->member);
        $this->approve($kept);

        $this->deleteEntry($deleted);

        $this->assertEquals($this->sums(25, 0, 25), $this->debts($account, $this->member)['Own']);
        $this->assertSame(0, \App\Money\MoneySettlement::get()->filter('EntryID', $deleted)->count());
    }

    public function testLoweringAmountBelowSettledClosesTheDebt(): void
    {
        $account = $this->createAccount();
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->member);
        $this->settle($entry, 30);

        $this->updateEntry($entry, ['ChangeType' => 'Withdrawal', 'ChangeAmount' => '25']);

        $this->assertEquals($this->sums(25, 25, 0), $this->debts($account, $this->member)['Own']);
        $this->assertSame([], $this->entryIDs($account, ['status' => 'open']));
    }

    public function testOnlyWithdrawalsCanBeSettled(): void
    {
        $account = $this->createAccount();
        $deposit = $this->createEntry($account, 'Deposit', '40', $this->member);

        $response = $this->api($this->treasurer, 'POST', 'entrySettle/' . $deposit, [], ['Amount' => 40, 'PaymentMethod' => 'Bar']);

        $this->assertSame(400, $response['_status']);
    }

    public function testOnlyApproversCanSettle(): void
    {
        $account = $this->createAccount();
        $entry = $this->createEntry($account, 'Withdrawal', '40', $this->member);

        $response = $this->api($this->member, 'POST', 'entrySettle/' . $entry, [], ['Amount' => 40, 'PaymentMethod' => 'Bar']);

        $this->assertSame(403, $response['_status']);
        $this->assertMoney(40.0, $this->debts($account, $this->member)['Own']['Open']);
    }

    public function testOpenFilterMatchesTheDebts(): void
    {
        $account = $this->createAccount(['RequiresApproval' => true]);
        $open = $this->createEntry($account, 'Withdrawal', '40', $this->member);
        $partial = $this->createEntry($account, 'Withdrawal', '30', $this->member2);
        $settled = $this->createEntry($account, 'Withdrawal', '20', $this->member);
        $deposit = $this->createEntry($account, 'Deposit', '50', $this->member);
        foreach ([$open, $partial, $settled, $deposit] as $id) {
            $this->approve($id);
        }
        $this->createEntry($account, 'Withdrawal', '99', $this->member); // ausstehend
        $this->settle($partial, 10);
        $this->settle($settled, 20);

        $result = $this->entries($account, ['status' => 'open', 'limit' => 100]);
        $ids = array_column($result['entries'], 'ID');
        sort($ids);
        $this->assertSame([$open, $partial], $ids);

        // Summe der offenen Reste in der Liste == offene Schulden insgesamt
        $remaining = array_sum(array_map(fn ($e) => $e['ChangeAmount'] - $e['SettledAmount'], $result['entries']));
        $this->assertEqualsWithDelta($this->debts($account)['Total']['Open'], $remaining, 0.001);

        $this->assertSame([$open], $this->entryIDs($account, ['status' => 'open', 'user' => $this->member->ID]));
    }
}
