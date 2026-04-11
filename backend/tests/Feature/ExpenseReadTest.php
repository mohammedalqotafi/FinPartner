<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Expense Read Operations
 *
 * Tests GET /api/expenses and GET /api/expenses/{id}
 * Requirements: 11.2, 11.3, 11.7, 15.2
 */
class ExpenseReadTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMember(string $name = 'Test Member'): Member
    {
        return Member::create([
            'name'            => $name,
            'opening_balance' => 0,
            'balance'         => 0,
        ]);
    }

    /**
     * Helper to get expenses from API response, handling both paginated and non-paginated responses
     */
    private function getExpensesFromResponse(array $responseData): array
    {
        return isset($responseData['data']) ? $responseData['data'] : $responseData;
    }

    private function createOperationalExpense(array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'reference'        => Expense::generateReference(),
            'expense_type'     => 'operational',
            'category'         => 'Test Category',
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
        ], $overrides));
    }

    private function createSharedExpense(Member $payer, array $memberIds, float $amount = 90.00): Expense
    {
        $expense = Expense::create([
            'reference'        => Expense::generateReference(),
            'expense_type'     => 'shared',
            'category'         => 'Shared Category',
            'amount'           => $amount,
            'payer_id'         => $payer->id,
            'payment_method'   => 'transfer',
            'expense_datetime' => now(),
        ]);

        $splitAmount = round($amount / count($memberIds), 2);
        foreach ($memberIds as $memberId) {
            ExpenseSplit::create([
                'expense_id' => $expense->id,
                'member_id'  => $memberId,
                'amount'     => $splitAmount,
            ]);
        }

        return $expense;
    }

    // ─── GET /api/expenses ────────────────────────────────────────────────────

    /**
     * GET /api/expenses returns 200 with JSON array
     * Requirements: 11.2, 11.5, 11.6, 15.4
     */
    public function test_index_returns_200_with_json_array(): void
    {
        $response = $this->getJson('/api/expenses');

        $response->assertStatus(200);
        
        // Should return paginated response structure
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
    }

    /**
     * GET /api/expenses returns all expenses
     * Requirements: 11.2, 15.4
     */
    public function test_index_returns_all_expenses(): void
    {
        $this->createOperationalExpense();
        $this->createOperationalExpense(['reference' => 'EXP-0002', 'category' => 'Another']);

        $response = $this->getJson('/api/expenses?all=true');

        $response->assertStatus(200);
        $data = $response->json();
        $expenses = $this->getExpensesFromResponse($data);
        
        $this->assertCount(2, $expenses);
    }

    /**
     * GET /api/expenses returns empty array when no expenses exist
     * Requirements: 11.2, 15.4
     */
    public function test_index_returns_empty_array_when_no_expenses(): void
    {
        $response = $this->getJson('/api/expenses?all=true');

        $response->assertStatus(200);
        $data = $response->json();
        $expenses = $this->getExpensesFromResponse($data);
        
        $this->assertEmpty($expenses);
    }

    /**
     * GET /api/expenses returns expenses ordered by expense_datetime descending
     * Requirements: 15.2
     */
    public function test_index_returns_expenses_ordered_by_datetime_desc(): void
    {
        $older = $this->createOperationalExpense([
            'expense_datetime' => now()->subDays(2),
        ]);
        $newer = $this->createOperationalExpense([
            'reference'        => 'EXP-0002',
            'expense_datetime' => now()->subDay(),
        ]);
        $newest = $this->createOperationalExpense([
            'reference'        => 'EXP-0003',
            'expense_datetime' => now(),
        ]);

        $response = $this->getJson('/api/expenses?all=true');

        $response->assertStatus(200);
        $data = $response->json();
        $expenses = $this->getExpensesFromResponse($data);

        $this->assertEquals($newest->id, $expenses[0]['id']);
        $this->assertEquals($newer->id, $expenses[1]['id']);
        $this->assertEquals($older->id, $expenses[2]['id']);
    }

    /**
     * GET /api/expenses includes payer relationship
     * Requirements: 11.7
     */
    public function test_index_includes_payer_relationship(): void
    {
        $payer = $this->createMember('Payer Member');
        $this->createOperationalExpense(['payer_id' => $payer->id]);

        $response = $this->getJson('/api/expenses?all=true');

        $response->assertStatus(200);
        $data = $response->json();
        $expenses = $this->getExpensesFromResponse($data);

        $this->assertArrayHasKey('payer', $expenses[0]);
        $this->assertEquals($payer->id, $expenses[0]['payer']['id']);
        $this->assertEquals('Payer Member', $expenses[0]['payer']['name']);
    }

    /**
     * GET /api/expenses includes null payer when no payer (Treasury)
     * Requirements: 11.7, 3.2
     */
    public function test_index_includes_null_payer_for_treasury(): void
    {
        $this->createOperationalExpense(['payer_id' => null]);

        $response = $this->getJson('/api/expenses?all=true');

        $response->assertStatus(200);
        $data = $response->json();
        $expenses = $this->getExpensesFromResponse($data);

        $this->assertNull($expenses[0]['payer']);
    }

    /**
     * GET /api/expenses includes splits with member data for shared expenses
     * Requirements: 11.7
     */
    public function test_index_includes_splits_for_shared_expenses(): void
    {
        $payer   = $this->createMember('Payer');
        $member1 = $this->createMember('Member 1');
        $member2 = $this->createMember('Member 2');

        $this->createSharedExpense($payer, [$member1->id, $member2->id], 100.00);

        $response = $this->getJson('/api/expenses?all=true');

        $response->assertStatus(200);
        $data = $response->json();
        $expenses = $this->getExpensesFromResponse($data);

        $this->assertArrayHasKey('splits', $expenses[0]);
        $this->assertCount(2, $expenses[0]['splits']);
        $this->assertArrayHasKey('member', $expenses[0]['splits'][0]);
    }

    /**
     * GET /api/expenses includes core expense fields
     * Requirements: 11.2
     */
    public function test_index_includes_core_expense_fields(): void
    {
        $this->createOperationalExpense([
            'reference'      => 'EXP-0001',
            'category'       => 'Fuel',
            'amount'         => 250.00,
            'payment_method' => 'cash',
        ]);

        $response = $this->getJson('/api/expenses?all=true');

        $response->assertStatus(200);
        $data = $response->json();
        $expenses = $this->getExpensesFromResponse($data);
        
        $this->assertEquals('EXP-0001', $expenses[0]['reference']);
        $this->assertEquals('operational', $expenses[0]['expense_type']);
        $this->assertEquals('Fuel', $expenses[0]['category']);
        $this->assertEquals('cash', $expenses[0]['payment_method']);
    }

    // ─── GET /api/expenses/{id} ───────────────────────────────────────────────

    /**
     * GET /api/expenses/{id} returns 200 with expense data
     * Requirements: 11.3, 11.5, 11.6
     */
    public function test_show_returns_200_with_expense_data(): void
    {
        $expense = $this->createOperationalExpense();

        $response = $this->getJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $expense->id]);
    }

    /**
     * GET /api/expenses/{id} returns 404 for non-existent expense
     * Requirements: 11.3, 11.6
     */
    public function test_show_returns_404_for_nonexistent_expense(): void
    {
        $response = $this->getJson('/api/expenses/99999');

        $response->assertStatus(404);
    }

    /**
     * GET /api/expenses/{id} includes all relationships
     * Requirements: 11.3, 11.7
     */
    public function test_show_includes_all_relationships(): void
    {
        $payer   = $this->createMember('Payer');
        $member1 = $this->createMember('Member 1');
        $member2 = $this->createMember('Member 2');

        $expense = $this->createSharedExpense($payer, [$member1->id, $member2->id], 100.00);

        $response = $this->getJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('payer', $data);
        $this->assertArrayHasKey('affected_member', $data);
        $this->assertArrayHasKey('splits', $data);
        $this->assertCount(2, $data['splits']);
        $this->assertArrayHasKey('member', $data['splits'][0]);
        $this->assertEquals($payer->id, $data['payer']['id']);
    }

    /**
     * GET /api/expenses/{id} returns correct expense data
     * Requirements: 11.3
     */
    public function test_show_returns_correct_expense_data(): void
    {
        $expense = $this->createOperationalExpense([
            'reference'      => 'EXP-0001',
            'category'       => 'Rent',
            'amount'         => 500.00,
            'payment_method' => 'transfer',
            'description'    => 'Monthly rent',
        ]);

        $response = $this->getJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'reference'      => 'EXP-0001',
                     'expense_type'   => 'operational',
                     'category'       => 'Rent',
                     'payment_method' => 'transfer',
                     'description'    => 'Monthly rent',
                 ]);
    }

    /**
     * GET /api/expenses/{id} includes affected_member for personal expenses
     * Requirements: 11.7, 1.3
     */
    public function test_show_includes_affected_member_for_personal_expense(): void
    {
        $affected = $this->createMember('Affected Member');
        $expense  = $this->createOperationalExpense([
            'expense_type'       => 'personal',
            'affected_member_id' => $affected->id,
        ]);

        $response = $this->getJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('affected_member', $data);
        $this->assertEquals($affected->id, $data['affected_member']['id']);
        $this->assertEquals('Affected Member', $data['affected_member']['name']);
    }
}
