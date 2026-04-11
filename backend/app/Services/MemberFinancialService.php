<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Transaction;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use Illuminate\Support\Facades\DB;

/**
 * MemberFinancialService
 * 
 * خدمة حساب التفاصيل المالية الكاملة للعضو
 * تحسب جميع الحركات المالية ديناميكياً بدون تخزين
 */
class MemberFinancialService
{
    /**
     * حساب التفاصيل المالية الكاملة للعضو
     * 
     * @param Member $member
     * @return array
     */
    public function calculateFinancials(Member $member): array
    {
        return [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'opening_balance' => (float) $member->opening_balance,
            ],
            'deposits' => $this->getDeposits($member),
            'withdrawals' => $this->getWithdrawals($member),
            'expenses' => $this->getExpenses($member),
            'balances' => $this->getBalancesWithMembers($member),
            'summary' => $this->getSummary($member),
        ];
    }

    /**
     * حساب الإيداعات
     * 
     * @param Member $member
     * @return array
     */
    private function getDeposits(Member $member): array
    {
        $deposits = Transaction::where('member_id', $member->id)
            ->whereIn('type', ['deposit', 'adjustment'])
            ->where('status', 'completed')
            ->orderBy('transaction_at', 'desc')
            ->get(['id', 'reference', 'type', 'amount', 'transaction_at', 'note']);

        return [
            'total' => (float) $deposits->sum('amount'),
            'count' => $deposits->count(),
            'items' => $deposits->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->transaction_at->toIso8601String(),
                    'note' => $transaction->note,
                ];
            })->values(),
        ];
    }

    /**
     * حساب السحوبات
     * 
     * @param Member $member
     * @return array
     */
    private function getWithdrawals(Member $member): array
    {
        $withdrawals = Transaction::where('member_id', $member->id)
            ->whereIn('type', ['withdraw', 'transfer'])
            ->where('status', 'completed')
            ->orderBy('transaction_at', 'desc')
            ->get(['id', 'reference', 'type', 'amount', 'transaction_at', 'note']);

        return [
            'total' => (float) $withdrawals->sum('amount'),
            'count' => $withdrawals->count(),
            'items' => $withdrawals->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->transaction_at->toIso8601String(),
                    'note' => $transaction->note,
                ];
            })->values(),
        ];
    }

    /**
     * حساب المصروفات (المدفوعة والمستحقة)
     * 
     * @param Member $member
     * @return array
     */
    private function getExpenses(Member $member): array
    {
        // المصروفات التي دفعها العضو
        $paidExpenses = $this->getPaidExpenses($member);
        
        // المصروفات التي شارك فيها (مدين)
        $owedExpenses = $this->getOwedExpenses($member);

        return [
            'paid' => $paidExpenses,
            'owed' => $owedExpenses,
            'summary' => [
                'you_paid_total' => $paidExpenses['total'],
                'your_share_from_paid' => $paidExpenses['your_share'],
                'you_paid_for_others' => $paidExpenses['paid_for_others'],
                'you_owe_total' => $owedExpenses['total'],
            ],
        ];
    }

    /**
     * المصروفات التي دفعها العضو
     * 
     * @param Member $member
     * @return array
     */
    private function getPaidExpenses(Member $member): array
    {
        $expenses = Expense::where('payer_id', $member->id)
            ->with(['splits.member:id,name', 'affectedMember:id,name'])
            ->orderBy('expense_datetime', 'desc')
            ->get();

        $items = $expenses->map(function ($expense) use ($member) {
            $totalAmount = (float) $expense->amount;
            
            // حساب نصيب العضو من المصروف
            $yourShare = 0;
            if ($expense->expense_type === 'shared') {
                $split = $expense->splits->firstWhere('member_id', $member->id);
                $yourShare = $split ? (float) $split->amount : 0;
            } elseif ($expense->expense_type === 'personal' && $expense->affected_member_id === $member->id) {
                $yourShare = $totalAmount;
            }

            $paidForOthers = $totalAmount - $yourShare;

            return [
                'id' => $expense->id,
                'reference' => $expense->reference,
                'type' => $expense->expense_type,
                'category' => $expense->category,
                'total_amount' => $totalAmount,
                'your_share' => $yourShare,
                'paid_for_others' => $paidForOthers,
                'date' => $expense->expense_datetime->toIso8601String(),
                'description' => $expense->description,
                'splits' => $expense->splits->map(function ($split) {
                    return [
                        'member_id' => $split->member_id,
                        'member_name' => $split->member->name,
                        'amount' => (float) $split->amount,
                    ];
                })->values(),
            ];
        });

        return [
            'total' => (float) $expenses->sum('amount'),
            'your_share' => (float) $items->sum('your_share'),
            'paid_for_others' => (float) $items->sum('paid_for_others'),
            'count' => $expenses->count(),
            'items' => $items->values(),
        ];
    }

    /**
     * المصروفات التي شارك فيها العضو (مدين)
     * 
     * @param Member $member
     * @return array
     */
    private function getOwedExpenses(Member $member): array
    {
        // المصروفات المشتركة التي شارك فيها
        $sharedSplits = ExpenseSplit::where('member_id', $member->id)
            ->with(['expense.payer:id,name'])
            ->whereHas('expense', function ($query) use ($member) {
                // استبعاد المصروفات التي دفعها العضو نفسه (تم حسابها في paid)
                $query->where('payer_id', '!=', $member->id)
                      ->orWhereNull('payer_id'); // مصروفات الخزانة
            })
            ->get();

        // المصروفات الشخصية المستحقة عليه
        $personalExpenses = Expense::where('expense_type', 'personal')
            ->where('affected_member_id', $member->id)
            ->where(function ($query) use ($member) {
                $query->where('payer_id', '!=', $member->id)
                      ->orWhereNull('payer_id');
            })
            ->with('payer:id,name')
            ->get();

        $items = collect();

        // إضافة المصروفات المشتركة
        foreach ($sharedSplits as $split) {
            $items->push([
                'id' => $split->expense->id,
                'reference' => $split->expense->reference,
                'type' => 'shared',
                'category' => $split->expense->category,
                'your_share' => (float) $split->amount,
                'paid_by' => $split->expense->payer ? $split->expense->payer->name : 'الخزانة',
                'paid_by_id' => $split->expense->payer_id,
                'date' => $split->expense->expense_datetime->toIso8601String(),
                'description' => $split->expense->description,
            ]);
        }

        // إضافة المصروفات الشخصية
        foreach ($personalExpenses as $expense) {
            $items->push([
                'id' => $expense->id,
                'reference' => $expense->reference,
                'type' => 'personal',
                'category' => $expense->category,
                'your_share' => (float) $expense->amount,
                'paid_by' => $expense->payer ? $expense->payer->name : 'الخزانة',
                'paid_by_id' => $expense->payer_id,
                'date' => $expense->expense_datetime->toIso8601String(),
                'description' => $expense->description,
            ]);
        }

        // ترتيب حسب التاريخ
        $items = $items->sortByDesc('date')->values();

        return [
            'total' => (float) $items->sum('your_share'),
            'count' => $items->count(),
            'items' => $items,
        ];
    }

    /**
     * حساب الملخص النهائي
     * 
     * @param Member $member
     * @return array
     */
    private function getSummary(Member $member): array
    {
        $deposits = Transaction::where('member_id', $member->id)
            ->whereIn('type', ['deposit', 'adjustment'])
            ->where('status', 'completed')
            ->sum('amount');

        $withdrawals = Transaction::where('member_id', $member->id)
            ->whereIn('type', ['withdraw', 'transfer'])
            ->where('status', 'completed')
            ->sum('amount');

        // المصروفات التي دفعها
        $paidExpenses = Expense::where('payer_id', $member->id)->sum('amount');

        // نصيبه من المصروفات التي دفعها
        $yourShareFromPaid = ExpenseSplit::where('member_id', $member->id)
            ->whereHas('expense', function ($query) use ($member) {
                $query->where('payer_id', $member->id);
            })
            ->sum('amount');
        
        // المصروفات الشخصية التي دفعها لنفسه
        $personalPaidForSelf = Expense::where('expense_type', 'personal')
            ->where('payer_id', $member->id)
            ->where('affected_member_id', $member->id)
            ->sum('amount');

        $yourShareFromPaid += (float) $personalPaidForSelf;

        // ما دفعه للآخرين
        $paidForOthers = (float) $paidExpenses - $yourShareFromPaid;

        // المصروفات المستحقة عليه
        $sharedOwed = ExpenseSplit::where('member_id', $member->id)
            ->whereHas('expense', function ($query) use ($member) {
                $query->where('payer_id', '!=', $member->id)
                      ->orWhereNull('payer_id');
            })
            ->sum('amount');

        $personalOwed = Expense::where('expense_type', 'personal')
            ->where('affected_member_id', $member->id)
            ->where(function ($query) use ($member) {
                $query->where('payer_id', '!=', $member->id)
                      ->orWhereNull('payer_id');
            })
            ->sum('amount');

        $youOwe = (float) $sharedOwed + (float) $personalOwed;

        // الرصيد الصافي
        $netBalance = (float) $member->opening_balance 
            + (float) $deposits 
            - (float) $withdrawals 
            + $paidForOthers 
            - $youOwe;

        return [
            'opening_balance' => (float) $member->opening_balance,
            'total_deposits' => (float) $deposits,
            'total_withdrawals' => (float) $withdrawals,
            'total_paid_for_others' => $paidForOthers,
            'total_you_owe' => $youOwe,
            'net_balance' => $netBalance,
            'calculated_balance' => (float) $member->calculated_balance, // للمقارنة
        ];
    }

    /**
     * حساب الأرصدة مع الأعضاء الآخرين (من يدين لمن)
     * 
     * @param Member $member
     * @return array
     */
    private function getBalancesWithMembers(Member $member): array
    {
        $balances = [];

        // جلب جميع الأعضاء الآخرين
        $otherMembers = Member::where('id', '!=', $member->id)
            ->whereNull('deleted_at')
            ->get(['id', 'name']);

        foreach ($otherMembers as $otherMember) {
            // حساب كم دفع العضو الحالي للعضو الآخر
            $youPaidForThem = $this->calculatePaidForMember($member, $otherMember);
            
            // حساب كم دفع العضو الآخر للعضو الحالي
            $theyPaidForYou = $this->calculatePaidForMember($otherMember, $member);
            
            // الرصيد الصافي
            $netBalance = $youPaidForThem - $theyPaidForYou;
            
            // إضافة فقط إذا كان هناك رصيد (موجب أو سالب)
            if (abs($netBalance) > 0.01) { // تجاهل الفروقات الصغيرة جداً
                $balances[] = [
                    'member_id' => $otherMember->id,
                    'member_name' => $otherMember->name,
                    'you_paid_for_them' => round($youPaidForThem, 2),
                    'they_paid_for_you' => round($theyPaidForYou, 2),
                    'net_balance' => round($netBalance, 2),
                    'status' => $netBalance > 0 ? 'they_owe_you' : 'you_owe_them',
                ];
            }
        }

        // ترتيب حسب القيمة المطلقة للرصيد (الأكبر أولاً)
        usort($balances, function ($a, $b) {
            return abs($b['net_balance']) <=> abs($a['net_balance']);
        });

        // حساب الإجماليات
        $totalOwedToYou = array_sum(array_map(function ($b) {
            return $b['net_balance'] > 0 ? $b['net_balance'] : 0;
        }, $balances));

        $totalYouOwe = array_sum(array_map(function ($b) {
            return $b['net_balance'] < 0 ? abs($b['net_balance']) : 0;
        }, $balances));

        return [
            'total_owed_to_you' => round($totalOwedToYou, 2),
            'total_you_owe' => round($totalYouOwe, 2),
            'net_balance' => round($totalOwedToYou - $totalYouOwe, 2),
            'count' => count($balances),
            'items' => $balances,
        ];
    }

    /**
     * حساب كم دفع عضو لصالح عضو آخر
     * 
     * @param Member $payer العضو الدافع
     * @param Member $beneficiary العضو المستفيد
     * @return float
     */
    private function calculatePaidForMember(Member $payer, Member $beneficiary): float
    {
        $total = 0;

        // 1. المصروفات المشتركة: العضو دفع ونصيب الآخر
        $sharedExpenses = Expense::where('payer_id', $payer->id)
            ->where('expense_type', 'shared')
            ->with('splits')
            ->get();

        foreach ($sharedExpenses as $expense) {
            $beneficiarySplit = $expense->splits->firstWhere('member_id', $beneficiary->id);
            if ($beneficiarySplit) {
                $total += (float) $beneficiarySplit->amount;
            }
        }

        // 2. المصروفات الشخصية: العضو دفع لصالح الآخر
        $personalExpenses = Expense::where('payer_id', $payer->id)
            ->where('expense_type', 'personal')
            ->where('affected_member_id', $beneficiary->id)
            ->sum('amount');

        $total += (float) $personalExpenses;

        return $total;
    }
}

