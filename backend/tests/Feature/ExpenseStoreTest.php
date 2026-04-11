<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Expense Store Operation
 *
 * Feature: expense-management-system
 * Property 3: Equal Split Mathematical Accuracy
 * Property 5: Manual Split Sum Verification
 * Property 6: Payer Balance Contribution
 * Property 8: Balance Recalculation on Changes
 * Property 10: Atomic Transaction Rollback
 * Property 14: Split Distribution Completeness
 * Validates: Requirements 2.1, 2.2, 2.4, 2.5, 3.2, 4.2, 9.1, 9.2
 */
class ExpenseStoreTest extends TestCase
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

    private function basePayload(string $type = 'operational', float $amount = 100.00): array
    {
        return [
            'expense_type'     => $type,
            'category'         => 'Test',
            'amount'           => $amount,
            'payment_method'   => 'cash',
            'expense_datetime' => now()->toDateTimeString(),
        ];
    }

    // ─── Property 3: Equal Split Mathematical Accuracy ────────────────────────
    // For any shared expense with equal split type, the sum of all member splits
    // should exactly equal the total expense amount with zero remainder.
    // Validates: Requirements 2.1, 2.2

    /**
     * Property 3: Equal Split Mathematical Accuracy
     * Validates: Requirements 2.1, 2.2
     *
     * For any total amount and any number of members (2–8), the sum of all
     * persisted expense_splits must exactly equal the total expense amount.
     * Runs 100 iterations.
     */
    public function test_equal_split_sum_always_equals_total_amount(): void
    {
        // Feature: expense-management-system, Property 3: Equal Split Mathematical Accuracy

        for ($i = 0; $i < 100; $i++) {
            $totalAmount = round(mt_rand(100, 100000) / 100, 2); // 1.00 – 1000.00
            $memberCount = mt_rand(2, 8);
            $members     = array_map(fn() => $this->createMember(), range(1, $memberCount));
            $memberIds   = array_map(fn(Member $m) => $m->id, $members);

            $payload = $this->basePayload('shared', $totalAmount);
            $payload['split_type'] = 'equal';
            $payload['members']    = array_map(fn($id) => ['id' => $id], $memberIds);

            $response = $this->postJson('/api/expenses', $payload);

            $response->assertStatus(201);

            $expenseId = $response->json('id');
            $splitSum  = ExpenseSplit::where('expense_id', $expenseId)->sum('amount');

            $this->assertEquals(
                (int) round($totalAmount * 100),
                (int) round($splitSum * 100),
                "Iteration {$i}: Split sum ({$splitSum}) != total ({$totalAmount}) for {$memberCount} members"
            );
        }
    }

    // ─── Property 5: Manual Split Sum Verification ────────────────────────────
    // For any shared expense with manual split type, the system should accept
    // the expense only if the sum of manual amounts exactly equals the total.
    // Validates: Requirements 2.4, 2.5

    /**
     * Property 5: Manual Split Sum Verification - valid sum accepted
     * Validates: Requirements 2.4, 2.5
     *
     * For any manual split where amounts sum to the total, the request should succeed.
     * Runs 100 iterations.
     */
    public function test_manual_split_with_correct_sum_is_accepted(): void
    {
        // Feature: expense-management-system, Property 5: Manual Split Sum Verification

        for ($i = 0; $i < 100; $i++) {
            $member1 = $this->createMember();
            $member2 = $this->createMember();

            $total  = round(mt_rand(200, 100000) / 100, 2);
            $share1 = round($total * (mt_rand(10, 90) / 100), 2);
            $share2 = round($total - $share1, 2);

            $payload = $this->basePayload('shared', $total);
            $payload['split_type'] = 'manual';
            $payload['members']    = [
                ['id' => $member1->id, 'amount' => $share1],
                ['id' => $member2->id, 'amount' => $share2],
            ];

            $response = $this->postJson('/api/expenses', $payload);

            $response->assertStatus(201, "Iteration {$i}: Valid manual split should be accepted");

            $expenseId = $response->json('id');
            $splitSum  = ExpenseSplit::where('expense_id', $expenseId)->sum('amount');

            $this->assertEquals(
                (int) round($total * 100),
                (int) round($splitSum * 100),
                "Iteration {$i}: Persisted split sum should equal total"
            );
        }
    }

    /**
     * Property 5: Manual Split Sum Verification - mismatched sum rejected
     * Validates: Requirements 2.5, 2.6
     *
     * For any manual split where amounts do NOT sum to the total, the request should fail.
     * Runs 100 iterations.
     */
    public function test_manual_split_with_wrong_sum_is_rejected(): void
    {
        // Feature: expense-management-system, Property 5: Manual Split Sum Verification

        for ($i = 0; $i < 100; $i++) {
            $member1 = $this->createMember();
            $member2 = $this->createMember();

            $total     = round(mt_rand(200, 100000) / 100, 2);
            $wrongSum  = round($total * 0.8, 2); // always 20% short
            $share1    = round($wrongSum / 2, 2);
            $share2    = round($wrongSum - $share1, 2);

            $payload = $this->basePayload('shared', $total);
            $payload['split_type'] = 'manual';
            $payload['members']    = [
                ['id' => $member1->id, 'amount' => $share1],
                ['id' => $member2->id, 'amount' => $share2],
            ];

            $response = $this->postJson('/api/expenses', $payload);

            $response->assertStatus(422, "Iteration {$i}: Mismatched manual split should be rejected");
        }
    }

    // ─── Property 6: Payer Balance Contribution ───────────────────────────────
    // For any expense with a payer_id, the payer's calculated balance should
    // increase by the expense amount.
    // Validates: Requirements 3.2

    /**
     * Property 6: Payer Balance Contribution
     * Validates: Requirements 3.2
     *
     * For any expense with a payer, the payer's balance should increase by the
     * expense amount after creation.
     * Runs 100 iterations.
     */
    public function test_payer_balance_increases_by_expense_amount(): void
    {
        // Feature: expense-management-system, Property 6: Payer Balance Contribution

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(0, 100000) / 100, 2);
            $payer          = $this->createMember($openingBalance);
            $amount         = round(mt_rand(100, 10000) / 100, 2);

            $payload = $this->basePayload('operational', $amount);
            $payload['payer_id'] = $payer->id;

            $response = $this->postJson('/api/expenses', $payload);

            $response->assertStatus(201);

            $payer->refresh();
            $expectedBalance = round($openingBalance + $amount, 2);

            $this->assertEquals(
                (int) round($expectedBalance * 100),
                (int) round((float) $payer->balance * 100),
                "Iteration {$i}: Payer balance should be {$expectedBalance}, got {$payer->balance}"
            );
        }
    }

    // ─── Property 8: Balance Recalculation on Changes ─────────────────────────
    // For any expense creation or deletion, all affected members' balances should
    // be recalculated and updated in the database.
    // Validates: Requirements 4.2, 12.2, 17.2

    /**
     * Property 8: Balance Recalculation on Changes - shared expense affects split members
     * Validates: Requirements 4.2, 17.2
     *
     * For any shared expense, each member's balance should decrease by their split amount.
     * Runs 100 iterations.
     */
    public function test_shared_expense_reduces_member_balances_by_split_amount(): void
    {
        // Feature: expense-management-system, Property 8: Balance Recalculation on Changes

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(10000, 100000) / 100, 2);
            $member1        = $this->createMember($openingBalance);
            $member2        = $this->createMember($openingBalance);

            $total = round(mt_rand(200, 10000) / 100, 2);

            $payload = $this->basePayload('shared', $total);
            $payload['split_type'] = 'equal';
            $payload['members']    = [
                ['id' => $member1->id],
                ['id' => $member2->id],
            ];

            $response = $this->postJson('/api/expenses', $payload);
            $response->assertStatus(201);

            $expenseId = $response->json('id');
            $split1    = (float) ExpenseSplit::where('expense_id', $expenseId)
                ->where('member_id', $member1->id)->value('amount');
            $split2    = (float) ExpenseSplit::where('expense_id', $expenseId)
                ->where('member_id', $member2->id)->value('amount');

            $member1->refresh();
            $member2->refresh();

            $this->assertEquals(
                (int) round(($openingBalance - $split1) * 100),
                (int) round((float) $member1->balance * 100),
                "Iteration {$i}: Member1 balance should decrease by split amount"
            );
            $this->assertEquals(
                (int) round(($openingBalance - $split2) * 100),
                (int) round((float) $member2->balance * 100),
                "Iteration {$i}: Member2 balance should decrease by split amount"
            );
        }
    }

    /**
     * Property 8: Balance Recalculation on Changes - personal expense affects member
     * Validates: Requirements 4.2
     *
     * For any personal expense, the affected member's balance should decrease by the amount.
     * Runs 100 iterations.
     */
    public function test_personal_expense_reduces_affected_member_balance(): void
    {
        // Feature: expense-management-system, Property 8: Balance Recalculation on Changes

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(10000, 100000) / 100, 2);
            $member         = $this->createMember($openingBalance);
            $amount         = round(mt_rand(100, 10000) / 100, 2);

            $payload = $this->basePayload('personal', $amount);
            $payload['affected_member_id'] = $member->id;

            $response = $this->postJson('/api/expenses', $payload);
            $response->assertStatus(201);

            $member->refresh();
            $expectedBalance = round($openingBalance - $amount, 2);

            $this->assertEquals(
                (int) round($expectedBalance * 100),
                (int) round((float) $member->balance * 100),
                "Iteration {$i}: Affected member balance should decrease by expense amount"
            );
        }
    }

    // ─── Property 10: Atomic Transaction Rollback ─────────────────────────────
    // For any expense creation that fails at any step, no partial data should
    // remain in the database (complete rollback).
    // Validates: Requirements 9.1, 9.2

    /**
     * Property 10: Atomic Transaction Rollback
     * Validates: Requirements 9.1, 9.2
     *
     * When a manual split sum doesn't match the total, no expense or splits
     * should be persisted in the database.
     */
    public function test_failed_expense_creation_leaves_no_partial_data(): void
    {
        // Feature: expense-management-system, Property 10: Atomic Transaction Rollback

        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $expenseCountBefore = Expense::count();
        $splitCountBefore   = ExpenseSplit::count();

        // Trigger a failure: manual split sum mismatch
        $payload = $this->basePayload('shared', 100.00);
        $payload['split_type'] = 'manual';
        $payload['members']    = [
            ['id' => $member1->id, 'amount' => 30.00],
            ['id' => $member2->id, 'amount' => 30.00], // sum = 60, not 100
        ];

        $response = $this->postJson('/api/expenses', $payload);
        $response->assertStatus(422);

        // No partial data should remain
        $this->assertEquals($expenseCountBefore, Expense::count(), 'No expense should be created on failure');
        $this->assertEquals($splitCountBefore, ExpenseSplit::count(), 'No splits should be created on failure');
    }

    /**
     * Property 10: Atomic Transaction Rollback - invalid member leaves no data
     * Validates: Requirements 9.1, 9.2
     *
     * When a shared expense references a non-existent member, no data should be persisted.
     */
    public function test_invalid_member_reference_leaves_no_partial_data(): void
    {
        // Feature: expense-management-system, Property 10: Atomic Transaction Rollback

        $expenseCountBefore = Expense::count();
        $splitCountBefore   = ExpenseSplit::count();

        $payload = $this->basePayload('shared', 100.00);
        $payload['split_type'] = 'equal';
        $payload['members']    = [['id' => 99999]]; // non-existent member

        $response = $this->postJson('/api/expenses', $payload);
        $response->assertStatus(422);

        $this->assertEquals($expenseCountBefore, Expense::count());
        $this->assertEquals($splitCountBefore, ExpenseSplit::count());
    }

    // ─── Property 14: Split Distribution Completeness ─────────────────────────
    // For any shared expense, the number of expense_splits records should equal
    // the number of members in the request.
    // Validates: Requirements 2.7

    /**
     * Property 14: Split Distribution Completeness
     * Validates: Requirements 2.7
     *
     * For any shared expense with N members, exactly N expense_splits records
     * should be created in the database.
     * Runs 100 iterations.
     */
    public function test_split_count_equals_member_count(): void
    {
        // Feature: expense-management-system, Property 14: Split Distribution Completeness

        for ($i = 0; $i < 100; $i++) {
            $memberCount = mt_rand(2, 8);
            $members     = array_map(fn() => $this->createMember(), range(1, $memberCount));
            $memberIds   = array_map(fn(Member $m) => $m->id, $members);
            $total       = round(mt_rand(100, 100000) / 100, 2);

            $payload = $this->basePayload('shared', $total);
            $payload['split_type'] = 'equal';
            $payload['members']    = array_map(fn($id) => ['id' => $id], $memberIds);

            $response = $this->postJson('/api/expenses', $payload);
            $response->assertStatus(201);

            $expenseId  = $response->json('id');
            $splitCount = ExpenseSplit::where('expense_id', $expenseId)->count();

            $this->assertEquals(
                $memberCount,
                $splitCount,
                "Iteration {$i}: Expected {$memberCount} splits, got {$splitCount}"
            );
        }
    }

    // ─── Additional Store Tests ───────────────────────────────────────────────

    /**
     * POST /api/expenses returns 201 with expense data for operational expense
     * Requirements: 11.1, 11.5, 11.6
     */
    public function test_store_operational_expense_returns_201(): void
    {
        $payload = $this->basePayload('operational', 250.00);

        $response = $this->postJson('/api/expenses', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment([
                     'expense_type'   => 'operational',
                     'category'       => 'Test',
                     'payment_method' => 'cash',
                 ]);
    }

    /**
     * POST /api/expenses returns 201 with personal expense and affected_member
     * Requirements: 1.3, 11.7
     */
    public function test_store_personal_expense_includes_affected_member(): void
    {
        $member  = $this->createMember();
        $payload = $this->basePayload('personal', 50.00);
        $payload['affected_member_id'] = $member->id;

        $response = $this->postJson('/api/expenses', $payload);

        $response->assertStatus(201);
        $data = $response->json();

        $this->assertNotNull($data['affected_member']);
        $this->assertEquals($member->id, $data['affected_member']['id']);
        $this->assertEquals($member->id, $data['affected_member_id']);
    }

    /**
     * POST /api/expenses returns 201 with splits for shared expense
     * Requirements: 2.7, 11.7
     */
    public function test_store_shared_expense_includes_splits_in_response(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $payload = $this->basePayload('shared', 100.00);
        $payload['split_type'] = 'equal';
        $payload['members']    = [
            ['id' => $member1->id],
            ['id' => $member2->id],
        ];

        $response = $this->postJson('/api/expenses', $payload);

        $response->assertStatus(201);
        $data = $response->json();

        $this->assertArrayHasKey('splits', $data);
        $this->assertCount(2, $data['splits']);
    }

    /**
     * POST /api/expenses returns 422 for missing required fields
     * Requirements: 10.1, 10.2, 18.1
     */
    public function test_store_returns_422_for_missing_required_fields(): void
    {
        $response = $this->postJson('/api/expenses', []);

        $response->assertStatus(422);
    }

    /**
     * POST /api/expenses generates a unique reference for each expense
     * Requirements: 5.1, 5.2
     */
    public function test_store_generates_unique_reference_for_each_expense(): void
    {
        $payload1 = $this->basePayload('operational', 100.00);
        $payload2 = $this->basePayload('operational', 200.00);

        $response1 = $this->postJson('/api/expenses', $payload1);
        $response2 = $this->postJson('/api/expenses', $payload2);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $this->assertNotEquals(
            $response1->json('reference'),
            $response2->json('reference'),
            'Each expense should have a unique reference'
        );
    }

    /**
     * POST /api/expenses - operational expense has no splits and no affected_member
     * Requirements: 1.4
     */
    public function test_operational_expense_has_no_splits_and_no_affected_member(): void
    {
        $payload = $this->basePayload('operational', 300.00);

        $response = $this->postJson('/api/expenses', $payload);
        $response->assertStatus(201);

        $expenseId = $response->json('id');

        $this->assertEquals(0, ExpenseSplit::where('expense_id', $expenseId)->count());
        $this->assertNull(Expense::find($expenseId)->affected_member_id);
    }
}
