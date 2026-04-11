<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Complex Scenarios
 *
 * Feature: expense-management-system
 * Property 7: Unified Ledger Balance Calculation (complex scenarios)
 * Property 8: Balance Recalculation on Changes (complex scenarios)
 * Property 13: Member Relationship Integrity
 * Validates: Requirements 4.1, 4.2, 17.1
 */
class ExpenseComplexPropertyTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMemberWithTransactions(float $openingBalance = 0.0): Member
    {
        $member = Member::create([
            'name'            => 'Member ' . uniqid(),
            'email'           => 'member' . uniqid() . '@test.com',
            'phone'           => '05' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            'opening_balance' => $openingBalance,
            'balance'         => $openingBalance,
            'join_date'       => now()->subMonths(mt_rand(1, 12))->format('Y-m-d'),
        ]);

        // Add some random transactions to make it more realistic
        $transactionCount = mt_rand(1, 5);
        $runningBalance = $openingBalance;

        for ($i = 0; $i < $transactionCount; $i++) {
            $type = fake()->randomElement(['deposit', 'withdraw', 'adjustment']);
            $amount = round(mt_rand(100, 5000) / 100, 2);
            
            if ($type === 'withdraw' && $amount > $runningBalance) {
                $amount = round($runningBalance * 0.5, 2); // Don't overdraw
            }

            $isCredit = in_array($type, ['deposit', 'adjustment']);
            $runningBalance += $isCredit ? $amount : -$amount;

            Transaction::create([
                'reference'      => 'TX-' . uniqid() . '-' . $i,
                'member_id'      => $member->id,
                'type'           => $type,
                'amount'         => $amount,
                'transaction_at' => fake()->dateTimeBetween('-1 year', 'now'),
                'note'           => 'Test transaction',
                'status'         => 'completed',
                'balance_after'  => $runningBalance,
            ]);
        }

        // Update member balance to match transactions
        $member->update(['balance' => $runningBalance]);
        
        return $member;
    }

    private function basePayload(string $type, float $amount): array
    {
        return [
            'expense_type'     => $type,
            'category'         => 'Test Category',
            'amount'           => $amount,
            'payment_method'   => 'cash',
            'expense_datetime' => now()->toDateTimeString(),
        ];
    }

    // ─── Property 7: Unified Ledger Balance Calculation ──────────────────────
    // For any member with existing transactions and expenses, the calculated
    // balance should equal: opening_balance + deposits - withdrawals - 
    // shared_splits - personal_expenses + paid_expenses
    // Validates: Requirements 4.1

    /**
     * Property 7: Unified Ledger Balance Calculation (complex scenarios)
     * Validates: Requirements 4.1
     *
     * For any member with existing transactions, adding various expense types
     * should maintain the unified ledger balance calculation accuracy.
     * Runs 100 iterations.
     */
    public function test_unified_ledger_balance_with_mixed_transactions_and_expenses(): void
    {
        // Feature: expense-management-system, Property 7: Unified Ledger Balance Calculation

        for ($i = 0; $i < 100; $i++) {
            $member = $this->createMemberWithTransactions(round(mt_rand(0, 100000) / 100, 2));
            
            $balanceBeforeExpenses = (float) $member->balance;
            $calculatedBeforeExpenses = $member->calculated_balance;

            // Verify calculated balance matches stored balance before expenses
            $this->assertEquals(
                (int) round($balanceBeforeExpenses * 100),
                (int) round($calculatedBeforeExpenses * 100),
                "Iteration {$i}: Calculated balance should match stored balance before expenses"
            );

            // Add a personal expense
            $personalAmount = round(mt_rand(100, 5000) / 100, 2);
            $personalPayload = $this->basePayload('personal', $personalAmount);
            $personalPayload['affected_member_id'] = $member->id;

            $personalResponse = $this->postJson('/api/expenses', $personalPayload);
            $personalResponse->assertStatus(201);

            // Add a shared expense where this member participates
            $otherMember = $this->createMemberWithTransactions(1000.0);
            $sharedAmount = round(mt_rand(200, 10000) / 100, 2);
            
            $sharedPayload = $this->basePayload('shared', $sharedAmount);
            $sharedPayload['split_type'] = 'equal';
            $sharedPayload['members'] = [
                ['id' => $member->id],
                ['id' => $otherMember->id],
            ];

            $sharedResponse = $this->postJson('/api/expenses', $sharedPayload);
            $sharedResponse->assertStatus(201);

            $sharedExpenseId = $sharedResponse->json('id');
            $memberSplit = (float) ExpenseSplit::where('expense_id', $sharedExpenseId)
                ->where('member_id', $member->id)->value('amount');

            // Add an operational expense where this member is the payer
            $operationalAmount = round(mt_rand(100, 3000) / 100, 2);
            $operationalPayload = $this->basePayload('operational', $operationalAmount);
            $operationalPayload['payer_id'] = $member->id;

            $operationalResponse = $this->postJson('/api/expenses', $operationalPayload);
            $operationalResponse->assertStatus(201);

            // Refresh and verify unified ledger calculation
            $member->refresh();
            $finalCalculatedBalance = $member->calculated_balance;
            $finalStoredBalance = (float) $member->balance;

            // Expected balance calculation
            $expectedBalance = $balanceBeforeExpenses 
                - $personalAmount      // personal expense reduces balance
                - $memberSplit         // shared split reduces balance
                + $operationalAmount;  // payer contribution increases balance

            $this->assertEquals(
                (int) round($expectedBalance * 100),
                (int) round($finalStoredBalance * 100),
                "Iteration {$i}: Stored balance should match expected calculation"
            );

            $this->assertEquals(
                (int) round($finalStoredBalance * 100),
                (int) round($finalCalculatedBalance * 100),
                "Iteration {$i}: Calculated balance should match stored balance after expenses"
            );
        }
    }

    // ─── Property 8: Balance Recalculation on Changes ────────────────────────
    // For any expense creation or deletion with existing member activity,
    // all affected members' balances should be recalculated correctly.
    // Validates: Requirements 4.2

    /**
     * Property 8: Balance Recalculation on Changes (complex scenarios)
     * Validates: Requirements 4.2
     *
     * For any member with existing transactions and expenses, adding and removing
     * new expenses should correctly recalculate balances.
     * Runs 100 iterations.
     */
    public function test_balance_recalculation_with_existing_member_activity(): void
    {
        // Feature: expense-management-system, Property 8: Balance Recalculation on Changes

        for ($i = 0; $i < 100; $i++) {
            // Create member with existing transactions and expenses
            $member1 = $this->createMemberWithTransactions(round(mt_rand(50000, 200000) / 100, 2));
            $member2 = $this->createMemberWithTransactions(round(mt_rand(30000, 150000) / 100, 2));

            // Create some existing expenses
            $existingPersonalPayload = $this->basePayload('personal', 300.00);
            $existingPersonalPayload['affected_member_id'] = $member1->id;
            $this->postJson('/api/expenses', $existingPersonalPayload)->assertStatus(201);

            $existingSharedPayload = $this->basePayload('shared', 400.00);
            $existingSharedPayload['split_type'] = 'equal';
            $existingSharedPayload['members'] = [
                ['id' => $member1->id],
                ['id' => $member2->id],
            ];
            $this->postJson('/api/expenses', $existingSharedPayload)->assertStatus(201);

            // Capture balances after existing expenses
            $member1->refresh();
            $member2->refresh();
            $balance1AfterExisting = (float) $member1->balance;
            $balance2AfterExisting = (float) $member2->balance;

            // Add new expense
            $newAmount = round(mt_rand(500, 5000) / 100, 2);
            $newPayload = $this->basePayload('shared', $newAmount);
            $newPayload['split_type'] = 'equal';
            $newPayload['payer_id'] = $member1->id;
            $newPayload['members'] = [
                ['id' => $member1->id],
                ['id' => $member2->id],
            ];

            $newResponse = $this->postJson('/api/expenses', $newPayload);
            $newResponse->assertStatus(201);
            $newExpenseId = $newResponse->json('id');

            // Verify balance changes
            $member1->refresh();
            $member2->refresh();

            // Get actual split amounts from database
            $member1Split = (float) ExpenseSplit::where('expense_id', $newExpenseId)
                ->where('member_id', $member1->id)->value('amount');
            $member2Split = (float) ExpenseSplit::where('expense_id', $newExpenseId)
                ->where('member_id', $member2->id)->value('amount');

            $expectedBalance1 = round($balance1AfterExisting + $newAmount - $member1Split, 2); // payer + split
            $expectedBalance2 = round($balance2AfterExisting - $member2Split, 2);

            $this->assertEquals(
                (int) round($expectedBalance1 * 100),
                (int) round((float) $member1->balance * 100),
                "Iteration {$i}: Member1 balance should be correctly recalculated after new expense"
            );

            $this->assertEquals(
                (int) round($expectedBalance2 * 100),
                (int) round((float) $member2->balance * 100),
                "Iteration {$i}: Member2 balance should be correctly recalculated after new expense"
            );

            // Delete the new expense and verify restoration
            $deleteResponse = $this->deleteJson("/api/expenses/{$newExpenseId}");
            $deleteResponse->assertStatus(200);

            $member1->refresh();
            $member2->refresh();

            $this->assertEquals(
                (int) round($balance1AfterExisting * 100),
                (int) round((float) $member1->balance * 100),
                "Iteration {$i}: Member1 balance should be restored after expense deletion"
            );

            $this->assertEquals(
                (int) round($balance2AfterExisting * 100),
                (int) round((float) $member2->balance * 100),
                "Iteration {$i}: Member2 balance should be restored after expense deletion"
            );
        }
    }

    // ─── Property 13: Member Relationship Integrity ──────────────────────────
    // For any expense, all referenced member_ids should exist in the members
    // table and relationships should be maintained correctly.
    // Validates: Requirements 17.1

    /**
     * Property 13: Member Relationship Integrity
     * Validates: Requirements 17.1
     *
     * For any expense with member references, all relationships should be
     * valid and accessible through Eloquent relationships.
     * Runs 100 iterations.
     */
    public function test_member_relationship_integrity_across_expense_types(): void
    {
        // Feature: expense-management-system, Property 13: Member Relationship Integrity

        for ($i = 0; $i < 100; $i++) {
            $memberCount = mt_rand(3, 6);
            $members = [];
            
            for ($j = 0; $j < $memberCount; $j++) {
                $members[] = $this->createMemberWithTransactions(round(mt_rand(10000, 100000) / 100, 2));
            }

            $payer = $members[0];
            $affectedMember = $members[1];
            $sharedMembers = array_slice($members, 2);

            // Create personal expense
            $personalPayload = $this->basePayload('personal', 200.00);
            $personalPayload['affected_member_id'] = $affectedMember->id;
            $personalPayload['payer_id'] = $payer->id;

            $personalResponse = $this->postJson('/api/expenses', $personalPayload);
            $personalResponse->assertStatus(201);
            $personalExpenseId = $personalResponse->json('id');

            // Create shared expense
            $sharedPayload = $this->basePayload('shared', 600.00);
            $sharedPayload['split_type'] = 'equal';
            $sharedPayload['payer_id'] = $payer->id;
            $sharedPayload['members'] = array_map(fn($m) => ['id' => $m->id], $sharedMembers);

            $sharedResponse = $this->postJson('/api/expenses', $sharedPayload);
            $sharedResponse->assertStatus(201);
            $sharedExpenseId = $sharedResponse->json('id');

            // Verify personal expense relationships
            $personalExpense = Expense::with(['payer', 'affectedMember'])->find($personalExpenseId);
            
            $this->assertNotNull($personalExpense->payer, "Iteration {$i}: Personal expense should have payer relationship");
            $this->assertEquals($payer->id, $personalExpense->payer->id, "Iteration {$i}: Payer relationship should be correct");
            
            $this->assertNotNull($personalExpense->affectedMember, "Iteration {$i}: Personal expense should have affected member relationship");
            $this->assertEquals($affectedMember->id, $personalExpense->affectedMember->id, "Iteration {$i}: Affected member relationship should be correct");

            // Verify shared expense relationships
            $sharedExpense = Expense::with(['payer', 'splits.member'])->find($sharedExpenseId);
            
            $this->assertNotNull($sharedExpense->payer, "Iteration {$i}: Shared expense should have payer relationship");
            $this->assertEquals($payer->id, $sharedExpense->payer->id, "Iteration {$i}: Shared expense payer should be correct");
            
            $this->assertCount(count($sharedMembers), $sharedExpense->splits, "Iteration {$i}: Shared expense should have correct number of splits");
            
            foreach ($sharedExpense->splits as $split) {
                $this->assertNotNull($split->member, "Iteration {$i}: Each split should have member relationship");
                $this->assertContains($split->member_id, array_map(fn($m) => $m->id, $sharedMembers), "Iteration {$i}: Split member should be in original members list");
            }

            // Verify reverse relationships
            $payerExpenses = $payer->paidExpenses;
            $this->assertGreaterThanOrEqual(2, $payerExpenses->count(), "Iteration {$i}: Payer should have paid expenses");
            
            $affectedMemberPersonalExpenses = $affectedMember->personalExpenses;
            $this->assertGreaterThanOrEqual(1, $affectedMemberPersonalExpenses->count(), "Iteration {$i}: Affected member should have personal expenses");

            foreach ($sharedMembers as $member) {
                $memberSplits = $member->expenseSplits;
                $this->assertGreaterThanOrEqual(1, $memberSplits->count(), "Iteration {$i}: Shared member should have expense splits");
            }
        }
    }

    /**
     * Property 13: Member Relationship Integrity - orphaned references
     * Validates: Requirements 17.1
     *
     * The system should handle member deletion gracefully by setting
     * foreign keys to null rather than breaking relationships.
     */
    public function test_member_deletion_maintains_expense_integrity(): void
    {
        // Feature: expense-management-system, Property 13: Member Relationship Integrity

        $payer = $this->createMemberWithTransactions(5000.00);
        $affected = $this->createMemberWithTransactions(3000.00);

        // Create expenses with these members
        $personalPayload = $this->basePayload('personal', 300.00);
        $personalPayload['affected_member_id'] = $affected->id;
        $personalPayload['payer_id'] = $payer->id;

        $response = $this->postJson('/api/expenses', $personalPayload);
        $response->assertStatus(201);
        $expenseId = $response->json('id');

        // Force delete the payer (not soft delete)
        $payer->forceDelete();

        // Expense should still exist but payer should be null
        $expense = Expense::find($expenseId);
        $this->assertNotNull($expense, 'Expense should still exist after payer deletion');
        $this->assertNull($expense->payer_id, 'Payer ID should be null after member deletion');
        $this->assertNull($expense->payer, 'Payer relationship should be null');

        // Affected member should still be accessible
        $this->assertNotNull($expense->affectedMember, 'Affected member should still be accessible');
        $this->assertEquals($affected->id, $expense->affected_member_id);
    }
}