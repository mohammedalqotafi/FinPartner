<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Transaction;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberFinancialsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_member_financials_structure()
    {
        // Arrange
        $member = Member::factory()->create(['name' => 'أحمد']);

        // Act
        $response = $this->getJson("/api/members/{$member->id}/financials");

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'member' => ['id', 'name', 'email', 'phone', 'opening_balance'],
            'deposits' => ['total', 'count', 'items'],
            'withdrawals' => ['total', 'count', 'items'],
            'expenses' => [
                'paid' => ['total', 'your_share', 'paid_for_others', 'count', 'items'],
                'owed' => ['total', 'count', 'items'],
                'summary',
            ],
            'summary' => [
                'opening_balance',
                'total_deposits',
                'total_withdrawals',
                'total_paid_for_others',
                'total_you_owe',
                'net_balance',
                'calculated_balance',
            ],
        ]);
    }

    /** @test */
    public function it_calculates_deposits_correctly()
    {
        // Arrange
        $member = Member::factory()->create(['opening_balance' => 0]);
        
        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'deposit',
            'amount' => 1000,
            'status' => 'completed',
        ]);

        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'adjustment',
            'amount' => 500,
            'status' => 'completed',
        ]);

        // Act
        $response = $this->getJson("/api/members/{$member->id}/financials");

        // Assert
        $response->assertOk();
        $this->assertEquals(1500, $response->json('deposits.total'));
        $this->assertEquals(2, $response->json('deposits.count'));
    }

    /** @test */
    public function it_calculates_withdrawals_correctly()
    {
        // Arrange
        $member = Member::factory()->create();
        
        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'withdraw',
            'amount' => 300,
            'status' => 'completed',
        ]);

        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'transfer',
            'amount' => 200,
            'status' => 'completed',
        ]);

        // Act
        $response = $this->getJson("/api/members/{$member->id}/financials");

        // Assert
        $response->assertOk();
        $this->assertEquals(500, $response->json('withdrawals.total'));
        $this->assertEquals(2, $response->json('withdrawals.count'));
    }

    /** @test */
    public function it_calculates_paid_expenses_correctly()
    {
        // Arrange
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $member1 = Member::factory()->create(['name' => 'محمد']);
        $member2 = Member::factory()->create(['name' => 'علي']);

        // مصروف مشترك: أحمد دفع 1000، نصيبه 250
        $expense = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $payer->id,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $payer->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member1->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member2->id,
            'amount' => 500,
        ]);

        // Act
        $response = $this->getJson("/api/members/{$payer->id}/financials");

        // Assert
        $response->assertOk();
        $this->assertEquals(1000, $response->json('expenses.paid.total'));
        $this->assertEquals(250, $response->json('expenses.paid.your_share'));
        $this->assertEquals(750, $response->json('expenses.paid.paid_for_others')); // 1000 - 250
    }

    /** @test */
    public function it_calculates_owed_expenses_correctly()
    {
        // Arrange
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $participant = Member::factory()->create(['name' => 'محمد']);

        // مصروف مشترك: أحمد دفع 1000، محمد نصيبه 400
        $expense = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $payer->id,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $payer->id,
            'amount' => 600,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $participant->id,
            'amount' => 400,
        ]);

        // Act
        $response = $this->getJson("/api/members/{$participant->id}/financials");

        // Assert
        $response->assertOk();
        $this->assertEquals(400, $response->json('expenses.owed.total'));
        $this->assertEquals(1, $response->json('expenses.owed.count'));
        $this->assertEquals('أحمد', $response->json('expenses.owed.items.0.paid_by'));
    }

    /** @test */
    public function it_calculates_net_balance_correctly()
    {
        // Arrange
        $member = Member::factory()->create(['opening_balance' => 500]);
        
        // إيداع 1000
        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'deposit',
            'amount' => 1000,
            'status' => 'completed',
        ]);

        // سحب 200
        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'withdraw',
            'amount' => 200,
            'status' => 'completed',
        ]);

        // Act
        $response = $this->getJson("/api/members/{$member->id}/financials");

        // Assert
        $response->assertOk();
        $summary = $response->json('summary');
        
        // 500 (opening) + 1000 (deposit) - 200 (withdraw) = 1300
        $this->assertEquals(1300, $summary['net_balance']);
    }

    /** @test */
    public function it_handles_complex_scenario()
    {
        // Arrange
        $member = Member::factory()->create(['opening_balance' => 1000, 'name' => 'أحمد']);
        $other = Member::factory()->create(['name' => 'محمد']);

        // إيداع 2000
        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'deposit',
            'amount' => 2000,
            'status' => 'completed',
        ]);

        // سحب 500
        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'withdraw',
            'amount' => 500,
            'status' => 'completed',
        ]);

        // دفع مصروف مشترك 1000 (نصيبه 300)
        $expense1 = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $member->id,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense1->id,
            'member_id' => $member->id,
            'amount' => 300,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense1->id,
            'member_id' => $other->id,
            'amount' => 700,
        ]);

        // شارك في مصروف دفعه محمد 600 (نصيب أحمد 200)
        $expense2 = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 600,
            'payer_id' => $other->id,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense2->id,
            'member_id' => $member->id,
            'amount' => 200,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense2->id,
            'member_id' => $other->id,
            'amount' => 400,
        ]);

        // Act
        $response = $this->getJson("/api/members/{$member->id}/financials");

        // Assert
        $response->assertOk();
        $summary = $response->json('summary');

        // الحساب:
        // 1000 (opening) + 2000 (deposit) - 500 (withdraw) + 700 (paid for others) - 200 (owed) = 3000
        $this->assertEquals(1000, $summary['opening_balance']);
        $this->assertEquals(2000, $summary['total_deposits']);
        $this->assertEquals(500, $summary['total_withdrawals']);
        $this->assertEquals(700, $summary['total_paid_for_others']);
        $this->assertEquals(200, $summary['total_you_owe']);
        $this->assertEquals(3000, $summary['net_balance']);
    }

    /** @test */
    public function it_excludes_pending_transactions()
    {
        // Arrange
        $member = Member::factory()->create();
        
        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'deposit',
            'amount' => 1000,
            'status' => 'completed',
        ]);

        Transaction::factory()->create([
            'member_id' => $member->id,
            'type' => 'deposit',
            'amount' => 500,
            'status' => 'pending', // معلقة
        ]);

        // Act
        $response = $this->getJson("/api/members/{$member->id}/financials");

        // Assert
        $response->assertOk();
        $this->assertEquals(1000, $response->json('deposits.total')); // فقط المكتملة
        $this->assertEquals(1, $response->json('deposits.count'));
    }

    /** @test */
    public function it_handles_member_with_no_activity()
    {
        // Arrange
        $member = Member::factory()->create(['opening_balance' => 100]);

        // Act
        $response = $this->getJson("/api/members/{$member->id}/financials");

        // Assert
        $response->assertOk();
        $this->assertEquals(0, $response->json('deposits.total'));
        $this->assertEquals(0, $response->json('withdrawals.total'));
        $this->assertEquals(0, $response->json('expenses.paid.total'));
        $this->assertEquals(0, $response->json('expenses.owed.total'));
        $this->assertEquals(100, $response->json('summary.net_balance'));
    }

    /** @test */
    public function it_returns_404_for_nonexistent_member()
    {
        // Act
        $response = $this->getJson('/api/members/99999/financials');

        // Assert
        $response->assertNotFound();
    }

    /** @test */
    public function it_includes_personal_expenses_in_owed()
    {
        // Arrange
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $affected = Member::factory()->create(['name' => 'محمد']);

        // مصروف شخصي: أحمد دفع 300 لمحمد
        Expense::factory()->create([
            'expense_type' => 'personal',
            'amount' => 300,
            'payer_id' => $payer->id,
            'affected_member_id' => $affected->id,
        ]);

        // Act
        $response = $this->getJson("/api/members/{$affected->id}/financials");

        // Assert
        $response->assertOk();
        $this->assertEquals(300, $response->json('expenses.owed.total'));
        $this->assertEquals('personal', $response->json('expenses.owed.items.0.type'));
    }
}
