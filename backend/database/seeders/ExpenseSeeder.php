<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $members = Member::all()->keyBy('name');

        if ($members->isEmpty()) {
            $this->command->warn('⚠️  لا يوجد أعضاء. شغّل FinPartnerSeeder أولاً.');
            return;
        }

        $ahmed  = $members['أحمد محمود'] ?? null;
        $sara   = $members['سارة خالد']  ?? null;
        $moh    = $members['محمد علي']   ?? null;
        $fatima = $members['فاطمة أحمد'] ?? null;
        $omar   = $members['عمر حسن']    ?? null;

        $expenses = [
            // ─── مصروف تشغيلي ─────────────────────────────────────────────
            [
                'data' => [
                    'reference'        => 'EXP-0001',
                    'expense_type'     => 'operational',
                    'category'         => 'إيجار',
                    'amount'           => 3000.00,
                    'payer_id'         => null,
                    'affected_member_id' => null,
                    'payment_method'   => 'transfer',
                    'description'      => 'إيجار المكتب - نوفمبر 2024',
                    'expense_datetime' => '2024-11-01 09:00:00',
                ],
                'splits' => [],
            ],
            // ─── مصروف تشغيلي مع دافع ─────────────────────────────────────
            [
                'data' => [
                    'reference'        => 'EXP-0002',
                    'expense_type'     => 'operational',
                    'category'         => 'مرافق',
                    'amount'           => 450.00,
                    'payer_id'         => $ahmed?->id,
                    'affected_member_id' => null,
                    'payment_method'   => 'cash',
                    'description'      => 'فاتورة الكهرباء والماء',
                    'expense_datetime' => '2024-11-05 11:00:00',
                ],
                'splits' => [],
            ],
            // ─── مصروف مشترك - تقسيم متساوي بين 3 أعضاء ─────────────────
            [
                'data' => [
                    'reference'        => 'EXP-0003',
                    'expense_type'     => 'shared',
                    'category'         => 'غيارات',
                    'amount'           => 900.00,
                    'payer_id'         => $moh?->id,
                    'affected_member_id' => null,
                    'payment_method'   => 'cash',
                    'description'      => 'غيارات السيارة المشتركة',
                    'expense_datetime' => '2024-11-08 14:00:00',
                ],
                'splits' => [
                    ['member_id' => $ahmed?->id,  'amount' => 300.00],
                    ['member_id' => $sara?->id,   'amount' => 300.00],
                    ['member_id' => $moh?->id,    'amount' => 300.00],
                ],
            ],
            // ─── مصروف مشترك - تقسيم يدوي غير متساوي ────────────────────
            [
                'data' => [
                    'reference'        => 'EXP-0004',
                    'expense_type'     => 'shared',
                    'category'         => 'تسويق',
                    'amount'           => 1000.00,
                    'payer_id'         => $fatima?->id,
                    'affected_member_id' => null,
                    'payment_method'   => 'transfer',
                    'description'      => 'حملة إعلانية على السوشيال ميديا',
                    'expense_datetime' => '2024-11-12 10:30:00',
                ],
                'splits' => [
                    ['member_id' => $ahmed?->id,  'amount' => 400.00],
                    ['member_id' => $fatima?->id, 'amount' => 350.00],
                    ['member_id' => $omar?->id,   'amount' => 250.00],
                ],
            ],
            // ─── مصروف شخصي ───────────────────────────────────────────────
            [
                'data' => [
                    'reference'        => 'EXP-0005',
                    'expense_type'     => 'personal',
                    'category'         => 'رواتب',
                    'amount'           => 2500.00,
                    'payer_id'         => null,
                    'affected_member_id' => $sara?->id,
                    'payment_method'   => 'transfer',
                    'description'      => 'راتب سارة - نوفمبر 2024',
                    'expense_datetime' => '2024-11-15 09:00:00',
                ],
                'splits' => [],
            ],
            // ─── مصروف مشترك بين جميع الأعضاء - Penny Routing ────────────
            [
                'data' => [
                    'reference'        => 'EXP-0006',
                    'expense_type'     => 'shared',
                    'category'         => 'أخرى',
                    'amount'           => 100.00,
                    'payer_id'         => $ahmed?->id,
                    'affected_member_id' => null,
                    'payment_method'   => 'cash',
                    'description'      => 'وجبة غداء جماعية - اختبار Penny Routing',
                    'expense_datetime' => '2024-11-20 13:00:00',
                ],
                // 100 / 3 = 33.33 × 3 = 99.99 → باقي 0.01 يذهب لعضو واحد
                'splits' => [
                    ['member_id' => $ahmed?->id, 'amount' => 33.34],
                    ['member_id' => $sara?->id,  'amount' => 33.33],
                    ['member_id' => $moh?->id,   'amount' => 33.33],
                ],
            ],
            // ─── مصروف شخصي مع دافع ───────────────────────────────────────
            [
                'data' => [
                    'reference'        => 'EXP-0007',
                    'expense_type'     => 'personal',
                    'category'         => 'مصاريف طبية',
                    'amount'           => 350.00,
                    'payer_id'         => $omar?->id,
                    'affected_member_id' => $omar?->id,
                    'payment_method'   => 'cash',
                    'description'      => 'مصاريف طبية - عمر حسن',
                    'expense_datetime' => '2024-11-22 16:00:00',
                ],
                'splits' => [],
            ],
        ];

        $transactionService = new TransactionService();
        $affectedMemberIds  = collect();

        foreach ($expenses as $item) {
            DB::beginTransaction();
            try {
                // تخطي إذا كان reference موجوداً
                if (Expense::where('reference', $item['data']['reference'])->exists()) {
                    DB::rollBack();
                    continue;
                }

                $expense = Expense::create($item['data']);

                foreach ($item['splits'] as $split) {
                    if ($split['member_id']) {
                        ExpenseSplit::create([
                            'expense_id' => $expense->id,
                            'member_id'  => $split['member_id'],
                            'amount'     => $split['amount'],
                        ]);
                        $affectedMemberIds->push($split['member_id']);
                    }
                }

                // جمع الأعضاء المتأثرين
                if ($expense->payer_id)           $affectedMemberIds->push($expense->payer_id);
                if ($expense->affected_member_id) $affectedMemberIds->push($expense->affected_member_id);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->command->error('❌ خطأ في ' . $item['data']['reference'] . ': ' . $e->getMessage());
            }
        }

        // إعادة حساب أرصدة جميع الأعضاء المتأثرين
        $uniqueIds = $affectedMemberIds->unique()->filter();
        foreach ($uniqueIds as $memberId) {
            $member = Member::find($memberId);
            if ($member) {
                $transactionService->recalculateBalance($member);
            }
        }

        $this->command->info('✅ تم إضافة ' . Expense::count() . ' مصروف تجريبي بنجاح');
    }
}
