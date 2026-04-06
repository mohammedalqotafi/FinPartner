<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MemberDetailResource
 *
 * يُرسَل عند فتح صفحة تفاصيل العضو (Member Detail Page).
 * يحتوي على بيانات العضو + دفتر الأستاذ الكامل مع الرصيد التراكمي.
 *
 * يتوافق مع ما تتوقعه React في MemberDetailPage.tsx
 */
class MemberDetailResource extends JsonResource
{
    /**
     * بيانات دفتر الأستاذ المحسوبة من TransactionService::buildLedger()
     */
    public array $ledger = [];

    public function __construct($resource, array $ledger = [])
    {
        parent::__construct($resource);
        $this->ledger = $ledger;
    }

    public function toArray(Request $request): array
    {
        return [
            // ─── بيانات العضو ────────────────────────────────────────────

            'id'             => (string) $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'balance'        => (float) $this->balance,
            'openingBalance' => (float) $this->opening_balance,
            'joinDate'       => $this->join_date?->toDateString(),

            // ─── ملخص مالي (Summary Cards) ───────────────────────────────

            'summary' => [
                'currentBalance'   => $this->ledger['final_balance'] ?? (float) $this->balance,
                'totalDeposits'    => $this->ledger['total_deposits'] ?? 0,
                'totalWithdrawals' => $this->ledger['total_withdrawals'] ?? 0,
                'netChange'        => $this->ledger['net_change'] ?? 0,
                'openingBalance'   => $this->ledger['opening_balance'] ?? (float) $this->opening_balance,
            ],

            // ─── دفتر الأستاذ (Ledger Rows) مع الرصيد التراكمي ──────────

            'ledger' => collect($this->ledger['rows'] ?? [])->map(function ($row) {
                return [
                    'tx'      => new TransactionResource($row['tx']),
                    'credit'  => (float) $row['credit'],
                    'debit'   => (float) $row['debit'],
                    'running' => (float) $row['running'],
                ];
            })->values(),

            // ─── العمليات المعلقة (للتنبيه في الـ UI) ─────────────────

            'pendingTransactions' => TransactionResource::collection(
                $this->whenLoaded('pendingTransactions')
            ),
        ];
    }
}
