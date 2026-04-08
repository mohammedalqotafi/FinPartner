<?php

use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ExpenseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FinPartner API Routes
|--------------------------------------------------------------------------
|
| All routes return JSON. React frontend consumes these endpoints.
| Base URL: /api/...
|
*/

// ─── Members ──────────────────────────────────────────────────────────────────
Route::apiResource('members', MemberController::class);


// إرسال رسالة واتساب مخصصة لعضو
Route::post('members/{member}/whatsapp', [MemberController::class, 'sendWhatsApp'])
    ->name('members.whatsapp');
// ─── Expenses ─────────────────────────────────────────────────────────────────
Route::apiResource('expenses', ExpenseController::class);

// عمليات عضو محدد (nested route)
Route::get('members/{member}/transactions', [TransactionController::class, 'memberTransactions'])
    ->name('members.transactions');

// ─── Transactions ─────────────────────────────────────────────────────────────
Route::apiResource('transactions', TransactionController::class)
    ->parameters(['transactions' => 'reference']) // نستخدم reference (TX-0001) كـ URL key
    ->except(['update', 'destroy']);               // العمليات لا تُعدَّل كاملاً، فقط الحالة

// تحديث حالة العملية فقط (اعتماد / إلغاء)
Route::patch('transactions/{reference}/status', [TransactionController::class, 'updateStatus'])
    ->name('transactions.status');

// ─── Dashboard Summary ─────────────────────────────────────────────────────────
Route::get('dashboard', function () {
    $members      = \App\Models\Member::all();
    $transactions = \App\Models\Transaction::where('status', 'completed');

    return response()->json([
        'members_count'     => $members->count(),
        'total_balance'     => (float) $members->sum('balance'),
        'total_deposits'    => (float) $transactions->clone()->whereIn('type', ['deposit', 'adjustment'])->sum('amount'),
        'total_withdrawals' => (float) $transactions->clone()->whereIn('type', ['withdraw', 'transfer'])->sum('amount'),
        'pending_count'     => \App\Models\Transaction::where('status', 'pending')->count(),
        'recent_transactions' => \App\Http\Resources\TransactionResource::collection(
            \App\Models\Transaction::with('member')
                ->orderByDesc('transaction_at')
                ->limit(10)
                ->get()
        ),
    ]);
})->name('dashboard');
