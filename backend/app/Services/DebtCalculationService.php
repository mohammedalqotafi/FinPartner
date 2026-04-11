<?php

namespace App\Services;

use App\Models\ExpenseSplit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * DebtCalculationService
 * 
 * خدمة حساب الديون بين الأعضاء (Who Owes Who)
 * تحسب الديون بناءً على المصروفات المشتركة مع تطبيق Netting
 */
class DebtCalculationService
{
    /**
     * حساب الديون بين الأعضاء مع تطبيق Netting
     * 
     * المنطق:
     * 1. استخراج العلاقات: debtor = expense_splits.member_id, creditor = expenses.payer_id
     * 2. تجاهل السجلات حيث member_id = payer_id (العضو دفع لنفسه)
     * 3. تجميع النتائج حسب (debtor, creditor)
     * 4. تطبيق Netting لتبسيط الديون المتبادلة
     * 
     * @return Collection<array{debtor_id: int, creditor_id: int, amount: string}>
     */
    public function calculateDebts(): Collection
    {
        // الخطوة 1: استخراج الديون الأولية من قاعدة البيانات
        $rawDebts = $this->getRawDebts();
        
        // الخطوة 2: تطبيق Netting لتبسيط الديون المتبادلة
        $nettedDebts = $this->applyNetting($rawDebts);
        
        // الخطوة 3: تحويل النتائج للشكل المطلوب مع أسماء الأعضاء
        return $this->formatDebtsWithMemberNames($nettedDebts);
    }

    /**
     * استخراج الديون الأولية من قاعدة البيانات
     * 
     * @return Collection<array{debtor_id: int, creditor_id: int, amount: string}>
     */
    private function getRawDebts(): Collection
    {
        return DB::table('expense_splits')
            ->join('expenses', 'expense_splits.expense_id', '=', 'expenses.id')
            ->select([
                'expense_splits.member_id as debtor_id',
                'expenses.payer_id as creditor_id',
                DB::raw('SUM(expense_splits.amount) as total_amount')
            ])
            ->whereNotNull('expenses.payer_id')  // تجاهل المصروفات المدفوعة من الخزانة
            ->whereColumn('expense_splits.member_id', '!=', 'expenses.payer_id')  // تجاهل حالات الدفع للنفس
            ->groupBy('expense_splits.member_id', 'expenses.payer_id')
            ->get()
            ->map(function ($debt) {
                return [
                    'debtor_id' => (int) $debt->debtor_id,
                    'creditor_id' => (int) $debt->creditor_id,
                    'amount' => number_format((float) $debt->total_amount, 2, '.', '')
                ];
            });
    }

    /**
     * تطبيق Netting لتبسيط الديون المتبادلة
     * 
     * إذا كان A مدين لـ B بمبلغ X و B مدين لـ A بمبلغ Y:
     * - إذا كان X > Y: A مدين لـ B بمبلغ (X - Y)
     * - إذا كان Y > X: B مدين لـ A بمبلغ (Y - X)
     * - إذا كان X = Y: لا توجد ديون بينهما
     * 
     * @param Collection $rawDebts
     * @return Collection<array{debtor_id: int, creditor_id: int, amount: string}>
     */
    private function applyNetting(Collection $rawDebts): Collection
    {
        $debtMatrix = [];
        
        // بناء مصفوفة الديون
        foreach ($rawDebts as $debt) {
            $debtorId = $debt['debtor_id'];
            $creditorId = $debt['creditor_id'];
            $amount = (float) $debt['amount'];
            
            if (!isset($debtMatrix[$debtorId])) {
                $debtMatrix[$debtorId] = [];
            }
            
            $debtMatrix[$debtorId][$creditorId] = $amount;
        }
        
        $nettedDebts = collect();
        $processed = [];
        
        // تطبيق Netting
        foreach ($debtMatrix as $debtorId => $creditors) {
            foreach ($creditors as $creditorId => $amount) {
                $pairKey = $this->getPairKey($debtorId, $creditorId);
                
                if (in_array($pairKey, $processed)) {
                    continue; // تم معالجة هذا الزوج مسبقاً
                }
                
                $reverseAmount = $debtMatrix[$creditorId][$debtorId] ?? 0;
                $netAmount = $amount - $reverseAmount;
                
                if ($netAmount > 0.01) { // تجاهل المبالغ الصغيرة جداً
                    $nettedDebts->push([
                        'debtor_id' => $debtorId,
                        'creditor_id' => $creditorId,
                        'amount' => number_format($netAmount, 2, '.', '')
                    ]);
                } elseif ($netAmount < -0.01) {
                    $nettedDebts->push([
                        'debtor_id' => $creditorId,
                        'creditor_id' => $debtorId,
                        'amount' => number_format(abs($netAmount), 2, '.', '')
                    ]);
                }
                
                $processed[] = $pairKey;
            }
        }
        
        return $nettedDebts;
    }

    /**
     * إضافة أسماء الأعضاء للنتائج النهائية
     * 
     * @param Collection $nettedDebts
     * @return Collection<array{debtor_id: int, debtor_name: string, creditor_id: int, creditor_name: string, amount: string}>
     */
    private function formatDebtsWithMemberNames(Collection $nettedDebts): Collection
    {
        if ($nettedDebts->isEmpty()) {
            return collect();
        }
        
        // جمع جميع معرفات الأعضاء المطلوبة
        $memberIds = $nettedDebts->flatMap(function ($debt) {
            return [$debt['debtor_id'], $debt['creditor_id']];
        })->unique();
        
        // تحميل أسماء الأعضاء مرة واحدة
        $members = DB::table('members')
            ->whereIn('id', $memberIds)
            ->pluck('name', 'id');
        
        // إضافة أسماء الأعضاء للنتائج
        return $nettedDebts->map(function ($debt) use ($members) {
            return [
                'debtor_id' => $debt['debtor_id'],
                'debtor_name' => $members[$debt['debtor_id']] ?? 'Unknown',
                'creditor_id' => $debt['creditor_id'],
                'creditor_name' => $members[$debt['creditor_id']] ?? 'Unknown',
                'amount' => $debt['amount']
            ];
        });
    }

    /**
     * إنشاء مفتاح فريد لزوج من الأعضاء (بغض النظر عن الترتيب)
     * 
     * @param int $id1
     * @param int $id2
     * @return string
     */
    private function getPairKey(int $id1, int $id2): string
    {
        return $id1 < $id2 ? "{$id1}-{$id2}" : "{$id2}-{$id1}";
    }

    /**
     * حساب إجمالي الديون لعضو معين
     * 
     * @param int $memberId
     * @return array{total_owed: string, total_owing: string, net_position: string}
     */
    public function getMemberDebtSummary(int $memberId): array
    {
        $allDebts = $this->calculateDebts();
        
        $totalOwed = $allDebts
            ->where('creditor_id', $memberId)
            ->sum(fn($debt) => (float) $debt['amount']);
            
        $totalOwing = $allDebts
            ->where('debtor_id', $memberId)
            ->sum(fn($debt) => (float) $debt['amount']);
            
        $netPosition = $totalOwed - $totalOwing;
        
        return [
            'total_owed' => number_format($totalOwed, 2, '.', ''),      // المبلغ المستحق له
            'total_owing' => number_format($totalOwing, 2, '.', ''),    // المبلغ المستحق عليه
            'net_position' => number_format($netPosition, 2, '.', '')   // الموقف الصافي (موجب = دائن، سالب = مدين)
        ];
    }
}