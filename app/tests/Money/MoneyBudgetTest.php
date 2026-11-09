<?php

namespace App\Tests\Money;

/**
 * Budgets: Spent = freigegebene Ausgaben im Budget, PendingAmount = noch nicht
 * freigegebene Ausgaben, Remaining = Limit − Spent; Sperre bei Überschreitung.
 */
class MoneyBudgetTest extends MoneyApiTestCase
{
    public function testSpentCountsOnlyApprovedWithdrawals(): void
    {
        $account = $this->createAccount(['RequiresApproval' => true]);
        $budget = $this->createBudget($account, 100);

        $this->approve($this->createEntry($account, 'Withdrawal', '30', null, ['BudgetID' => $budget]));
        $this->approve($this->createEntry($account, 'Deposit', '20', null, ['BudgetID' => $budget]));
        $this->createEntry($account, 'Withdrawal', '15', $this->member, ['BudgetID' => $budget]); // ausstehend
        $this->approve($this->createEntry($account, 'Withdrawal', '50')); // ohne Budget

        $data = $this->budget($account, $budget);
        $this->assertMoney(30.0, $data['Spent'], 'Einnahmen, ausstehende und budgetlose Ausgaben zählen nicht');
        $this->assertMoney(15.0, $data['PendingAmount']);
        $this->assertMoney(70.0, $data['Remaining']);
    }

    public function testApprovingPendingEntryMovesItFromPendingToSpent(): void
    {
        $account = $this->createAccount(['RequiresApproval' => true]);
        $budget = $this->createBudget($account, 100);
        $entry = $this->createEntry($account, 'Withdrawal', '15', $this->member, ['BudgetID' => $budget]);

        $this->approve($entry);

        $data = $this->budget($account, $budget);
        $this->assertMoney(15.0, $data['Spent']);
        $this->assertMoney(0.0, $data['PendingAmount']);
    }

    public function testMovingEntryBetweenBudgetsUpdatesBoth(): void
    {
        $account = $this->createAccount();
        $deko = $this->createBudget($account, 100, false, 'Deko');
        $essen = $this->createBudget($account, 100, false, 'Essen');
        $entry = $this->createEntry($account, 'Withdrawal', '40', null, ['BudgetID' => $deko]);

        $this->updateEntry($entry, ['ChangeType' => 'Withdrawal', 'ChangeAmount' => '40', 'BudgetID' => $essen]);
        $this->assertMoney(0.0, $this->budget($account, $deko)['Spent']);
        $this->assertMoney(40.0, $this->budget($account, $essen)['Spent']);

        $this->updateEntry($entry, ['ChangeType' => 'Withdrawal', 'ChangeAmount' => '40', 'BudgetID' => 0]);
        $this->assertMoney(0.0, $this->budget($account, $essen)['Spent'], 'Budget entfernt');
    }

    public function testEditingAndDeletingEntriesUpdatesSpent(): void
    {
        $account = $this->createAccount();
        $budget = $this->createBudget($account, 100);
        $entry = $this->createEntry($account, 'Withdrawal', '40', null, ['BudgetID' => $budget]);

        $this->updateEntry($entry, ['ChangeType' => 'Withdrawal', 'ChangeAmount' => '25', 'BudgetID' => $budget]);
        $this->assertMoney(25.0, $this->budget($account, $budget)['Spent']);

        $this->deleteEntry($entry);
        $this->assertMoney(0.0, $this->budget($account, $budget)['Spent']);
    }

    public function testDeletingBudgetKeepsEntriesAndBalance(): void
    {
        $account = $this->createAccount(['StartingAmount' => 100]);
        $budget = $this->createBudget($account, 100);
        $entry = $this->createEntry($account, 'Withdrawal', '40', null, ['BudgetID' => $budget]);

        $this->apiOk($this->treasurer, 'DELETE', 'budgetRemove/' . $budget);

        $data = $this->account($account);
        $this->assertSame([], $data['Budgets']);
        $this->assertMoney(60.0, $data['CachedCurrentBalance']);
        $this->assertSame([$entry], $this->entryIDs($account, ['budget' => 'none']));
    }

    public function testWithdrawalOverTheLimitIsRejected(): void
    {
        $account = $this->createAccount();
        $budget = $this->createBudget($account, 100);
        $this->createEntry($account, 'Withdrawal', '60', null, ['BudgetID' => $budget]);

        $response = $this->api($this->treasurer, 'POST', 'entryStore', [
            'AccountID' => $account, 'ChangeType' => 'Withdrawal', 'ChangeAmount' => '40.01',
            'ChangeReason' => 'Zu viel', 'BudgetID' => $budget,
        ]);
        $this->assertSame(400, $response['_status']);

        // Genau bis zum Limit ist erlaubt
        $this->createEntry($account, 'Withdrawal', '40', null, ['BudgetID' => $budget]);
        $data = $this->budget($account, $budget);
        $this->assertMoney(100.0, $data['Spent']);
        $this->assertMoney(0.0, $data['Remaining']);
    }

    public function testBudgetThatMayBeExceededShowsNegativeRemaining(): void
    {
        $account = $this->createAccount();
        $budget = $this->createBudget($account, 50, true);

        $this->createEntry($account, 'Withdrawal', '80', null, ['BudgetID' => $budget]);

        $data = $this->budget($account, $budget);
        $this->assertMoney(80.0, $data['Spent']);
        $this->assertMoney(-30.0, $data['Remaining']);
    }

    public function testBudgetWithoutLimitTracksSpendingWithoutRemaining(): void
    {
        $account = $this->createAccount();
        $data = $this->apiOk($this->treasurer, 'POST', 'budgetStore', [], [
            'AccountID' => $account, 'Title' => 'Sonstiges', 'HasBudget' => false,
        ]);
        $budget = (int) $data['data']['budget']['ID'];

        $this->createEntry($account, 'Withdrawal', '999', null, ['BudgetID' => $budget]);

        $data = $this->budget($account, $budget);
        $this->assertMoney(999.0, $data['Spent']);
        $this->assertNull($data['Remaining']);
    }

    public function testBudgetOfAnotherAccountCannotBeUsed(): void
    {
        $account = $this->createAccount(['Title' => 'A']);
        $other = $this->createAccount(['Title' => 'B']);
        $foreignBudget = $this->createBudget($other, 100);

        $response = $this->api($this->treasurer, 'POST', 'entryStore', [
            'AccountID' => $account, 'ChangeType' => 'Withdrawal', 'ChangeAmount' => '10',
            'ChangeReason' => 'x', 'BudgetID' => $foreignBudget,
        ]);

        $this->assertSame(400, $response['_status']);
        $this->assertMoney(0.0, $this->budget($other, $foreignBudget)['Spent']);
    }

    public function testBudgetEntriesListsAllEntriesOfTheBudget(): void
    {
        $account = $this->createAccount();
        $budget = $this->createBudget($account, 1000);
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createEntry($account, 'Withdrawal', '10', null, ['BudgetID' => $budget]);
        }
        $this->createEntry($account, 'Withdrawal', '10');

        $data = $this->apiOk($this->member, 'GET', 'budgetEntries/' . $budget);

        $listed = array_column($data['entries'], 'ID');
        sort($listed);
        $this->assertSame($ids, $listed);
        $this->assertMoney(30.0, $data['budget']['Spent']);
    }
}
