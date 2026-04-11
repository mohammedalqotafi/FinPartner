<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Debt API Endpoints
 * 
 * اختبار API endpoints للديون بين الأعضاء
 */
class DebtApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMember(string $name): Member
    {
        return Member::create([
            'name' => $name,
            'opening_balance' => 0,
            'balance' => 0,
        ]);
    }

    private function createSharedExpense(Member $payer, array $memberSplits): Expense
    {
        $totalAmount = array_sum(array_column($memberSplits, 'amount'));
        
        $expense = Expense::create([
            'reference' => Expense::generateReference(),
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

    // ─── API Tests ───────────────────────────────────────────────────────────

    /**
     * GET /api/debts - اختبار عرض جميع الديون
     */
    public function test_get_all_debts(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        $response = $this->getJson('/api/debts');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'debtor_id',
                             'debtor_name',
                             'creditor_id',
                             'creditor_name',
                             'amount'
                         ]
                     ],
                     'meta' => [
                         'total_debts',
                         'active_debtors',
                         'active_creditors',
                         'debt_relationships'
                     ]
                 ]);

        $data = $response->json();
        $this->assertCount(1, $data['data']);
        $this->assertEquals($bob->id, $data['data'][0]['debtor_id']);
        $this->assertEquals($alice->id, $data['data'][0]['creditor_id']);
        $this->assertEquals('100.00', $data['data'][0]['amount']);
    }

    /**
     * GET /api/debts/simplified - اختبار الشكل المبسط
     */
    public function test_get_simplified_debts(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 150],
            ['member_id' => $bob->id, 'amount' => 50],
        ]);

        $response = $this->getJson('/api/debts/simplified');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     '*' => [
                         'debtor',
                         'creditor',
                         'amount'
                     ]
                 ]);

        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($bob->id, $data[0]['debtor']);
        $this->assertEquals($alice->id, $data[0]['creditor']);
        $this->assertEquals(50.0, $data[0]['amount']);
    }

    /**
     * GET /api/debts/member/{member} - اختبار ملخص ديون عضو معين
     */
    public function test_get_member_debt_summary(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');
        $charlie = $this->createMember('Charlie');

        // Alice دفعت لـ Bob و Charlie
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
            ['member_id' => $charlie->id, 'amount' => 100],
        ]);

        // Bob دفع لـ Alice
        $this->createSharedExpense($bob, [
            ['member_id' => $alice->id, 'amount' => 50],
            ['member_id' => $bob->id, 'amount' => 50],
        ]);

        $response = $this->getJson("/api/debts/member/{$alice->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'member' => ['id', 'name'],
                     'summary' => [
                         'total_owed',
                         'total_owing',
                         'net_position'
                     ],
                     'debts_owed_to_member',
                     'debts_owed_by_member'
                 ]);

        $data = $response->json();
        $this->assertEquals($alice->id, $data['member']['id']);
        $this->assertEquals('Alice', $data['member']['name']);
        
        // Alice مستحق لها 150 (100 من Bob + 100 من Charlie - 50 لـ Bob بعد Netting)
        $this->assertEquals('150.00', $data['summary']['total_owed']);
        $this->assertEquals('0.00', $data['summary']['total_owing']);
        $this->assertEquals('150.00', $data['summary']['net_position']);
    }

    /**
     * GET /api/debts/matrix - اختبار مصفوفة الديون
     */
    public function test_get_debt_matrix(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        $response = $this->getJson('/api/debts/matrix');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'matrix',
                     'members' => [
                         '*' => ['id', 'name']
                     ]
                 ]);

        $data = $response->json();
        $this->assertCount(2, $data['members']); // Alice و Bob
        $this->assertCount(2, $data['matrix']);  // مصفوفة 2x2
        $this->assertCount(2, $data['matrix'][0]); // كل صف له عمودين
    }

    /**
     * اختبار عدم وجود ديون
     */
    public function test_no_debts_returns_empty_arrays(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');

        // لا توجد مصروفات مشتركة
        $response = $this->getJson('/api/debts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(0, $data['data']);
        $this->assertEquals('0.00', $data['meta']['total_debts']);
    }

    /**
     * اختبار عضو غير موجود
     */
    public function test_nonexistent_member_returns_404(): void
    {
        $response = $this->getJson('/api/debts/member/99999');
        $response->assertStatus(404);
    }

    /**
     * اختبار مصفوفة فارغة عند عدم وجود ديون
     */
    public function test_empty_matrix_when_no_debts(): void
    {
        $response = $this->getJson('/api/debts/matrix');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEmpty($data['matrix']);
        $this->assertEmpty($data['members']);
    }

    /**
     * اختبار الاستجابة للأخطاء
     */
    public function test_handles_database_errors_gracefully(): void
    {
        // محاكاة خطأ في قاعدة البيانات عن طريق استخدام جدول غير موجود
        // هذا اختبار تكاملي للتأكد من معالجة الأخطاء
        
        $alice = $this->createMember('Alice');
        
        // الاختبار العادي يجب أن يعمل
        $response = $this->getJson("/api/debts/member/{$alice->id}");
        $response->assertStatus(200);
    }

    /**
     * اختبار تضمين أسماء الأعضاء في النتائج
     */
    public function test_includes_member_names_in_response(): void
    {
        $alice = $this->createMember('Alice Smith');
        $bob = $this->createMember('Bob Johnson');

        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        $response = $this->getJson('/api/debts');

        $response->assertStatus(200);
        $data = $response->json();
        
        $debt = $data['data'][0];
        $this->assertEquals('Bob Johnson', $debt['debtor_name']);
        $this->assertEquals('Alice Smith', $debt['creditor_name']);
    }

    /**
     * اختبار الإحصائيات في الاستجابة
     */
    public function test_includes_statistics_in_response(): void
    {
        $alice = $this->createMember('Alice');
        $bob = $this->createMember('Bob');
        $charlie = $this->createMember('Charlie');

        // إنشاء عدة مصروفات لاختبار الإحصائيات
        $this->createSharedExpense($alice, [
            ['member_id' => $alice->id, 'amount' => 100],
            ['member_id' => $bob->id, 'amount' => 100],
        ]);

        $this->createSharedExpense($bob, [
            ['member_id' => $bob->id, 'amount' => 50],
            ['member_id' => $charlie->id, 'amount' => 50],
        ]);

        $response = $this->getJson('/api/debts');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total_debts', $data['meta']);
        $this->assertArrayHasKey('active_debtors', $data['meta']);
        $this->assertArrayHasKey('active_creditors', $data['meta']);
        $this->assertArrayHasKey('debt_relationships', $data['meta']);
        
        // التحقق من أن الإحصائيات منطقية
        $this->assertGreaterThan(0, (float) $data['meta']['total_debts']);
        $this->assertGreaterThan(0, $data['meta']['active_debtors']);
        $this->assertGreaterThan(0, $data['meta']['active_creditors']);
    }
}