<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MemberDetailResource;
use App\Models\Member;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private TransactionService $service) {}

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
}
