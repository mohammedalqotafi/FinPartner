<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class FinPartnerSeeder extends Seeder
{
    public function run(): void
    {
        // ─── نفس البيانات التجريبية في React mock.ts ─────────────────────

        $members = [
            ['name' => 'أحمد محمود',  'email' => 'ahmed@example.com', 'phone' => '0501234567', 'opening_balance' => 0,   'join_date' => '2024-01-15'],
            ['name' => 'سارة خالد',   'email' => 'sara@example.com',  'phone' => '0557654321', 'opening_balance' => 0,   'join_date' => '2024-02-20'],
            ['name' => 'محمد علي',    'email' => 'moh@example.com',   'phone' => '0531112233', 'opening_balance' => 0,   'join_date' => '2024-03-05'],
            ['name' => 'فاطمة أحمد',  'email' => 'fat@example.com',   'phone' => '0543334455', 'opening_balance' => 500, 'join_date' => '2024-03-18'],
            ['name' => 'عمر حسن',     'email' => 'omar@example.com',  'phone' => '0569998877', 'opening_balance' => 0,   'join_date' => '2024-04-01'],
        ];

        $createdMembers = [];
        foreach ($members as $memberData) {
            $m = Member::create(array_merge($memberData, [
                'balance' => $memberData['opening_balance'],
            ]));
            $createdMembers[$m->name] = $m;
        }

        $transactions = [
            // أحمد محمود
            ['ref' => 'TX-0001', 'member' => 'أحمد محمود', 'type' => 'deposit',    'amount' => 2000, 'datetime' => '2024-11-01 10:30:00', 'note' => 'إيداع شهري',        'status' => 'completed'],
            ['ref' => 'TX-0004', 'member' => 'أحمد محمود', 'type' => 'deposit',    'amount' => 1000, 'datetime' => '2024-11-05 14:00:00', 'note' => 'إيداع إضافي',       'status' => 'completed'],
            ['ref' => 'TX-0009', 'member' => 'أحمد محمود', 'type' => 'withdraw',   'amount' => 500,  'datetime' => '2024-11-14 09:15:00', 'note' => 'سحب قرض',          'status' => 'completed'],
            ['ref' => 'TX-0014', 'member' => 'أحمد محمود', 'type' => 'deposit',    'amount' => 1500, 'datetime' => '2024-11-25 16:45:00', 'note' => 'إيداع نهاية الشهر','status' => 'completed'],
            ['ref' => 'TX-0016', 'member' => 'أحمد محمود', 'type' => 'transfer',   'amount' => 200,  'datetime' => '2024-11-28 11:20:00', 'note' => 'تحويل لسارة',       'status' => 'pending'],
            ['ref' => 'TX-0018', 'member' => 'أحمد محمود', 'type' => 'adjustment', 'amount' => 500,  'datetime' => '2024-11-30 08:00:00', 'note' => 'تسوية فروق حسابية','status' => 'completed'],
            // سارة خالد
            ['ref' => 'TX-0002', 'member' => 'سارة خالد',  'type' => 'deposit',    'amount' => 1500, 'datetime' => '2024-11-02 09:00:00', 'note' => 'مساهمة صندوق',     'status' => 'completed'],
            ['ref' => 'TX-0007', 'member' => 'سارة خالد',  'type' => 'withdraw',   'amount' => 300,  'datetime' => '2024-11-10 13:30:00', 'note' => 'سحب شخصي',         'status' => 'completed'],
            ['ref' => 'TX-0011', 'member' => 'سارة خالد',  'type' => 'deposit',    'amount' => 500,  'datetime' => '2024-11-18 10:00:00', 'note' => 'دفعة إضافية',       'status' => 'completed'],
            // محمد علي
            ['ref' => 'TX-0003', 'member' => 'محمد علي',   'type' => 'withdraw',   'amount' => 500,  'datetime' => '2024-11-03 14:20:00', 'note' => 'سحب طارئ',         'status' => 'completed'],
            ['ref' => 'TX-0008', 'member' => 'محمد علي',   'type' => 'deposit',    'amount' => 1300, 'datetime' => '2024-11-12 15:00:00', 'note' => 'إيداع شهري',       'status' => 'completed'],
            // فاطمة أحمد
            ['ref' => 'TX-0005', 'member' => 'فاطمة أحمد', 'type' => 'deposit',    'amount' => 3000, 'datetime' => '2024-11-07 11:00:00', 'note' => 'مساهمة ربع سنوية','status' => 'completed'],
            ['ref' => 'TX-0010', 'member' => 'فاطمة أحمد', 'type' => 'withdraw',   'amount' => 700,  'datetime' => '2024-11-15 14:30:00', 'note' => 'مصاريف طارئة',     'status' => 'pending'],
            ['ref' => 'TX-0015', 'member' => 'فاطمة أحمد', 'type' => 'deposit',    'amount' => 800,  'datetime' => '2024-11-28 16:15:00', 'note' => 'مساهمة إضافية',    'status' => 'completed'],
            // عمر حسن
            ['ref' => 'TX-0006', 'member' => 'عمر حسن',    'type' => 'deposit',    'amount' => 800,  'datetime' => '2024-11-08 10:35:00', 'note' => 'إيداع شهري',       'status' => 'completed'],
            ['ref' => 'TX-0012', 'member' => 'عمر حسن',    'type' => 'withdraw',   'amount' => 150,  'datetime' => '2024-11-20 12:00:00', 'note' => 'سحب جزئي',         'status' => 'completed'],
        ];

        // حساب الرصيد المتراكم وإدخال العمليات
        $memberBalances = [];
        foreach ($createdMembers as $name => $m) {
            $memberBalances[$name] = (float) $m->opening_balance;
        }

        foreach ($transactions as $txData) {
            $member = $createdMembers[$txData['member']];
            $isCredit = in_array($txData['type'], ['deposit', 'adjustment']);
            $delta = $isCredit ? $txData['amount'] : -$txData['amount'];

            $balanceAfter = null;
            if ($txData['status'] === 'completed') {
                $memberBalances[$txData['member']] += $delta;
                $balanceAfter = $memberBalances[$txData['member']];
            }

            Transaction::create([
                'reference'      => $txData['ref'],
                'member_id'      => $member->id,
                'type'           => $txData['type'],
                'amount'         => $txData['amount'],
                'transaction_at' => $txData['datetime'],
                'note'           => $txData['note'],
                'status'         => $txData['status'],
                'balance_after'  => $balanceAfter,
            ]);
        }

        // تحديث الأرصدة النهائية للأعضاء
        foreach ($createdMembers as $name => $member) {
            $member->update(['balance' => $memberBalances[$name]]);
        }

        $this->command->info('✅ تم تهيئة بيانات FinPartner بنجاح');
    }
}
