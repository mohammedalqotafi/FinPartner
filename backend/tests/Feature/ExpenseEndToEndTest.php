<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-End Integration Tests for Expense Management System
 *
 * Feature: expense-management-system
 * Tests complete scenarios: Create → Read → Delete
 * Tests impact on member balances across all expense types
 * Validates: Requirements 20.1, 20.2, 20.3, 20.4
 */
class ExpenseEndToEndTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMember(string $name, float $openingBalance = 0.0): Member
    {
        return Member::create([
            'name'            => $name,
            'email'           => strtolower(str_replace(' ', '', $name)) . '@test.com',
            'phone'           => '05' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            'opening_balance' => $openingBalance,
            'balance'         => $openingBalance,
            'join_date'       => now()->subMonths(mt_rand(1, 12))->format('Y-m-d'),
        ]);
    }

    private function basePayload(string $type, float $amount): array
    {
        return [
            'expense_type'     => $type,
            'category'         => 'Test Category',
            'amount'           => $amount,
            'payment_method'   => 'cash',
            'expense_datetime' => now()->toDateTimeString(),
            'description'      => 'Test expense description',
        ];
    }

    // ─── End-to-End Scenario Tests ───────────────────────────────────────────

    /**
     * E2E: Complete lifecycle of operational expense
     * Create → Read → Verify Balance → Delete → Verify Restoration
     * Requirements: 20.1, 20.2, 20.3
     */
    public function test_operational_expense_complete_lifecycle(): void
    {
        // Setup: Create a payer member
        $payer = $this->createMember('Ahmed', 1000.00);

        // Step 1: Create operational expense with payer
        $payload = $this->basePayload('operational', 250.00);
        $payload['payer_id'] = $payer->id;

        $createResponse = $this->postJson('/api/expenses', $payload);
        $createResponse->assertStatus(201);

        $expenseId = $createResponse->json('id');
        $reference = $createResponse->json('reference');

        // Verify creation response structure
        $this->assertNotNull($expenseId);
        $this->assertNotNull($reference);
        $this->assertMatchesRegularExpression('/^EXP-\d{4}$/', $reference);

        // Step 2: Verify payer balance increased
        $payer->refresh();
        $this->assertEquals(1250.00, (float) $payer->balance);

        // Step 3: Read the expense via index
        $indexResponse = $this->getJson('/api/expenses?all=true');
        $indexResponse->assertStatus(200);
        $responseData = $indexResponse->json();
        
        // Handle both paginated and non-paginated responses
        $expenses = isset($responseData['data']) ? $responseData['data'] : $responseData;

        $this->assertCount(1, $expenses);
        $this->assertEquals($expenseId, $expenses[0]['id']);
        $this->assertEquals('operational', $expenses[0]['expense_type']);
        $this->assertEquals(250.00, $expenses[0]['amount']);

        // Step 4: Read the expense via show
        $showResponse = $this->getJson("/api/expenses/{$expenseId}");
        $showResponse->assertStatus(200);
        $expense = $showResponse->json();

        $this->assertEquals($reference, $expense['reference']);
        $this->assertEquals('Test Category', $expense['category']);
        $this->assertEquals('cash', $expense['payment_method']);
        $this->assertNotNull($expense['payer']);
        $this->assertEquals($payer->id, $expense['payer']['id']);
        $this->assertNull($expense['affected_member']);
        $this->assertEmpty($expense['splits']);

        // Step 5: Delete the expense
        $deleteResponse = $this->deleteJson("/api/expenses/{$expenseId}");
        $deleteResponse->assertStatus(200);

        // Step 6: Verify expense is gone
        $this->assertNull(Expense::find($expenseId));

        // Step 7: Verify payer balance restored
        $payer->refresh();
        $this->assertEquals(1000.00, (float) $payer->balance);

        // Step 8: Verify expense not in index
        $finalIndexResponse = $this->getJson('/api/expenses?all=true');
        $finalIndexResponse->assertStatus(200);
        $finalData = $finalIndexResponse->json();
        $finalExpenses = isset($finalData['data']) ? $finalData['data'] : $finalData;
        $this->assertEmpty($finalExpenses);
    }

    /**
     * E2E: Complete lifecycle of personal expense
     * Create → Read → Verify Balance → Delete → Verify Restoration
     * Requirements: 20.1, 20.2, 20.3
     */
    public function test_personal_expense_complete_lifecycle(): void
    {
        // Setup: Create affected member and payer
        $affectedMember = $this->createMember('Sara', 2000.00);
        $payer          = $this->createMember('Omar', 500.00);

        // Step 1: Create personal expense
        $payload = $this->basePayload('personal', 150.00);
        $payload['affected_member_id'] = $affectedMember->id;
        $payload['payer_id']           = $payer->id;

        $createResponse = $this->postJson('/api/expenses', $payload);
        $createResponse->assertStatus(201);

        $expenseId = $createResponse->json('id');

        // Step 2: Verify balances changed
        $affectedMember->refresh();
        $payer->refresh();

        $this->assertEquals(1850.00, (float) $affectedMember->balance); // 2000 - 150
        $this->assertEquals(650.00, (float) $payer->balance);           // 500 + 150

        // Step 3: Read and verify structure
        $showResponse = $this->getJson("/api/expenses/{$expenseId}");
        $showResponse->assertStatus(200);
        $expense = $showResponse->json();

        $this->assertEquals('personal', $expense['expense_type']);
        $this->assertNotNull($expense['affected_member']);
        $this->assertEquals($affectedMember->id, $expense['affected_member']['id']);
        $this->assertEquals('Sara', $expense['affected_member']['name']);
        $this->assertNotNull($expense['payer']);
        $this->assertEquals($payer->id, $expense['payer']['id']);
        $this->assertEmpty($expense['splits']);

        // Step 4: Delete the expense
        $deleteResponse = $this->deleteJson("/api/expenses/{$expenseId}");
        $deleteResponse->assertStatus(200);

        // Step 5: Verify balances restored
        $affectedMember->refresh();
        $payer->refresh();

        $this->assertEquals(2000.00, (float) $affectedMember->balance);
        $this->assertEquals(500.00, (float) $payer->balance);
    }

    /**
     * E2E: Complete lifecycle of shared expense with equal split
     * Create → Read → Verify Balances → Delete → Verify Restoration
     * Requirements: 20.1, 20.2, 20.3, 20.4
     */
    public function test_shared_expense_equal_split_complete_lifecycle(): void
    {
        // Setup: Create members
        $member1 = $this->createMember('Ali', 3000.00);
        $member2 = $this->createMember('Fatima', 2500.00);
        $member3 = $this->createMember('Hassan', 1800.00);
        $payer   = $this->createMember('Khaled', 1000.00);

        // Step 1: Create shared expense with equal split
        $payload = $this->basePayload('shared', 300.00);
        $payload['split_type'] = 'equal';
        $payload['payer_id']   = $payer->id;
        $payload['members']    = [
            ['id' => $member1->id],
            ['id' => $member2->id],
            ['id' => $member3->id],
        ];

        $createResponse = $this->postJson('/api/expenses', $payload);
        $createResponse->assertStatus(201);

        $expenseId = $createResponse->json('id');

        // Step 2: Verify splits were created
        $splits = ExpenseSplit::where('expense_id', $expenseId)->get();
        $this->assertCount(3, $splits);

        // Verify split sum equals total
        $splitSum = $splits->sum('amount');
        $this->assertEquals(300.00, (float) $splitSum);

        // Step 3: Verify member balances decreased by their split amounts
        $member1->refresh();
        $member2->refresh();
        $member3->refresh();
        $payer->refresh();

        $split1 = (float) $splits->where('member_id', $member1->id)->first()->amount;
        $split2 = (float) $splits->where('member_id', $member2->id)->first()->amount;
        $split3 = (float) $splits->where('member_id', $member3->id)->first()->amount;

        $this->assertEquals(3000.00 - $split1, (float) $member1->balance);
        $this->assertEquals(2500.00 - $split2, (float) $member2->balance);
        $this->assertEquals(1800.00 - $split3, (float) $member3->balance);
        $this->assertEquals(1300.00, (float) $payer->balance); // 1000 + 300

        // Step 4: Read and verify structure
        $showResponse = $this->getJson("/api/expenses/{$expenseId}");
        $showResponse->assertStatus(200);
        $expense = $showResponse->json();

        $this->assertEquals('shared', $expense['expense_type']);
        $this->assertNull($expense['affected_member']);
        $this->assertNotNull($expense['payer']);
        $this->assertCount(3, $expense['splits']);

        // Verify each split has member data
        foreach ($expense['splits'] as $split) {
            $this->assertArrayHasKey('member', $split);
            $this->assertArrayHasKey('amount', $split);
            $this->assertNotNull($split['member']['name']);
        }

        // Step 5: Delete the expense
        $deleteResponse = $this->deleteJson("/api/expenses/{$expenseId}");
        $deleteResponse->assertStatus(200);

        // Step 6: Verify splits are deleted
        $this->assertEquals(0, ExpenseSplit::where('expense_id', $expenseId)->count());

        // Step 7: Verify all balances restored
        $member1->refresh();
        $member2->refresh();
        $member3->refresh();
        $payer->refresh();

        $this->assertEquals(3000.00, (float) $member1->balance);
        $this->assertEquals(2500.00, (float) $member2->balance);
        $this->assertEquals(1800.00, (float) $member3->balance);
        $this->assertEquals(1000.00, (float) $payer->balance);
    }

    /**
     * E2E: Complete lifecycle of shared expense with manual split
     * Create → Read → Verify Balances → Delete → Verify Restoration
     * Requirements: 20.1, 20.2, 20.3, 20.4
     */
    public function test_shared_expense_manual_split_complete_lifecycle(): void
    {
        // Setup: Create members
        $member1 = $this->createMember('Nora', 5000.00);
        $member2 = $this->createMember('Youssef', 4000.00);

        // Step 1: Create shared expense with manual split
        $payload = $this->basePayload('shared', 500.00);
        $payload['split_type'] = 'manual';
        $payload['members']    = [
            ['id' => $member1->id, 'amount' => 300.00],
            ['id' => $member2->id, 'amount' => 200.00],
        ];

        $createResponse = $this->postJson('/api/expenses', $payload);
        $createResponse->assertStatus(201);

        $expenseId = $createResponse->json('id');

        // Step 2: Verify manual splits are correct
        $splits = ExpenseSplit::where('expense_id', $expenseId)->get();
        $this->assertCount(2, $splits);

        $split1 = $splits->where('member_id', $member1->id)->first();
        $split2 = $splits->where('member_id', $member2->id)->first();

        $this->assertEquals(300.00, (float) $split1->amount);
        $this->assertEquals(200.00, (float) $split2->amount);

        // Step 3: Verify balances
        $member1->refresh();
        $member2->refresh();

        $this->assertEquals(4700.00, (float) $member1->balance); // 5000 - 300
        $this->assertEquals(3800.00, (float) $member2->balance); // 4000 - 200

        // Step 4: Delete and verify restoration
        $this->deleteJson("/api/expenses/{$expenseId}")->assertStatus(200);

        $member1->refresh();
        $member2->refresh();

        $this->assertEquals(5000.00, (float) $member1->balance);
        $this->assertEquals(4000.00, (float) $member2->balance);
    }

    /**
     * E2E: Multiple expenses affecting same members
     * Tests cumulative balance changes across multiple operations
     * Requirements: 20.1, 20.2, 20.4
     */
    public function test_multiple_expenses_cumulative_balance_impact(): void
    {
        // Setup: Create members
        $member1 = $this->createMember('Layla', 10000.00);
        $member2 = $this->createMember('Tariq', 8000.00);

        // Operation 1: Personal expense for member1
        $payload1 = $this->basePayload('personal', 500.00);
        $payload1['affected_member_id'] = $member1->id;

        $response1 = $this->postJson('/api/expenses', $payload1);
        $response1->assertStatus(201);
        $expense1Id = $response1->json('id');

        $member1->refresh();
        $this->assertEquals(9500.00, (float) $member1->balance); // 10000 - 500

        // Operation 2: Shared expense between both members
        $payload2 = $this->basePayload('shared', 400.00);
        $payload2['split_type'] = 'equal';
        $payload2['members']    = [
            ['id' => $member1->id],
            ['id' => $member2->id],
        ];

        $response2 = $this->postJson('/api/expenses', $payload2);
        $response2->assertStatus(201);
        $expense2Id = $response2->json('id');

        $member1->refresh();
        $member2->refresh();

        $this->assertEquals(9300.00, (float) $member1->balance); // 9500 - 200
        $this->assertEquals(7800.00, (float) $member2->balance); // 8000 - 200

        // Operation 3: Member2 pays operational expense
        $payload3 = $this->basePayload('operational', 300.00);
        $payload3['payer_id'] = $member2->id;

        $response3 = $this->postJson('/api/expenses', $payload3);
        $response3->assertStatus(201);
        $expense3Id = $response3->json('id');

        $member2->refresh();
        $this->assertEquals(8100.00, (float) $member2->balance); // 7800 + 300

        // Verify total expenses in system
        $indexResponse = $this->getJson('/api/expenses?all=true');
        $indexResponse->assertStatus(200);
        $indexData = $indexResponse->json();
        $expenses = isset($indexData['data']) ? $indexData['data'] : $indexData;
        $this->assertCount(3, $expenses);

        // Delete expense 2 (shared) - restores 200 to each member
        $this->deleteJson("/api/expenses/{$expense2Id}")->assertStatus(200);

        $member1->refresh();
        $member2->refresh();

        $this->assertEquals(9500.00, (float) $member1->balance); // 9300 + 200 restored
        $this->assertEquals(8300.00, (float) $member2->balance); // 8100 + 200 restored

        // Delete expense 1 (personal) - restores 500 to member1
        $this->deleteJson("/api/expenses/{$expense1Id}")->assertStatus(200);

        $member1->refresh();
        $this->assertEquals(10000.00, (float) $member1->balance); // 9500 + 500 restored

        // Delete expense 3 (operational with payer) - removes 300 from member2
        $this->deleteJson("/api/expenses/{$expense3Id}")->assertStatus(200);

        $member2->refresh();
        $this->assertEquals(8000.00, (float) $member2->balance); // 8300 - 300 removed

        // Verify all expenses deleted
        $finalIndexResponse = $this->getJson('/api/expenses?all=true');
        $finalIndexResponse->assertStatus(200);
        $finalData = $finalIndexResponse->json();
        $finalExpenses = isset($finalData['data']) ? $finalData['data'] : $finalData;
        $this->assertEmpty($finalExpenses);
    }

    /**
     * E2E: All three expense types in one scenario
     * Tests system handling all expense types simultaneously
     * Requirements: 20.1, 20.2, 20.3, 20.4
     */
    public function test_all_expense_types_in_single_scenario(): void
    {
        // Setup: Create members
        $member1 = $this->createMember('Zainab', 15000.00);
        $member2 = $this->createMember('Ibrahim', 12000.00);
        $member3 = $this->createMember('Maryam', 10000.00);

        $expenseIds = [];

        // Create operational expense (no member impact except payer)
        $payload1 = $this->basePayload('operational', 1000.00);
        $payload1['payer_id'] = $member1->id;
        $response1 = $this->postJson('/api/expenses', $payload1);
        $response1->assertStatus(201);
        $expenseIds[] = $response1->json('id');

        // Create personal expense
        $payload2 = $this->basePayload('personal', 800.00);
        $payload2['affected_member_id'] = $member2->id;
        $response2 = $this->postJson('/api/expenses', $payload2);
        $response2->assertStatus(201);
        $expenseIds[] = $response2->json('id');

        // Create shared expense
        $payload3 = $this->basePayload('shared', 600.00);
        $payload3['split_type'] = 'equal';
        $payload3['members']    = [
            ['id' => $member1->id],
            ['id' => $member2->id],
            ['id' => $member3->id],
        ];
        $response3 = $this->postJson('/api/expenses', $payload3);
        $response3->assertStatus(201);
        $expenseIds[] = $response3->json('id');

        // Verify all expenses exist
        $indexResponse = $this->getJson('/api/expenses?all=true');
        $indexResponse->assertStatus(200);
        $indexData = $indexResponse->json();
        $expenses = isset($indexData['data']) ? $indexData['data'] : $indexData;
        $this->assertCount(3, $expenses);

        // Verify final balances
        $member1->refresh();
        $member2->refresh();
        $member3->refresh();

        // member1: 15000 + 1000 (payer) - 200 (split) = 15800
        $this->assertEquals(15800.00, (float) $member1->balance);

        // member2: 12000 - 800 (personal) - 200 (split) = 11000
        $this->assertEquals(11000.00, (float) $member2->balance);

        // member3: 10000 - 200 (split) = 9800
        $this->assertEquals(9800.00, (float) $member3->balance);

        // Delete all expenses
        foreach ($expenseIds as $id) {
            $this->deleteJson("/api/expenses/{$id}")->assertStatus(200);
        }

        // Verify all balances restored
        $member1->refresh();
        $member2->refresh();
        $member3->refresh();

        $this->assertEquals(15000.00, (float) $member1->balance);
        $this->assertEquals(12000.00, (float) $member2->balance);
        $this->assertEquals(10000.00, (float) $member3->balance);

        // Verify no expenses remain
        $finalIndexResponse = $this->getJson('/api/expenses?all=true');
        $finalIndexResponse->assertStatus(200);
        $finalData = $finalIndexResponse->json();
        $finalExpenses = isset($finalData['data']) ? $finalData['data'] : $finalData;
        $this->assertEmpty($finalExpenses);
    }
}
