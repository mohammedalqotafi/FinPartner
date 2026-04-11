<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\SendMemberWhatsAppRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MemberDetailResource;
use App\Models\Member;
use App\Services\WhatsAppService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class MemberController extends Controller
{
    public function __construct(
        private TransactionService $service,
        private WhatsAppService $whatsAppService,
        private \App\Services\MemberFinancialService $financialService,
    ) {}

    /**
     * GET /api/members
     * قائمة الأعضاء مع إحصاءات الرصيد
     * Requirements: 15.1, 15.4 - تحسين eager loading و pagination
     */
    public function index(Request $request): JsonResponse
    {
        // تحسين eager loading - تحميل المعاملات مع تحديد الحقول المطلوبة فقط
        $query = Member::with([
                'transactions' => function ($query) {
                    $query->select('id', 'member_id', 'type', 'amount', 'status', 'transaction_at')
                          ->where('status', '!=', 'cancelled')  // استبعاد المعاملات الملغاة
                          ->orderBy('transaction_at', 'desc');
                }
            ])
            ->select([  // تحديد الحقول المطلوبة فقط
                'id', 'name', 'email', 'phone', 'opening_balance', 
                'balance', 'join_date', 'created_at'
            ])
            ->orderBy('name');

        // Requirements: 15.4 - إضافة pagination للقوائم الطويلة
        $perPage = $request->get('per_page', 20); // 20 عضو افتراضياً
        $perPage = min(max($perPage, 10), 50);    // بين 10 و 50 عضو كحد أقصى

        // إذا طلب المستخدم جميع الأعضاء
        if ($request->get('all') === 'true') {
            $members = $query->get();
            
            return response()->json([
                'data' => MemberResource::collection($members),
                'meta' => [
                    'total'           => $members->count(),
                    'total_balance'   => $members->sum('balance'),
                    'positive_count'  => $members->where('balance', '>', 0)->count(),
                    'negative_count'  => $members->where('balance', '<', 0)->count(),
                    'per_page'        => $members->count(),
                    'current_page'    => 1,
                    'last_page'       => 1,
                    'from'            => 1,
                    'to'              => $members->count()
                ],
            ]);
        }

        // استخدام pagination
        $members = $query->paginate($perPage);
        $allMembers = $members->getCollection();

        return response()->json([
            'data' => MemberResource::collection($members->items()),
            'meta' => [
                'total'           => $members->total(),
                'total_balance'   => $allMembers->sum('balance'),
                'positive_count'  => $allMembers->where('balance', '>', 0)->count(),
                'negative_count'  => $allMembers->where('balance', '<', 0)->count(),
                'per_page'        => $members->perPage(),
                'current_page'    => $members->currentPage(),
                'last_page'       => $members->lastPage(),
                'from'            => $members->firstItem(),
                'to'              => $members->lastItem(),
                'has_more_pages'  => $members->hasMorePages()
            ],
            'links' => [
                'first' => $members->url(1),
                'last' => $members->url($members->lastPage()),
                'prev' => $members->previousPageUrl(),
                'next' => $members->nextPageUrl()
            ]
        ]);
    }

    /**
     * POST /api/members
     * إنشاء عضو جديد
     */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $member = Member::create([
            'name'            => $validated['name'],
            'email'           => $validated['email'] ?? null,
            'phone'           => $validated['phone'] ?? null,
            'opening_balance' => $validated['opening_balance'] ?? 0,
            'balance'         => $validated['opening_balance'] ?? 0, // يبدأ بالرصيد الافتتاحي
            'join_date'       => $validated['join_date'] ?? now()->toDateString(),
        ]);

        if (!empty($member->phone)) {
            try {
                $message = implode("\n", [
                    "مرحباً {$member->name}",
                    'تمت إضافة حسابك بنجاح في FinPartner.',
                    $member->opening_balance > 0
                        ? 'رصيدك الافتتاحي: ' . number_format((float) $member->opening_balance, 2) . ' ر.س'
                        : 'لا يوجد رصيد افتتاحي حالياً.',
                ]);

                $this->whatsAppService->sendTextMessage($member->phone, $message);
            } catch (\Throwable $exception) {
                Log::warning('WhatsApp welcome message failed for new member.', [
                    'member_id' => $member->id,
                    'phone' => $member->phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'تم إنشاء العضو بنجاح',
            'data'    => new MemberResource($member),
        ], 201);
    }

    /**
     * GET /api/members/{member}
     * تفاصيل العضو مع دفتر الأستاذ الكامل
     * Requirements: 15.1 - تحسين eager loading
     */
    public function show(Request $request, Member $member): JsonResponse
    {
        // تحسين eager loading - تحميل المعاملات المعلقة مع العلاقات المطلوبة
        $member->load([
            'pendingTransactions' => function ($query) {
                $query->select('id', 'member_id', 'reference', 'type', 'amount', 'transaction_at', 'note', 'status')
                      ->orderBy('transaction_at', 'desc');
            },
            // تحميل المصروفات المرتبطة بالعضو لعرضها في صفحة التفاصيل
            'expenseSplits' => function ($query) {
                $query->select('id', 'expense_id', 'member_id', 'amount')
                      ->with([
                          'expense' => function ($expenseQuery) {
                              $expenseQuery->select('id', 'reference', 'expense_type', 'category', 'amount', 'expense_datetime', 'payer_id')
                                          ->with('payer:id,name');
                          }
                      ])
                      ->orderBy('id', 'desc')
                      ->limit(10);  // آخر 10 تقسيمات فقط لتحسين الأداء
            },
            'personalExpenses' => function ($query) {
                $query->select('id', 'reference', 'expense_type', 'category', 'amount', 'expense_datetime', 'payer_id', 'affected_member_id')
                      ->with('payer:id,name')
                      ->orderBy('expense_datetime', 'desc')
                      ->limit(10);  // آخر 10 مصروفات شخصية فقط
            },
            'paidExpenses' => function ($query) {
                $query->select('id', 'reference', 'expense_type', 'category', 'amount', 'expense_datetime', 'payer_id', 'affected_member_id')
                      ->orderBy('expense_datetime', 'desc')
                      ->limit(10);  // آخر 10 مصروفات دفعها العضو فقط
            }
        ]);

        $filters = $request->only(['from_date', 'to_date', 'type', 'status']);
        $ledger = $this->service->buildLedger($member, $filters);

        return response()->json([
            'data' => new MemberDetailResource($member, $ledger),
        ]);
    }

    /**
     * PUT /api/members/{member}
     * تعديل بيانات العضو (الاسم، الإيميل، الجوال فقط)
     */
    public function update(UpdateMemberRequest $request, Member $member): JsonResponse
    {
        $member->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات العضو',
            'data'    => new MemberResource($member->fresh()),
        ]);
    }

    /**
     * DELETE /api/members/{member}
     * حذف ناعم للعضو (Soft Delete) للحفاظ على السجل المحاسبي
     */
    public function destroy(Member $member): JsonResponse
    {
        $member->delete();

        return response()->json(['message' => 'تم حذف العضو']);
    }

    /**
     * POST /api/members/{member}/whatsapp
     * إرسال رسالة واتساب مخصصة للعضو
     */
    public function sendWhatsApp(SendMemberWhatsAppRequest $request, Member $member): JsonResponse
    {
        if (empty($member->phone)) {
            return response()->json([
                'message' => 'هذا العضو لا يملك رقم هاتف يمكن الإرسال إليه',
            ], 422);
        }

        try {
            $result = $this->whatsAppService->sendTextMessage(
                $member->phone,
                $request->validated()['message']
            );
        } catch (RequestException $exception) {
            $body = $exception->response?->json() ?? [];
            $errorMessage = (string) data_get($body, 'error.message', $exception->getMessage());
            $errorCode = (string) data_get($body, 'error.code', '');

            if ($errorCode === '131030' || str_contains($errorMessage, 'Recipient phone number not in allowed list')) {
                return response()->json([
                    'message' => 'هذا الرقم غير مضاف في قائمة أرقام الاختبار داخل Meta. أضف الرقم وفعّل OTP ثم أعد الإرسال.',
                ], 422);
            }

            return response()->json([
                'message' => 'تعذّر إرسال رسالة واتساب حالياً. تحقق من إعدادات Meta أو أعد المحاولة لاحقاً.',
            ], 422);
        }

        return response()->json([
            'message' => 'تم إرسال رسالة واتساب بنجاح',
            'data' => $result,
        ]);
    }

    /**
     * GET /api/members/{member}/financials
     * تفاصيل مالية كاملة للعضو (Financial Drill-down)
     * 
     * يعرض:
     * - جميع الإيداعات والسحوبات
     * - المصروفات التي دفعها (مع حساب ما دفعه للآخرين)
     * - المصروفات المستحقة عليه
     * - الملخص المالي الكامل
     * 
     * @param Member $member
     * @return JsonResponse
     */
    public function financials(Member $member): JsonResponse
    {
        try {
            $financials = $this->financialService->calculateFinancials($member);
            
            return response()->json($financials);
            
        } catch (\Exception $e) {
            Log::error('Failed to calculate member financials', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'حدث خطأ أثناء حساب التفاصيل المالية',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
