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
    ) {}

    /**
     * GET /api/members
     * قائمة الأعضاء مع إحصاءات الرصيد
     */
    public function index(): JsonResponse
    {
        $members = Member::with('transactions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => MemberResource::collection($members),
            'meta' => [
                'total'           => $members->count(),
                'total_balance'   => $members->sum('balance'),
                'positive_count'  => $members->where('balance', '>', 0)->count(),
                'negative_count'  => $members->where('balance', '<', 0)->count(),
            ],
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
     */
    public function show(Request $request, Member $member): JsonResponse
    {
        $member->load('pendingTransactions');

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
}
