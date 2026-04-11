<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * الـ casts لضمان الدقة العشرية وتحويل التاريخ
     * Requirements: 2.1, 8.1
     */
    protected $casts = [
        'amount'           => 'decimal:2',
        'expense_datetime' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * العضو الدافع للمصروف (null = الخزانة)
     * Requirements: 3.1
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'payer_id');
    }

    /**
     * العضو المتأثر (للمصروفات الشخصية فقط)
     * Requirements: 3.1, 11.7
     */
    public function affectedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'affected_member_id');
    }

    /**
     * تقسيمات المصروف المشترك
     * Requirements: 11.7
     */
    public function splits(): HasMany
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    /**
     * توليد reference فريد بصيغة EXP-XXXX مع التحقق من عدم التكرار
     * Requirements: 5.1, 5.2
     */
    public static function generateReference(): string
    {
        do {
            $number    = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $reference = "EXP-{$number}";
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
