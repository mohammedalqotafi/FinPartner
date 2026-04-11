<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DebtCalculationService;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DebtController
 * 
 * تحكم في عرض الديون بين الأعضاء (Who Owes Who)
 */
class DebtController extends Controller
{
    public function __construct(
        private readonly DebtCalculationService $debtService
    ) {}

    /**
     * GET /api/debts
     * عرض جميع الديون بين الأعضاء مع تطبيق Netting
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $debts = $this->debtService->calculateDebts();
            
            // حساب الإحصائيات العامة
            $totalDebts = $debts->sum(fn($debt) => (float) $debt['amount']);
            $activeDebtors = $debts->pluck('debtor_id')->unique()->count();
            $activeCreditors = $debts->pluck('creditor_id')->unique()->count();
            
            return response()->json([
                'data' => $debts->values(),
                'meta' => [
                    'total_debts' => number_format($totalDebts, 2, '.', ''),
                    'active_debtors' => $activeDebtors,
                    'active_creditors' => $activeCreditors,
                    'debt_relationships' => $debts->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء حساب الديون',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /api/debts/member/{member}
     * عرض ملخص الديون لعضو معين
     * 
     * @param Member $member
     * @return JsonResponse
     */
    public function memberSummary(Member $member): JsonResponse
    {
        try {
            $summary = $this->debtService->getMemberDebtSummary($member->id);
            $allDebts = $this->debtService->calculateDebts();
            
            // الديون المستحقة للعضو (هو الدائن)
            $debtsOwedToMember = $allDebts
                ->where('creditor_id', $member->id)
                ->values();
                
            // الديون المستحقة على العضو (هو المدين)
            $debtsOwedByMember = $allDebts
                ->where('debtor_id', $member->id)
                ->values();
            
            return response()->json([
                'member' => [
                    'id' => $member->id,
                    'name' => $member->name
                ],
                'summary' => $summary,
                'debts_owed_to_member' => $debtsOwedToMember,
                'debts_owed_by_member' => $debtsOwedByMember
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء حساب ديون العضو',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /api/debts/simplified
     * عرض الديون بشكل مبسط (بدون أسماء الأعضاء)
     * للاستخدام في التطبيقات الخارجية أو التقارير
     * 
     * @return JsonResponse
     */
    public function simplified(): JsonResponse
    {
        try {
            $debts = $this->debtService->calculateDebts();
            
            // تحويل للشكل المبسط المطلوب
            $simplifiedDebts = $debts->map(function ($debt) {
                return [
                    'debtor' => $debt['debtor_id'],
                    'creditor' => $debt['creditor_id'],
                    'amount' => (float) $debt['amount']
                ];
            });
            
            return response()->json($simplifiedDebts->values());
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء حساب الديون',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /api/debts/matrix
     * عرض مصفوفة الديون بين جميع الأعضاء
     * مفيد لعرض جدول شامل للديون
     * 
     * @return JsonResponse
     */
    public function matrix(): JsonResponse
    {
        try {
            $debts = $this->debtService->calculateDebts();
            
            if ($debts->isEmpty()) {
                return response()->json([
                    'matrix' => [],
                    'members' => []
                ]);
            }
            
            // جمع جميع الأعضاء المشاركين في الديون
            $memberIds = $debts->flatMap(function ($debt) {
                return [$debt['debtor_id'], $debt['creditor_id']];
            })->unique()->sort()->values();
            
            // تحميل أسماء الأعضاء
            $members = Member::whereIn('id', $memberIds)
                ->get(['id', 'name'])
                ->keyBy('id');
            
            // بناء المصفوفة
            $matrix = [];
            foreach ($memberIds as $debtorId) {
                $row = [];
                foreach ($memberIds as $creditorId) {
                    if ($debtorId === $creditorId) {
                        $row[] = null; // العضو لا يدين لنفسه
                    } else {
                        $debt = $debts->first(function ($debt) use ($debtorId, $creditorId) {
                            return $debt['debtor_id'] === $debtorId && $debt['creditor_id'] === $creditorId;
                        });
                        $row[] = $debt ? (float) $debt['amount'] : 0;
                    }
                }
                $matrix[] = $row;
            }
            
            return response()->json([
                'matrix' => $matrix,
                'members' => $members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name
                    ];
                })->values()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء إنشاء مصفوفة الديون',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}