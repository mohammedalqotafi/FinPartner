<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TransactionResource
 *
 * يُرسَل عند عرض العمليات في الجداول والكشوف.
 * يتوافق مع واجهة Transaction في React (src/types/index.ts).
 */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ─── الحقول الأساسية (تتطابق مع React types) ───────────────

            /*
             * id: نُرسِل reference (TX-0001) بدلاً من الـ DB id الرقمي
             * لأن React يتوقع صيغة TX-XXXX كـ id
             */
            'id'          => $this->reference,  // TX-0001 format

            'memberId'    => (string) $this->member_id,
            'memberName'  => $this->when(
                $this->relationLoaded('member'),
                fn() => $this->member->name,
                $this->member_name_cache ?? ''
            ),

            'type'        => $this->type,     // deposit | withdraw | transfer | adjustment
            'amount'      => (float) $this->amount,

            /*
             * datetime: بصيغة ISO 8601 التي يتوقعها React (2024-11-08T10:35:00)
             */
            'datetime'    => $this->transaction_at?->toIso8601String(),

            'note'        => $this->note ?? '',
            'status'      => $this->status,   // completed | pending | cancelled

            // ─── حقول إضافية للـ Ledger ─────────────────────────────────

            /*
             * balanceAfter: الرصيد بعد هذه العملية للـ Ledger/Running Balance
             */
            'balanceAfter' => $this->balance_after !== null
                                ? (float) $this->balance_after
                                : null,

            /*
             * isCredit / isDebit: مساعدات للـ UI لتلوين الخلايا
             */
            'isCredit'    => in_array($this->type, ['deposit', 'adjustment']),
            'isDebit'     => in_array($this->type, ['withdraw', 'transfer']),

            'createdAt'   => $this->created_at?->toIso8601String(),
        ];
    }
}
