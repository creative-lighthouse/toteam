<?php

namespace App\Tests\Money;

use App\Money\MoneyHistory;
use SilverStripe\Security\Member;

/**
 * Buchungsverlauf (entries-Endpunkt): Seitenweises Laden ohne Lücken/Doppelte,
 * Sortierung, Filter und Suche über alle Buchungen.
 */
class MoneyEntriesTest extends MoneyApiTestCase
{
    public function testPagingReturnsEveryEntryExactlyOnce(): void
    {
        $account = $this->createAccount();
        $created = [];
        for ($i = 1; $i <= 65; $i++) {
            // Mehrere Buchungen pro Tag, damit die Reihenfolge auch bei gleichem Datum stabil sein muss
            $created[] = $this->createEntry($account, 'Deposit', '1', null, [
                'ChangeDate' => sprintf('2026-01-%02d', intdiv($i, 3) + 1),
            ]);
        }

        $seen = [];
        $offset = 0;
        do {
            $page = $this->entries($account, ['offset' => $offset, 'limit' => 30]);
            $this->assertSame(65, $page['total']);
            $seen = array_merge($seen, array_column($page['entries'], 'ID'));
            $offset += 30;
        } while ($page['hasMore']);

        $this->assertCount(65, $seen);
        $this->assertSame(65, count(array_unique($seen)), 'Keine Buchung doppelt');
        $this->assertSame(array_reverse($created), $seen, 'Neueste zuerst, bei gleichem Datum nach ID');
        $this->assertSame(65, $this->account($account)['EntryCount']);
    }

    public function testSorting(): void
    {
        $account = $this->createAccount();
        $b = $this->createEntry($account, 'Deposit', '1', null, ['ChangeReason' => 'Bier', 'ChangeDate' => '2026-03-01']);
        $a = $this->createEntry($account, 'Deposit', '1', null, ['ChangeReason' => 'apfel', 'ChangeDate' => '2026-01-01']);
        $c = $this->createEntry($account, 'Deposit', '1', null, ['ChangeReason' => 'Chips', 'ChangeDate' => '2026-02-01']);
        // Einreichung: $c zuletzt erstellt, $b zuerst
        MoneyHistory::get()->byID($b)->update(['Created' => '2026-01-01 10:00:00'])->write();
        MoneyHistory::get()->byID($a)->update(['Created' => '2026-01-02 10:00:00'])->write();
        MoneyHistory::get()->byID($c)->update(['Created' => '2026-01-03 10:00:00'])->write();

        $this->assertSame([$b, $c, $a], $this->entryIDs($account, ['sort' => 'invoice']));
        $this->assertSame([$c, $a, $b], $this->entryIDs($account, ['sort' => 'submission']));
        $this->assertSame([$a, $b, $c], $this->entryIDs($account, ['sort' => 'alphabetical']));
    }

    public function testFilters(): void
    {
        $account = $this->createAccount(['RequiresApproval' => true]);
        $budget = $this->createBudget($account, 1000);
        $inBudget = $this->createEntry($account, 'Withdrawal', '10', $this->member, ['BudgetID' => $budget]);
        $noBudget = $this->createEntry($account, 'Withdrawal', '10', $this->member2);
        $pending = $this->createEntry($account, 'Deposit', '10', $this->member);
        $this->approve($inBudget);
        $this->approve($noBudget);

        $sorted = function (array $ids) {
            sort($ids);
            return $ids;
        };
        $this->assertSame($sorted([$inBudget, $pending]), $sorted($this->entryIDs($account, ['user' => $this->member->ID])));
        $this->assertSame([$inBudget], $this->entryIDs($account, ['budget' => $budget]));
        $this->assertSame($sorted([$noBudget, $pending]), $sorted($this->entryIDs($account, ['budget' => 'none'])));
        $this->assertSame([$pending], $this->entryIDs($account, ['status' => 'pending']));
        $this->assertSame($sorted([$inBudget, $noBudget]), $sorted($this->entryIDs($account, ['status' => 'approved'])));
        $this->assertSame([$inBudget], $this->entryIDs($account, ['user' => $this->member->ID, 'status' => 'approved']));
    }

    public function testSearchByTitleIsCaseInsensitive(): void
    {
        $account = $this->createAccount();
        $pizza = $this->createEntry($account, 'Withdrawal', '10', null, ['ChangeReason' => 'Pizza für Aufbau']);
        $this->createEntry($account, 'Withdrawal', '10', null, ['ChangeReason' => 'Getränke']);

        $this->assertSame([$pizza], $this->entryIDs($account, ['search' => 'pizza']));
        $this->assertSame([$pizza], $this->entryIDs($account, ['search' => 'AUFBAU']));
    }

    public function testSearchByAmountInCommonNotations(): void
    {
        $account = $this->createAccount();
        $small = $this->createEntry($account, 'Withdrawal', '12.50', null, ['ChangeReason' => 'a']);
        $big = $this->createEntry($account, 'Withdrawal', '1234.50', null, ['ChangeReason' => 'b']);
        $this->createEntry($account, 'Withdrawal', '7', null, ['ChangeReason' => 'c']);

        foreach (['12,5', '12,50', '12.50', '12.5 €'] as $query) {
            $this->assertContains($small, $this->entryIDs($account, ['search' => $query]), "Suche \"$query\"");
        }
        foreach (['1.234,50', '1234,50', '1234.5'] as $query) {
            $this->assertSame([$big], $this->entryIDs($account, ['search' => $query]), "Suche \"$query\"");
        }
    }

    public function testSearchByPersonUsesOnlyTheDisplayedName(): void
    {
        $account = $this->createAccount();
        $anna = $this->createEntry($account, 'Withdrawal', '10', $this->member);
        $this->createEntry($account, 'Withdrawal', '10', $this->member2);

        $this->assertSame([$anna], $this->entryIDs($account, ['search' => 'schmidt']));

        // Wer nur den Vornamen zeigt, darf über den Nachnamen nicht auffindbar sein
        Member::get()->byID($this->member->ID)->update(['NameVisibility' => 'first'])->write();
        $this->assertSame([], $this->entryIDs($account, ['search' => 'schmidt']));
        $this->assertSame([$anna], $this->entryIDs($account, ['search' => 'anna']));
    }

    public function testSearchTreatsWildcardsLiterally(): void
    {
        $account = $this->createAccount();
        $percent = $this->createEntry($account, 'Withdrawal', '10', null, ['ChangeReason' => '100% Rabatt']);
        $this->createEntry($account, 'Withdrawal', '10', null, ['ChangeReason' => '1000 Rabattmarken']);
        $this->createEntry($account, 'Withdrawal', '10', null, ['ChangeReason' => "O'Neill"]);

        $this->assertSame([$percent], $this->entryIDs($account, ['search' => '100%']));
        $this->assertSame([], $this->entryIDs($account, ['search' => '_']));
        $this->assertCount(1, $this->entryIDs($account, ['search' => "o'n"]));
    }

    public function testEntryUsersListsEveryoneWithEntries(): void
    {
        $account = $this->createAccount();
        $this->createEntry($account, 'Withdrawal', '10', $this->member);
        $this->createEntry($account, 'Withdrawal', '10', $this->member);
        $this->createEntry($account, 'Withdrawal', '10', $this->member2);

        $this->assertSame(
            [['ID' => $this->member->ID, 'Name' => 'Anna Schmidt'], ['ID' => $this->member2->ID, 'Name' => 'Ben Weber']],
            $this->account($account)['EntryUsers']
        );
    }

    public function testOutsidersCannotReadEntries(): void
    {
        $account = $this->createAccount();
        $this->createEntry($account, 'Withdrawal', '10');
        $outsider = Member::create(['FirstName' => 'Fremd', 'Email' => 'fremd@example.com']);
        $outsider->write();

        $this->assertSame(403, $this->api($outsider, 'GET', 'entries/' . $account)['_status']);
        $this->assertSame(403, $this->api($outsider, 'GET', 'account/' . $account)['_status']);
    }
}
