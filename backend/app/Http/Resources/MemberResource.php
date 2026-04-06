<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MemberResource
 *
 * يُرسَل عند عرض قائمة الأعضاء وبطاقة كل عضو.
 * يتوافق مع واجهة Member في React (src/types/index.ts).
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ─── الحقول الأساسية (تتطابق مع React types) ───────────────

            'id'              => (string) $this->id,   // React يستخدم string ID
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,

            /*
             * balance: الرصيد الحالي المحسوب
             * يُرسَل كرقم عشري من نوع float
             */
            'balance'         => (float) $this->balance,

            /*
             * openingBalance: بنفس الـ camelCase الذي يتوقعه React
             */
            'openingBalance'  => (float) $this->opening_balance,

            'joinDate'        => $this->join_date?->toDateString(),

            // ─── إحصاءات إضافية (للبطاقات والـ Dashboard) ────────────

            'totalDeposits'   => (float) $this->when(
                $this->relationLoaded('transactions'),
                fn() => $this->transactions
                    ->where('status', 'completed')
                    ->whereIn('type', ['deposit', 'adjustment'])
                    ->sum('amount')
            ),

            'totalWithdrawals' => (float) $this->when(
                $this->relationLoaded('transactions'),
                fn() => $this->transactions
                    ->where('status', 'completed')
                    ->whereIn('type', ['withdraw', 'transfer'])
                    ->sum('amount')
            ),

            'pendingCount'    => $this->when(
                $this->relationLoaded('transactions'),
                fn() => $this->transactions->where('status', 'pending')->count()
            ),

            'transactionsCount' => $this->when(
                $this->relationLoaded('transactions'),
                fn() => $this->transactions->count()
            ),
        ];
    }
}
