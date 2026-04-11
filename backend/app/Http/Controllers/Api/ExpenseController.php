<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use App\Services\PennyRoutingService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly PennyRoutingService $pennyRouting,
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * Display a listing of the expenses.
     * Requirements: 11.2, 15.2, 15.1, 15.4
     */
    public function index(Request $request): JsonResponse
    {
        // تحسين eager loading لتجنب N+1 queries
        // تحميل جميع العلاقات المطلوبة في استعلام واحد
        $query = Expense::with([
                'payer:id,name',                    // تحميل الدافع مع الحقول المطلوبة فقط
                'affectedMember:id,name',           // تحميل العضو المتأثر مع الحقول المطلوبة فقط
                'splits' => function ($query) {    // تحميل التقسيمات مع تحسين
                    $query->select('id', 'expense_id', 'member_id', 'amount')
                          ->orderBy('member_id');   // ترتيب التقسيمات حسب العضو
                },
                'splits.member:id,name'             // تحميل أعضاء التقسيمات مع الحقول المطلوبة فقط
            ])
            ->select([                              // تحديد الحقول المطلوبة فقط لتحسين الأداء
                'id', 'reference', 'expense_type', 'affected_member_id',
                'category', 'amount', 'payer_id', 'payment_method',
                'description', 'expense_datetime', 'created_at'
            ])
            ->orderBy('expense_datetime', 'desc');

        // Requirements: 15.4 - إضافة pagination للقوائم الطويلة
        $perPage = $request->get('per_page', 25); // 25 سجل افتراضياً
        $perPage = min(max($perPage, 10), 100);   // بين 10 و 100 سجل كحد أقصى

        // إذا طلب المستخدم جميع السجلات (للتصدير مثلاً)
        if ($request->get('all') === 'true') {
            $expenses = $query->get();
            return response()->json([
                'data' => $expenses,
                'meta' => [
                    'total' => $expenses->count(),
                    'per_page' => $expenses->count(),
                    'current_page' => 1,
                    'last_page' => 1,
                    'from' => 1,
                    'to' => $expenses->count()
                ]
            ]);
        }

        // استخدام pagination
        $expenses = $query->paginate($perPage);

        return response()->json([
            'data' => $expenses->items(),
            'meta' => [
                'total' => $expenses->total(),
                'per_page' => $expenses->perPage(),
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'from' => $expenses->firstItem(),
                'to' => $expenses->lastItem(),
                'has_more_pages' => $expenses->hasMorePages()
            ],
            'links' => [
                'first' => $expenses->url(1),
                'last' => $expenses->url($expenses->lastPage()),
                'prev' => $expenses->previousPageUrl(),
                'next' => $expenses->nextPageUrl()
            ]
        ]);
    }

    /**
     * Store a newly created expense in storage.
     * Requirements: 11.1, 9.1, 2.1, 2.2, 2.4, 2.5, 2.7, 1.3, 1.4, 4.2, 17.2, 9.2, 18.1, 18.2
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Requirements: 9.1 - بدء database transaction
            DB::beginTransaction();

            // Requirements: 11.1, 9.1 - إنشاء Expense record
            $expense = Expense::create([
                'reference'          => Expense::generateReference(),
                'expense_type'       => $validated['expense_type'],
                // Requirements: 1.3 - affected_member_id للمصروفات الشخصية فقط
                'affected_member_id' => $validated['expense_type'] === 'personal'
                    ? ($validated['affected_member_id'] ?? null)
                    : null,
                'category'           => $validated['category'],
                'amount'             => $validated['amount'],
                'payer_id'           => $validated['payer_id'] ?? null,
                'payment_method'     => $validated['payment_method'],
                'description'        => $validated['description'] ?? null,
                'expense_datetime'   => $validated['expense_datetime'],
            ]);

            // Requirements: 2.1, 2.2, 2.4, 2.5, 2.7 - معالجة Shared Expenses
            if ($validated['expense_type'] === 'shared') {
                $this->handleSharedExpense($expense, $validated);
            }

            // Requirements: 9.1 - commit بعد نجاح جميع العمليات
            DB::commit();

            // Requirements: 4.2, 17.2 - إعادة حساب أرصدة الأعضاء المتأثرين
            $this->recalculateAffectedBalances($expense, $validated);

            return response()->json(
                $expense->load([
                    'payer:id,name',
                    'affectedMember:id,name',
                    'splits' => function ($query) {
                        $query->select('id', 'expense_id', 'member_id', 'amount')
                              ->orderBy('member_id');
                    },
                    'splits.member:id,name'
                ]),
                201
            );

        } catch (\Exception $e) {
            // Requirements: 9.2, 18.1 - rollback عند الفشل
            DB::rollBack();

            Log::error('Failed to create expense', [
                'error'   => $e->getMessage(),
                'data'    => $validated,
            ]);

            // Requirements: 18.2 - رسائل خطأ واضحة بدون تفاصيل تقنية حساسة
            $userMessage = $e instanceof \InvalidArgumentException
                ? $e->getMessage()
                : 'حدث خطأ أثناء إنشاء المصروف. يرجى المحاولة مرة أخرى.';

            return response()->json(['message' => $userMessage], 422);
        }
    }

    /**
     * Display the specified expense with smart analysis for current user.
     * Requirements: 11.3, 11.7, 15.1
     * 
     * @param Expense $expense
     * @param Request $request - يحتوي على current_user_id للتحليل الذكي
     */
    public function show(Expense $expense, Request $request): JsonResponse
    {
        // تحسين eager loading لعرض تفاصيل المصروف
        // تحميل جميع العلاقات المطلوبة مع تحسين الحقول
        $expense->load([
            'payer:id,name,email,phone',            // تحميل بيانات الدافع الكاملة
            'affectedMember:id,name,email,phone',   // تحميل بيانات العضو المتأثر الكاملة
            'splits' => function ($query) {         // تحميل التقسيمات مع ترتيب
                $query->select('id', 'expense_id', 'member_id', 'amount')
                      ->orderBy('amount', 'desc');  // ترتيب حسب المبلغ (الأكبر أولاً)
            },
            'splits.member:id,name,email'           // تحميل بيانات أعضاء التقسيمات
        ]);

        // التحليل الذكي للمستخدم الحالي (إذا تم تمرير current_user_id)
        $currentUserId = $request->query('current_user_id');
        
        // إذا تم طلب التحليل الذكي، نرجع الشكل الجديد
        if ($currentUserId && $expense->expense_type === 'shared') {
            $response = [
                'expense' => [
                    'id' => $expense->id,
                    'reference' => $expense->reference,
                    'expense_type' => $expense->expense_type,
                    'category' => $expense->category,
                    'total_amount' => (float) $expense->amount,
                    'payment_method' => $expense->payment_method,
                    'description' => $expense->description,
                    'expense_datetime' => $expense->expense_datetime->toIso8601String(),
                    'created_at' => $expense->created_at->toIso8601String(),
                    'payer' => $expense->payer ? [
                        'id' => $expense->payer->id,
                        'name' => $expense->payer->name,
                        'email' => $expense->payer->email,
                        'phone' => $expense->payer->phone,
                    ] : null,
                    'affected_member' => $expense->affectedMember ? [
                        'id' => $expense->affectedMember->id,
                        'name' => $expense->affectedMember->name,
                        'email' => $expense->affectedMember->email,
                        'phone' => $expense->affectedMember->phone,
                    ] : null,
                ],
                'splits' => $expense->splits->map(function ($split) {
                    return [
                        'id' => $split->id,
                        'member' => [
                            'id' => $split->member->id,
                            'name' => $split->member->name,
                            'email' => $split->member->email,
                        ],
                        'amount' => (float) $split->amount,
                    ];
                })->values(),
                'analysis' => $this->analyzeExpenseForUser($expense, (int) $currentUserId),
            ];
            
            return response()->json($response);
        }
        
        // الشكل القديم للتوافق مع الاختبارات الموجودة
        return response()->json($expense);
    }

    /**
     * تحليل ذكي للمصروف التشاركي بالنسبة للمستخدم الحالي
     * 
     * @param Expense $expense
     * @param int $currentUserId
     * @return array
     */
    private function analyzeExpenseForUser(Expense $expense, int $currentUserId): array
    {
        $totalAmount = (float) $expense->amount;
        $isPayer = $expense->payer_id === $currentUserId;
        
        // البحث عن نصيب المستخدم من التقسيمات
        $userSplit = $expense->splits->firstWhere('member_id', $currentUserId);
        $userShare = $userSplit ? (float) $userSplit->amount : 0;

        if ($isPayer) {
            // المستخدم هو الدافع
            return [
                'is_payer' => true,
                'your_share' => $userShare,
                'you_paid' => $totalAmount,
                'others_owe_you' => $totalAmount - $userShare,
                'net_position' => $totalAmount - $userShare, // موجب = لك على الآخرين
            ];
        } else {
            // المستخدم مشارك فقط
            return [
                'is_payer' => false,
                'you_owe' => $userShare,
                'paid_to' => $expense->payer ? $expense->payer->name : 'الخزانة',
                'paid_to_id' => $expense->payer_id,
                'net_position' => -$userShare, // سالب = عليك للآخرين
            ];
        }
    }

    /**
     * Remove the specified expense from storage.
     * Requirements: 12.1, 12.2, 12.4
     */
    public function destroy(Expense $expense): JsonResponse
    {
        try {
            DB::beginTransaction();

            // جمع الأعضاء المتأثرين قبل الحذف مع تحسين الاستعلام
            $affectedMemberIds = collect();
            
            // تحميل التقسيمات مع تحديد الحقول المطلوبة فقط
            if ($expense->relationLoaded('splits')) {
                $affectedMemberIds = $affectedMemberIds->merge($expense->splits->pluck('member_id'));
            } else {
                $affectedMemberIds = $affectedMemberIds->merge(
                    $expense->splits()->pluck('member_id')
                );
            }
            
            if ($expense->payer_id) {
                $affectedMemberIds->push($expense->payer_id);
            }
            if ($expense->affected_member_id) {
                $affectedMemberIds->push($expense->affected_member_id);
            }
            $affectedMemberIds = $affectedMemberIds->filter()->unique();

            // Requirements: 12.1 - cascade delete للـ splits تلقائياً
            $expense->delete();

            DB::commit();

            Log::info('Expense deleted', ['expense_id' => $expense->id]);

            // Requirements: 12.2 - إعادة حساب أرصدة الأعضاء المتأثرين مع تحسين eager loading
            Member::whereIn('id', $affectedMemberIds)
                ->with([
                    'expenseSplits:id,member_id,amount',
                    'personalExpenses:id,affected_member_id,amount',
                    'paidExpenses:id,payer_id,amount',
                    'completedTransactions:id,member_id,type,amount'
                ])
                ->get()
                ->each(fn(Member $member) => $this->transactionService->recalculateBalance($member));

            return response()->json(['message' => 'تم حذف المصروف بنجاح']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete expense', ['error' => $e->getMessage(), 'expense_id' => $expense->id]);
            return response()->json(['message' => 'فشل حذف المصروف'], 500);
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * معالجة تقسيمات المصروف المشترك
     * Requirements: 2.1, 2.2, 2.4, 2.5, 2.7
     */
    private function handleSharedExpense(Expense $expense, array $validated): void
    {
        $totalAmount = (float) $validated['amount'];
        $members     = $validated['members'];
        $memberIds   = array_column($members, 'id');

        if ($validated['split_type'] === 'equal') {
            // Requirements: 2.1, 2.2, 2.3 - استخدام PennyRoutingService للتقسيم المتساوي
            $splits = $this->pennyRouting->calculateEqualSplits(
                $totalAmount,
                $memberIds,
                $expense->reference
            );

            foreach ($splits as $memberId => $amount) {
                ExpenseSplit::create([
                    'expense_id' => $expense->id,
                    'member_id'  => $memberId,
                    'amount'     => $amount,
                ]);
            }

        } else {
            // Requirements: 2.4, 2.5 - التقسيم اليدوي (التحقق من المجموع تم في StoreExpenseRequest)
            foreach ($members as $memberData) {
                ExpenseSplit::create([
                    'expense_id' => $expense->id,
                    'member_id'  => $memberData['id'],
                    'amount'     => $memberData['amount'],
                ]);
            }
        }
    }

    /**
     * إعادة حساب أرصدة جميع الأعضاء المتأثرين بالمصروف
     * Requirements: 4.2, 17.2, 15.1 - تحسين eager loading
     */
    private function recalculateAffectedBalances(Expense $expense, array $validated): void
    {
        $affectedMemberIds = collect($validated['members'] ?? [])->pluck('id');

        if ($expense->payer_id) {
            $affectedMemberIds->push($expense->payer_id);
        }
        if ($expense->affected_member_id) {
            $affectedMemberIds->push($expense->affected_member_id);
        }

        // تحسين: تحميل الأعضاء مع العلاقات المطلوبة لحساب الرصيد مرة واحدة
        Member::whereIn('id', $affectedMemberIds->filter()->unique())
            ->with([
                'expenseSplits:id,member_id,amount',
                'personalExpenses:id,affected_member_id,amount',
                'paidExpenses:id,payer_id,amount',
                'completedTransactions:id,member_id,type,amount'
            ])
            ->get()
            ->each(fn(Member $member) => $this->transactionService->recalculateBalance($member));
    }
}
