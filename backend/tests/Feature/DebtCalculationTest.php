<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use App\Services\DebtCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Debt Calculation Service
 * 
 * اختبار حساب الديون بين الأعضاء (Who Owes Who)
 */
class DebtCalculationTest extends TestCase
{
    use RefreshDatabase;

    private DebtCalculationService $debtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->debtService = new DebtCalculationService();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMember(string $name): Member
    {
        return Member::create([
            'name' => $name,
            'opening_balance' => 0,
            'balance' => 0,
        ]);
    }

    private function createSharedExpense(Member $payer, array $memberSplits, string $reference = null): Expense
    {
        $totalAmount = array_sum(array_column($memberSplits, 'amount'));
        
        $expense = Expense::create([
            'reference' => $reference ?? Expense::generateReference(),
            'expense_type' => 'shared',
            'category' => 'Test Category',
            'amount' => $totalAmount,
            'payer_id' => $payer->id,
            'payment_method' => 'cash',
            'expense_datetime' => now(),
        ]);

        foreach ($memberSplits as $split) {
            ExpenseSplit::create([
                'expense_id' => $expense->id,
                'member_id' => $split['member_id'],
                'amount' => $split['amount'],
            ]);
        }

        return $expense;
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    /**
     * اختبار السيناريو الأساسي: عضو واحد يدفع لعضوين آخرين
     */
    public function test_basic_debt_calculation(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');
        $charlie = $this->createMember('Charlie');

        // Alice دفعت 300 ريال، والتقسيم متساوي (100 لكل واحد)
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
            ['member_id' => $charlie->id, 'amount' => 100],
        ]);

        $debts = $this->debtService->calculateDebts();

        $this->assertCount(2, $debts);
        
        // Bob يدين لـ Alice بـ 100
        $bobDebt = $debts->firstWhere('debtor_id', $bob->id);
        $this->assertEquals($alice->id, $bobDebt['creditor_id']);
        $this->assertEquals('100.00', $bobDebt['amount']);
        
        // Charlie يدين لـ Alice بـ 100
        $charlieDebt = $debts->firstWhere('debtor_id', $charlie->id);
        $this->assertEquals($alice->id, $charlieDebt['creditor_id']);
        $this->assertEquals('100.00', $charlieDebt['amount']);
    }

    /**
     * اختبار Netting: ديون متبادلة بين عضوين
     */
    public function test_debt_netting(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        // Alice دفعت 200، Bob حصته 100
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        // Bob دفع 150، Alice حصتها 50
        $this->createSharedExpense($bob, [
            ['member_id' => $alice->id, 'amount' => 50],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        $debts = $this->debtService->calculateDebts();

        // النتيجة بعد Netting: Bob يدين لـ Alice بـ 50 (100 - 50)
        $this->assertCount(1, $debts);
        $debt = $debts->first();
        $this->assertEquals($bob->id, $debt['debtor_id']);
        $this->assertEquals($alice->id, $debt['creditor_id']);
        $this->assertEquals('50.00', $debt['amount']);
    }

    /**
     * اختبار تجاهل المصروفات المدفوعة من الخزانة
     */
    public function test_ignores_treasury_payments(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        // مصروف مدفوع من الخزانة (payer_id = null)
        $expense = Expense::create([
            'reference' => Expense::generateReference(),
            'expense_type' => 'shared',
            'category' => 'Treasury Expense',
            'amount' => 200,
            'payer_id' => null, // الخزانة
            'payment_method' => 'cash',
            'expense_datetime' => now(),
        ]);

        ExpenseSplit::create([
            'expense_id' => $expense->id,
            'member_id' => $alice->id,
            'amount' => 100,
        ]);

        ExpenseSplit::create([
            'expense_id' => $expense->id,
            'member_id' => $bob->id,
            'amount' => 100,
        ]);

        $debts = $this->debtService->calculateDebts();

        // لا يجب أن تكون هناك ديون لأن الدافع هو الخزانة
        $this->assertCount(0, $debts);
    }

    /**
     * اختبار تجاهل حالات الدفع للنفس
     */
    public function test_ignores_self_payment(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        // Alice دفعت وحصتها أكبر من حصة Bob
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 150], // Alice حصتها أكبر
            ['member_id' => $bob->id, 'amount' => 50],
        ]);

        $debts = $this->debtService->calculateDebts();

        // فقط Bob يدين لـ Alice، Alice لا تدين لنفسها
        $this->assertCount(1, $debts);
        $debt = $debts->first();
        $this->assertEquals($bob->id, $debt['debtor_id']);
        $this->assertEquals($alice->id, $debt['creditor_id']);
        $this->assertEquals('50.00', $debt['amount']);
    }

    /**
     * اختبار سيناريو معقد مع عدة مصروفات وأعضاء
     */
    public function test_complex_multiple_expenses(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');
        $charlie = $this->createMember('Charlie');

        // المصروف الأول: Alice دفعت 300
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
            ['member_id' => $charlie->id, 'amount' => 100],
        ]);

        // المصروف الثاني: Bob دفع 200
        $this->createSharedExpense($bob, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        // المصروف الثالث: Charlie دفع 150
        $this->createSharedExpense($charlie, [
            ['member_id' => $alice->id, 'amount' => 50],
            ['member_id' => $charlie->id, 'amount' => 100],
        ]);

        $debts = $this->debtService->calculateDebts();

        // التحقق من النتائج المتوقعة بعد Netting
        $this->assertGreaterThan(0, $debts->count());
        
        // التحقق من أن جميع المبالغ موجبة
        foreach ($debts as $debt) {
            $this->assertGreaterThan(0, (float) $debt['amount']);
        }
    }

    /**
     * اختبار ملخص الديون لعضو معين
     */
    public function test_member_debt_summary(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        // Alice دفعت 200، Bob حصته 100
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        $summary = $this->debtService->getMemberDebtSummary($alice->id);

        $this->assertEquals('100.00', $summary['total_owed']);  // Bob يدين لها
        $this->assertEquals('0.00', $summary['total_owing']);   // هي لا تدين لأحد
        $this->assertEquals('100.00', $summary['net_position']); // موقف إيجابي (دائنة)
    }

    /**
     * اختبار عدم وجود ديون
     */
    public function test_no_debts_when_no_shared_expenses(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        // لا توجد مصروفات مشتركة
        $debts = $this->debtService->calculateDebts();
        $this->assertCount(0, $debts);

        $summary = $this->debtService->getMemberDebtSummary($alice->id);
        $this->assertEquals('0.00', $summary['total_owed']);
        $this->assertEquals('0.00', $summary['total_owing']);
        $this->assertEquals('0.00', $summary['net_position']);
    }

    /**
     * اختبار دقة الحسابات العشرية
     */
    public function test_decimal_precision(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        // مبالغ بكسور عشرية
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 33.33],
            ['member_id' => $bob->id, 'amount' => 33.34], // Penny routing
        ]);

        $debts = $this->debtService->calculateDebts();

        $this->assertCount(1, $debts);
        $debt = $debts->first();
        $this->assertEquals('33.34', $debt['amount']);
    }
}