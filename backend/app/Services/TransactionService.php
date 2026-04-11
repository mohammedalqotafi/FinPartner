<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * TransactionService
 *
 * يمثل طبقة الأعمال (Business Layer) للعمليات المالية.
 * جميع العمليات التي تؤثر على الرصيد تمر عبر هذه الخدمة
 * لضمان الاتساق والدقة المحاسبية.
 */
class TransactionService
{
    /**
     * إنشاء عملية جديدة وتحديث رصيد العضو
     *
     * @param Member $member
     * @param array  $data   ['type', 'amount', 'note', 'status', 'transaction_at']
     * @return Transaction
     */
    public function create(Member $member, array $data): Transaction
    {
        return DB::transaction(function () use ($member, $data) {

            $status = $data['status'] ?? 'completed';

            // توليد reference فريد
            $reference = Transaction::generateReference();

            // حساب balance_after فقط إذا كانت العملية مكتملة
            $balanceAfter = null;
            if ($status === 'completed') {
                $delta = $this->calcDelta($data['type'], $data['amount']);
                $balanceAfter = $member->balance + $delta;
            }

            $transaction = Transaction::create([
                'reference'      => $reference,
                'member_id'      => $member->id,
                'type'           => $data['type'],
                'amount'         => $data['amount'],
                'transaction_at' => $data['transaction_at'] ?? now(),
                'note'           => $data['note'] ?? null,
                'status'         => $status,
                'balance_after'  => $balanceAfter,
            ]);

            // تحديث رصيد العضو إذا كانت العملية مكتملة
            if ($status === 'completed') {
                $member->increment('balance', $this->calcDelta($data['type'], $data['amount']));
            }

            return $transaction->fresh();
        });
    }

    /**
     * تحديث حالة عملية معلقة
     *
     * - pending → completed: يُطبَّق التأثير على الرصيد الآن
     * - completed → cancelled: يُعاد التأثير العكسي على الرصيد
     */
    public function updateStatus(Transaction $transaction, string $newStatus): Transaction
    {
        return DB::transaction(function () use ($transaction, $newStatus) {

            $member = $transaction->member;
            $oldStatus = $transaction->status;

            // اعتماد عملية معلقة → تطبيق التأثير
            if ($oldStatus === 'pending' && $newStatus === 'completed') {
                $delta = $this->calcDelta($transaction->type, $transaction->amount);
                $member->increment('balance', $delta);
                $transaction->balance_after = $member->fresh()->balance;
            }

            // إلغاء عملية مكتملة → عكس التأثير
            if ($oldStatus === 'completed' && $newStatus === 'cancelled') {
                $delta = $this->calcDelta($transaction->type, $transaction->amount);
                $member->decrement('balance', $delta); // نعكس التأثير
            }

            $transaction->update(['status' => $newStatus]);

            return $transaction->fresh();
        });
    }

    /**
     * حساب التأثير على الرصيد
     * deposit/adjustment → موجب (يزيد الرصيد)
     * withdraw/transfer  → سالب (ينقص الرصيد)
     */
    public function calcDelta(string $type, float $amount): float
    {
        return in_array($type, ['deposit', 'adjustment']) ? $amount : -$amount;
    }

    /**
     * إعادة حساب رصيد العضو من الصفر (للتدقيق والتحقق)
     * يُستعمَل لإصلاح أي تعارض محتمل في البيانات
     * تم تحديثه ليشمل حسابات المصروفات بناءً على Unified Ledger
     */
    public function recalculateBalance(Member $member): void
    {
        $newBalance = $member->calculated_balance;
        $member->update(['balance' => $newBalance]);
    }

    /**
     * بناء سجل دفتر الأستاذ مع الرصيد التراكمي لكل سطر
     * Requirements: 15.1 - تحسين eager loading
     *
     * @param Member $member
     * @param array  $filters ['from_date', 'to_date', 'type', 'status']
     * @return array ['opening', 'rows', 'totals']
     */
    public function buildLedger(Member $member, array $filters = []): array
    {
        $query = $member->transactions()
            ->select([  // تحديد الحقول المطلوبة فقط لتحسين الأداء
                'id', 'reference', 'member_id', 'type', 'amount', 
                'transaction_at', 'note', 'status', 'balance_after'
            ])
            ->orderBy('transaction_at')
            ->orderBy('id');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('transaction_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('transaction_at', '<=', $filters['to_date'] . ' 23:59:59');
        }

        // تحسين: تحميل المعاملات مرة واحدة بدلاً من استعلامات متعددة
        $transactions = $query->where('status', '!=', 'cancelled')->get();

        $running = (float) $member->opening_balance;
        $totalDeposits = 0;
        $totalWithdrawals = 0;
        $rows = [];

        foreach ($transactions as $tx) {
            $isCredit = in_array($tx->type, ['deposit', 'adjustment']);
            $credit = $isCredit ? (float) $tx->amount : 0;
            $debit  = !$isCredit ? (float) $tx->amount : 0;

            if ($tx->status === 'completed') {
                $running += $credit - $debit;
                $totalDeposits += $credit;
                $totalWithdrawals += $debit;
            }

            $rows[] = [
                'tx'      => $tx,
                'credit'  => $credit,
                'debit'   => $debit,
                'running' => $running,
            ];
        }

        return [
            'opening_balance'   => (float) $member->opening_balance,
            'rows'              => $rows,
            'total_deposits'    => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'net_change'        => $totalDeposits - $totalWithdrawals,
            'final_balance'     => $running,
        ];
    }
}
