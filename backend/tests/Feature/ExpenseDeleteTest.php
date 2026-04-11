<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Expense Delete Operation
 *
 * Feature: expense-management-system
 * Property 12: Cascade Delete Splits
 * Property 8: Balance Recalculation on Changes (delete side)
 * Validates: Requirements 12.1, 12.2
 */
class ExpenseDeleteTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMember(float $openingBalance = 0.0): Member
    {
        return Member::create([
            'name'            => 'Member ' . uniqid(),
            'opening_balance' => $openingBalance,
            'balance'         => $openingBalance,
        ]);
    }

    private function createSharedExpense(array $memberIds, float $amount, ?int $payerId = null): Expense
    {
        $expense = Expense::create([
            'reference'        => Expense::generateReference(),
            'expense_type'     => 'shared',
            'category'         => 'Test',
            'amount'           => $amount,
            'payer_id'         => $payerId,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
        ]);

        $splitAmount = round($amount / count($memberIds), 2);
        $remainder   = (int) round($amount * 100) - (int) round($splitAmount * 100) * count($memberIds);

        foreach ($memberIds as $index => $memberId) {
            $share = $splitAmount + ($index === 0 ? $remainder / 100 : 0);
            ExpenseSplit::create([
                'expense_id' => $expense->id,
                'member_id'  => $memberId,
                'amount'     => round($share, 2),
            ]);
        }

        return $expense;
    }

    private function createPersonalExpense(int $affectedMemberId, float $amount, ?int $payerId = null): Expense
    {
        return Expense::create([
            'reference'          => Expense::generateReference(),
            'expense_type'       => 'personal',
            'category'           => 'Test',
            'amount'             => $amount,
            'affected_member_id' => $affectedMemberId,
            'payer_id'           => $payerId,
            'payment_method'     => 'cash',
            'expense_datetime'   => now(),
        ]);
    }

    private function createOperationalExpense(float $amount, ?int $payerId = null): Expense
    {
        return Expense::create([
            'reference'        => Expense::generateReference(),
            'expense_type'     => 'operational',
            'category'         => 'Test',
            'amount'           => $amount,
            'payer_id'         => $payerId,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
        ]);
    }

    // ─── Property 12: Cascade Delete Splits ──────────────────────────────────
    // For any expense deletion, all associated expense_splits records should be
    // automatically deleted from the database.
    // Validates: Requirements 12.1

    /**
     * Property 12: Cascade Delete Splits
     * Validates: Requirements 12.1
     *
     * For any shared expense with N splits, deleting the expense should remove
     * all N associated expense_splits records from the database.
     * Runs 100 iterations.
     */
    public function test_deleting_expense_removes_all_associated_splits(): void
    {
        // Feature: expense-management-system, Property 12: Cascade Delete Splits

        for ($i = 0; $i < 100; $i++) {
            $memberCount = mt_rand(2, 6);
            $members     = array_map(fn() => $this->createMember(1000.0), range(1, $memberCount));
            $memberIds   = array_map(fn(Member $m) => $m->id, $members);
            $amount      = round(mt_rand(100, 10000) / 100, 2);

            $expense   = $this->createSharedExpense($memberIds, $amount);
            $expenseId = $expense->id;

            // Verify splits exist before deletion
            $this->assertEquals(
                $memberCount,
                ExpenseSplit::where('expense_id', $expenseId)->count(),
                "Iteration {$i}: Expected {$memberCount} splits before deletion"
            );

            $response = $this->deleteJson("/api/expenses/{$expenseId}");
            $response->assertStatus(200);

            // Property 12: All splits must be gone after deletion
            $this->assertEquals(
                0,
                ExpenseSplit::where('expense_id', $expenseId)->count(),
                "Iteration {$i}: All splits should be deleted when expense is deleted"
            );

            // The expense itself must also be gone
            $this->assertNull(
                Expense::find($expenseId),
                "Iteration {$i}: Expense should be deleted from database"
            );
        }
    }

    /**
     * Property 12: Cascade Delete Splits — only target expense splits are removed
     * Validates: Requirements 12.1
     *
     * Deleting one expense should not affect splits belonging to other expenses.
     * Runs 100 iterations.
     */
    public function test_deleting_one_expense_does_not_affect_other_expense_splits(): void
    {
        // Feature: expense-management-system, Property 12: Cascade Delete Splits

        for ($i = 0; $i < 100; $i++) {
            $member1 = $this->createMember(1000.0);
            $member2 = $this->createMember(1000.0);
            $member3 = $this->createMember(1000.0);

            $expenseToDelete = $this->createSharedExpense([$member1->id, $member2->id], 100.0);
            $expenseToKeep   = $this->createSharedExpense([$member2->id, $member3->id], 200.0);

            $keepSplitCount = ExpenseSplit::where('expense_id', $expenseToKeep->id)->count();

            $response = $this->deleteJson("/api/expenses/{$expenseToDelete->id}");
            $response->assertStatus(200);

            // Splits of the kept expense must remain intact
            $this->assertEquals(
                $keepSplitCount,
                ExpenseSplit::where('expense_id', $expenseToKeep->id)->count(),
                "Iteration {$i}: Splits of other expenses should not be affected"
            );
        }
    }

    // ─── Property 8: Balance Recalculation on Changes (delete side) ──────────
    // For any expense deletion, all affected members' balances should be
    // recalculated and updated in the database.
    // Validates: Requirements 12.2

    /**
     * Property 8: Balance Recalculation on Changes — shared expense deletion restores balances
     * Validates: Requirements 12.2
     *
     * For any shared expense, deleting it should restore each split member's
     * balance to what it was before the expense was created.
     * Runs 100 iterations.
     */
    public function test_deleting_shared_expense_restores_member_balances(): void
    {
        // Feature: expense-management-system, Property 8: Balance Recalculation on Changes

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(10000, 100000) / 100, 2);
            $member1        = $this->createMember($openingBalance);
            $member2        = $this->createMember($openingBalance);
            $amount         = round(mt_rand(200, 5000) / 100, 2);

            // Create expense via API so balances are updated
            $payload = [
                'expense_type'     => 'shared',
                'category'         => 'Test',
                'amount'           => $amount,
                'payment_method'   => 'cash',
                'expense_datetime' => now()->toDateTimeString(),
                'split_type'       => 'equal',
                'members'          => [
                    ['id' => $member1->id],
                    ['id' => $member2->id],
                ],
            ];

            $createResponse = $this->postJson('/api/expenses', $payload);
            $createResponse->assertStatus(201);

            $expenseId = $createResponse->json('id');

            // Capture balances after creation (should be reduced)
            $member1->refresh();
            $member2->refresh();
            $balanceAfterCreate1 = (float) $member1->balance;
            $balanceAfterCreate2 = (float) $member2->balance;

            $this->assertLessThan(
                $openingBalance,
                $balanceAfterCreate1,
                "Iteration {$i}: Member1 balance should decrease after expense creation"
            );

            // Delete the expense
            $deleteResponse = $this->deleteJson("/api/expenses/{$expenseId}");
            $deleteResponse->assertStatus(200);

            // Balances should be restored to opening balance
            $member1->refresh();
            $member2->refresh();

            $this->assertEquals(
                (int) round($openingBalance * 100),
                (int) round((float) $member1->balance * 100),
                "Iteration {$i}: Member1 balance should be restored after expense deletion"
            );
            $this->assertEquals(
                (int) round($openingBalance * 100),
                (int) round((float) $member2->balance * 100),
                "Iteration {$i}: Member2 balance should be restored after expense deletion"
            );
        }
    }

    /**
     * Property 8: Balance Recalculation on Changes — payer balance restored on deletion
     * Validates: Requirements 12.2
     *
     * For any expense with a payer, deleting it should reduce the payer's balance
     * back to what it was before (removing the payer contribution).
     * Runs 100 iterations.
     */
    public function test_deleting_expense_restores_payer_balance(): void
    {
        // Feature: expense-management-system, Property 8: Balance Recalculation on Changes

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(0, 50000) / 100, 2);
            $payer          = $this->createMember($openingBalance);
            $amount         = round(mt_rand(100, 10000) / 100, 2);

            // Create expense with payer via API
            $payload = [
                'expense_type'     => 'operational',
                'category'         => 'Test',
                'amount'           => $amount,
                'payment_method'   => 'cash',
                'expense_datetime' => now()->toDateTimeString(),
                'payer_id'         => $payer->id,
            ];

            $createResponse = $this->postJson('/api/expenses', $payload);
            $createResponse->assertStatus(201);

            $expenseId = $createResponse->json('id');

            $payer->refresh();
            $balanceAfterCreate = (float) $payer->balance;

            $this->assertEquals(
                (int) round(($openingBalance + $amount) * 100),
                (int) round($balanceAfterCreate * 100),
                "Iteration {$i}: Payer balance should increase after expense creation"
            );

            // Delete the expense
            $deleteResponse = $this->deleteJson("/api/expenses/{$expenseId}");
            $deleteResponse->assertStatus(200);

            // Payer balance should return to opening balance
            $payer->refresh();

            $this->assertEquals(
                (int) round($openingBalance * 100),
                (int) round((float) $payer->balance * 100),
                "Iteration {$i}: Payer balance should be restored after expense deletion"
            );
        }
    }

    /**
     * Property 8: Balance Recalculation on Changes — personal expense deletion restores balance
     * Validates: Requirements 12.2
     *
     * For any personal expense, deleting it should restore the affected member's
     * balance to what it was before the expense was created.
     * Runs 100 iterations.
     */
    public function test_deleting_personal_expense_restores_affected_member_balance(): void
    {
        // Feature: expense-management-system, Property 8: Balance Recalculation on Changes

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(10000, 100000) / 100, 2);
            $member         = $this->createMember($openingBalance);
            $amount         = round(mt_rand(100, 10000) / 100, 2);

            // Create personal expense via API
            $payload = [
                'expense_type'         => 'personal',
                'category'             => 'Test',
                'amount'               => $amount,
                'payment_method'       => 'cash',
                'expense_datetime'     => now()->toDateTimeString(),
                'affected_member_id'   => $member->id,
            ];

            $createResponse = $this->postJson('/api/expenses', $payload);
            $createResponse->assertStatus(201);

            $expenseId = $createResponse->json('id');

            // Delete the expense
            $deleteResponse = $this->deleteJson("/api/expenses/{$expenseId}");
            $deleteResponse->assertStatus(200);

            // Balance should be restored
            $member->refresh();

            $this->assertEquals(
                (int) round($openingBalance * 100),
                (int) round((float) $member->balance * 100),
                "Iteration {$i}: Affected member balance should be restored after personal expense deletion"
            );
        }
    }

    // ─── Additional Delete Tests ──────────────────────────────────────────────

    /**
     * DELETE /api/expenses/{id} returns 200 with success message
     * Requirements: 12.5
     */
    public function test_delete_returns_200_with_success_message(): void
    {
        $expense = $this->createOperationalExpense(100.0);

        $response = $this->deleteJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'تم حذف المصروف بنجاح']);
    }

    /**
     * DELETE /api/expenses/{id} returns 404 for non-existent expense
     * Requirements: 11.6
     */
    public function test_delete_returns_404_for_nonexistent_expense(): void
    {
        $response = $this->deleteJson('/api/expenses/99999');

        $response->assertStatus(404);
    }

    /**
     * DELETE /api/expenses/{id} removes the expense from the database
     * Requirements: 12.1
     */
    public function test_delete_removes_expense_from_database(): void
    {
        $expense   = $this->createOperationalExpense(100.0);
        $expenseId = $expense->id;

        $this->deleteJson("/api/expenses/{$expenseId}")->assertStatus(200);

        $this->assertNull(Expense::find($expenseId));
    }
}
