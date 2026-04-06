<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    /**
     * GET /api/transactions
     * كل العمليات مع فلاتر متقدمة
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with('member')
            ->orderByDesc('transaction_at')
            ->orderByDesc('id');

        // ─── Filters ────────────────────────────────────────────────────
        if ($request->filled('member_id')) {
            $query->where('member_id', $request->member_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from_date')) {
            $query->where('transaction_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('transaction_at', '<=', $request->to_date . ' 23:59:59');
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhereHas('member', fn($m) => $m->where('name', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->paginate($request->per_page ?? 50);

        // ─── Aggregates ──────────────────────────────────────────────────
        $allFiltered = Transaction::with('member')
            ->when($request->member_id, fn($q) => $q->where('member_id', $request->member_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->where('status', 'completed');

        return response()->json([
            'data' => TransactionResource::collection($transactions->items()),
            'meta' => [
                'total'        => $transactions->total(),
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total_deposits'    => (float) (clone $allFiltered)->whereIn('type', ['deposit', 'adjustment'])->sum('amount'),
                'total_withdrawals' => (float) (clone $allFiltered)->whereIn('type', ['withdraw', 'transfer'])->sum('amount'),
                'pending_count'     => Transaction::where('status', 'pending')->count(),
            ],
        ]);
    }

    /**
     * POST /api/transactions
     * إنشاء عملية جديدة
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $member = Member::findOrFail($request->member_id);

        $transaction = $this->service->create($member, $request->validated());
        $transaction->load('member');

        return response()->json([
            'message' => 'تم تسجيل العملية بنجاح',
            'data'    => new TransactionResource($transaction),
        ], 201);
    }

    /**
     * GET /api/transactions/{reference}
     * تفاصيل عملية واحدة (البحث بالـ reference مثل TX-0001)
     */
    public function show(string $reference): JsonResponse
    {
        $transaction = Transaction::with('member')
            ->where('reference', $reference)
            ->firstOrFail();

        return response()->json([
            'data' => new TransactionResource($transaction),
        ]);
    }

    /**
     * PATCH /api/transactions/{reference}/status
     * تحديث حالة العملية (اعتماد المعلق / إلغاء)
     */
    public function updateStatus(Request $request, string $reference): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:completed,cancelled'],
        ]);

        $transaction = Transaction::with('member')
            ->where('reference', $reference)
            ->firstOrFail();

        // منع تغيير الحالة للعمليات الملغاة
        if ($transaction->status === 'cancelled') {
            return response()->json(['message' => 'لا يمكن تعديل عملية ملغاة'], 422);
        }

        $updated = $this->service->updateStatus($transaction, $request->status);

        return response()->json([
            'message' => $request->status === 'completed' ? 'تم اعتماد العملية' : 'تم إلغاء العملية',
            'data'    => new TransactionResource($updated->load('member')),
        ]);
    }

    /**
     * GET /api/members/{member}/transactions
     * عمليات عضو محدد (للاستخدام من Member Detail Page)
     */
    public function memberTransactions(Request $request, Member $member): JsonResponse
    {
        $transactions = $member->transactions()
            ->with('member')
            ->orderBy('transaction_at')
            ->get();

        return response()->json([
            'data' => TransactionResource::collection($transactions),
        ]);
    }
}
