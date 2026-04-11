<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات Member Model - حساب الرصيد الموحد
 *
 * Feature: expense-management-system
 * Property 7: Unified Ledger Balance Calculation
 * Validates: Requirements 4.1
 */
class MemberModelTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * إنشاء عضو بـ opening_balance محدد
     */
    private function makeMember(float $openingBalance = 0.0): Member
    {
        return Member::create([
            'name'            => 'Test Member ' . uniqid(),
            'opening_balance' => $openingBalance,
            'balance'         => $openingBalance,
        ]);
    }

    /**
     * إضافة معاملة مكتملة للعضو
     */
    private function addTransaction(Member $member, string $type, float $amount): void
    {
        Transaction::create([
            'reference'      => 'TX-' . uniqid(),
            'member_id'      => $member->id,
            'type'           => $type,
            'amount'         => $amount,
            'transaction_at' => now(),
            'status'         => 'completed',
        ]);
    }

    /**
     * إضافة تقسيم مصروف مشترك للعضو
     */
    private function addExpenseSplit(Member $member, float $amount): void
    {
        $expense = Expense::create([
            'reference'        => 'EXP-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT) . uniqid(),
            'expense_type'     => 'shared',
            'category'         => 'Test',
            'amount'           => $amount,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
        ]);

        ExpenseSplit::create([
            'expense_id' => $expense->id,
            'member_id'  => $member->id,
            'amount'     => $amount,
        ]);
    }

    /**
     * إضافة مصروف شخصي للعضو
     */
    private function addPersonalExpense(Member $member, float $amount): void
    {
        Expense::create([
            'reference'          => 'EXP-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT) . uniqid(),
            'expense_type'       => 'personal',
            'affected_member_id' => $member->id,
            'category'           => 'Test',
            'amount'             => $amount,
            'payment_method'     => 'cash',
            'expense_datetime'   => now(),
        ]);
    }

    /**
     * إضافة مصروف دفعه العضو (payer)
     */
    private function addPaidExpense(Member $member, float $amount): void
    {
        Expense::create([
            'reference'        => 'EXP-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT) . uniqid(),
            'expense_type'     => 'operational',
            'payer_id'         => $member->id,
            'category'         => 'Test',
            'amount'           => $amount,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
        ]);
    }

    // ─── Property 7: Unified Ledger Balance Calculation ───────────────────────
    // For any member, the calculated balance should equal:
    // opening_balance + deposits - withdrawals - shared_splits - personal_expenses + paid_expenses
    // Validates: Requirements 4.1

    /**
     * Property 7: Unified Ledger Balance Calculation
     * Validates: Requirements 4.1
     *
     * For any member with random opening_balance, deposits, withdrawals,
     * shared_splits, personal_expenses, and paid_expenses, the calculated_balance
     * accessor must equal the formula:
     * opening_balance + deposits - withdrawals - shared_splits - personal_expenses + paid_expenses
     *
     * Runs 100 iterations with random data.
     */
    public function test_calculated_balance_matches_unified_ledger_formula(): void
    {
        // Feature: expense-management-system, Property 7: Unified Ledger Balance Calculation

        for ($i = 0; $i < 100; $i++) {
            // Generate random financial values (rounded to 2 decimal places)
            $openingBalance    = round(mt_rand(0, 100000) / 100, 2);
            $depositAmount     = round(mt_rand(100, 50000) / 100, 2);
            $withdrawalAmount  = round(mt_rand(100, 20000) / 100, 2);
            $sharedSplitAmount = round(mt_rand(100, 10000) / 100, 2);
            $personalAmount    = round(mt_rand(100, 10000) / 100, 2);
            $paidAmount        = round(mt_rand(100, 10000) / 100, 2);

            $member = $this->makeMember($openingBalance);

            // Add transactions
            $this->addTransaction($member, 'deposit', $depositAmount);
            $this->addTransaction($member, 'withdraw', $withdrawalAmount);

            // Add expense-related entries
            $this->addExpenseSplit($member, $sharedSplitAmount);
            $this->addPersonalExpense($member, $personalAmount);
            $this->addPaidExpense($member, $paidAmount);

            // Compute expected balance using the formula from Requirements 4.1
            $expectedBalance = $openingBalance
                + $depositAmount
                - $withdrawalAmount
                - $sharedSplitAmount
                - $personalAmount
                + $paidAmount;

            $actualBalance = $member->fresh()->calculated_balance;

            $this->assertEqualsWithDelta(
                $expectedBalance,
                $actualBalance,
                0.01,
                "Iteration {$i}: calculated_balance mismatch. "
                . "Expected {$expectedBalance}, got {$actualBalance}. "
                . "opening={$openingBalance}, deposit={$depositAmount}, "
                . "withdrawal={$withdrawalAmount}, split={$sharedSplitAmount}, "
                . "personal={$personalAmount}, paid={$paidAmount}"
            );
        }
    }

    /**
     * Property 7: Unified Ledger Balance Calculation - Zero baseline
     * Validates: Requirements 4.1
     *
     * A member with no transactions and no expenses should have
     * calculated_balance equal to opening_balance.
     */
    public function test_calculated_balance_equals_opening_balance_with_no_activity(): void
    {
        // Feature: expense-management-system, Property 7: Unified Ledger Balance Calculation

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(0, 100000) / 100, 2);
            $member = $this->makeMember($openingBalance);

            $this->assertEqualsWithDelta(
                $openingBalance,
                $member->calculated_balance,
                0.01,
                "Iteration {$i}: calculated_balance should equal opening_balance when no activity"
            );
        }
    }

    /**
     * Property 7: Unified Ledger Balance Calculation - Additive deposits
     * Validates: Requirements 4.1
     *
     * For any member, adding N deposits should increase calculated_balance
     * by the sum of those deposits.
     */
    public function test_deposits_increase_calculated_balance(): void
    {
        // Feature: expense-management-system, Property 7: Unified Ledger Balance Calculation

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(0, 10000) / 100, 2);
            $member = $this->makeMember($openingBalance);

            $totalDeposited = 0.0;
            $numDeposits = mt_rand(1, 5);

            for ($j = 0; $j < $numDeposits; $j++) {
                $amount = round(mt_rand(100, 5000) / 100, 2);
                $this->addTransaction($member, 'deposit', $amount);
                $totalDeposited += $amount;
            }

            $expectedBalance = $openingBalance + $totalDeposited;

            $this->assertEqualsWithDelta(
                $expectedBalance,
                $member->fresh()->calculated_balance,
                0.01,
                "Iteration {$i}: deposits should increase calculated_balance"
            );
        }
    }

    /**
     * Property 7: Unified Ledger Balance Calculation - Shared splits reduce balance
     * Validates: Requirements 4.1
     *
     * For any member, adding shared expense splits should decrease
     * calculated_balance by the sum of those splits.
     */
    public function test_shared_splits_decrease_calculated_balance(): void
    {
        // Feature: expense-management-system, Property 7: Unified Ledger Balance Calculation

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(10000, 100000) / 100, 2);
            $member = $this->makeMember($openingBalance);

            $totalSplits = 0.0;
            $numSplits = mt_rand(1, 5);

            for ($j = 0; $j < $numSplits; $j++) {
                $amount = round(mt_rand(100, 2000) / 100, 2);
                $this->addExpenseSplit($member, $amount);
                $totalSplits += $amount;
            }

            $expectedBalance = $openingBalance - $totalSplits;

            $this->assertEqualsWithDelta(
                $expectedBalance,
                $member->fresh()->calculated_balance,
                0.01,
                "Iteration {$i}: shared splits should decrease calculated_balance"
            );
        }
    }

    /**
     * Property 7: Unified Ledger Balance Calculation - Paid expenses increase balance
     * Validates: Requirements 4.1
     *
     * For any member who paid expenses on behalf of others, their
     * calculated_balance should increase by the sum of those paid amounts.
     */
    public function test_paid_expenses_increase_calculated_balance(): void
    {
        // Feature: expense-management-system, Property 7: Unified Ledger Balance Calculation

        for ($i = 0; $i < 100; $i++) {
            $openingBalance = round(mt_rand(0, 10000) / 100, 2);
            $member = $this->makeMember($openingBalance);

            $totalPaid = 0.0;
            $numPaid = mt_rand(1, 5);

            for ($j = 0; $j < $numPaid; $j++) {
                $amount = round(mt_rand(100, 5000) / 100, 2);
                $this->addPaidExpense($member, $amount);
                $totalPaid += $amount;
            }

            $expectedBalance = $openingBalance + $totalPaid;

            $this->assertEqualsWithDelta(
                $expectedBalance,
                $member->fresh()->calculated_balance,
                0.01,
                "Iteration {$i}: paid expenses should increase calculated_balance"
            );
        }
    }

    // ─── Relationship Tests ───────────────────────────────────────────────────

    /**
     * expenseSplits() يجب أن يُرجع جميع التقسيمات المرتبطة بالعضو
     * Requirements: 17.1
     */
    public function test_expense_splits_relationship(): void
    {
        $member = $this->makeMember();
        $this->addExpenseSplit($member, 50.00);
        $this->addExpenseSplit($member, 75.00);

        $this->assertCount(2, $member->fresh()->expenseSplits);
    }

    /**
     * personalExpenses() يجب أن يُرجع المصروفات الشخصية للعضو فقط
     * Requirements: 17.1
     */
    public function test_personal_expenses_relationship(): void
    {
        $member = $this->makeMember();
        $this->addPersonalExpense($member, 100.00);
        $this->addPersonalExpense($member, 200.00);
        // Add an operational expense (should not appear)
        $this->addPaidExpense($member, 50.00);

        $personalExpenses = $member->fresh()->personalExpenses;
        $this->assertCount(2, $personalExpenses);
        $this->assertTrue($personalExpenses->every(fn($e) => $e->expense_type === 'personal'));
    }

    /**
     * paidExpenses() يجب أن يُرجع المصروفات التي دفعها العضو
     * Requirements: 17.1
     */
    public function test_paid_expenses_relationship(): void
    {
        $member = $this->makeMember();
        $this->addPaidExpense($member, 300.00);
        $this->addPaidExpense($member, 150.00);

        $this->assertCount(2, $member->fresh()->paidExpenses);
    }
}
