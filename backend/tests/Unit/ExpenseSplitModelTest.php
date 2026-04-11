<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات ExpenseSplit Model
 * Validates: Requirements 2.1, 17.1
 */
class ExpenseSplitModelTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMember(): Member
    {
        return Member::create([
            'name'            => 'Test Member',
            'opening_balance' => 0,
            'balance'         => 0,
        ]);
    }

    private function createExpense(): Expense
    {
        return Expense::create([
            'reference'        => Expense::generateReference(),
            'expense_type'     => 'shared',
            'category'         => 'Test',
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
        ]);
    }

    // ─── Cast Tests ───────────────────────────────────────────────────────────

    /**
     * amount يجب أن يُعاد كـ string بدقة عشريتين (decimal:2 cast)
     * Requirements: 2.1
     */
    public function test_amount_is_cast_to_decimal_with_two_places(): void
    {
        $member  = $this->createMember();
        $expense = $this->createExpense();

        $split = ExpenseSplit::create([
            'expense_id' => $expense->id,
            'member_id'  => $member->id,
            'amount'     => 33.333,
        ]);

        // decimal:2 cast returns a string representation with 2 decimal places
        $this->assertSame('33.33', $split->amount);
    }

    /**
     * amount يجب أن يحتفظ بالقيمة الصحيحة بعد الحفظ والاسترجاع
     * Requirements: 2.1
     */
    public function test_amount_is_stored_and_retrieved_correctly(): void
    {
        $member  = $this->createMember();
        $expense = $this->createExpense();

        ExpenseSplit::create([
            'expense_id' => $expense->id,
            'member_id'  => $member->id,
            'amount'     => 50.25,
        ]);

        $split = ExpenseSplit::first();
        $this->assertSame('50.25', $split->amount);
    }

    // ─── Relationship Tests ───────────────────────────────────────────────────

    /**
     * expense() يجب أن يُرجع الـ Expense المرتبط
     * Requirements: 17.1
     */
    public function test_expense_relationship_returns_correct_expense(): void
    {
        $member  = $this->createMember();
        $expense = $this->createExpense();

        $split = ExpenseSplit::create([
            'expense_id' => $expense->id,
            'member_id'  => $member->id,
            'amount'     => 100.00,
        ]);

        $this->assertInstanceOf(Expense::class, $split->expense);
        $this->assertEquals($expense->id, $split->expense->id);
    }

    /**
     * member() يجب أن يُرجع الـ Member المرتبط
     * Requirements: 17.1
     */
    public function test_member_relationship_returns_correct_member(): void
    {
        $member  = $this->createMember();
        $expense = $this->createExpense();

        $split = ExpenseSplit::create([
            'expense_id' => $expense->id,
            'member_id'  => $member->id,
            'amount'     => 100.00,
        ]);

        $this->assertInstanceOf(Member::class, $split->member);
        $this->assertEquals($member->id, $split->member->id);
    }

    /**
     * يمكن الوصول إلى splits من خلال expense->splits()
     * Requirements: 17.1
     */
    public function test_expense_has_many_splits(): void
    {
        $member1 = $this->createMember();
        $member2 = Member::create([
            'name'            => 'Second Member',
            'opening_balance' => 0,
            'balance'         => 0,
        ]);
        $expense = $this->createExpense();

        ExpenseSplit::create(['expense_id' => $expense->id, 'member_id' => $member1->id, 'amount' => 50.00]);
        ExpenseSplit::create(['expense_id' => $expense->id, 'member_id' => $member2->id, 'amount' => 50.00]);

        $this->assertCount(2, $expense->splits);
    }
}
