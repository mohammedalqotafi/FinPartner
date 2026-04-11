<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'reference',
        'member_id',
        'type',
        'amount',
        'transaction_at',
        'note',
        'status',
        'balance_after',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'transaction_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * هل العملية تزيد الرصيد؟ (دائن)
     */
    public function getIsCreditAttribute(): bool
    {
        return in_array($this->type, ['deposit', 'adjustment']);
    }

    /**
     * هل العملية تنقص الرصيد؟ (مدين)
     */
    public function getIsDebitAttribute(): bool
    {
        return in_array($this->type, ['withdraw', 'transfer']);
    }

    /**
     * التأثير على الرصيد (+amount أو -amount)
     */
    public function getBalanceDeltaAttribute(): float
    {
        return $this->is_credit ? (float) $this->amount : -(float) $this->amount;
    }

    // ─── Static Helpers ───────────────────────────────────────────────────────

    /**
     * توليد معرف عملية فريد بصيغة TX-0001
     * يستخدم MAX(id)+1 بعد الحفظ أو sequence counter
     */
    public static function generateReference(): string
    {
        $maxId = static::withTrashed()->max('id') ?? 0;
        $nextId = $maxId + 1;
        
        do {
            $reference = 'TX-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            $nextId++;
        } while (static::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeInDateRange($query, ?string $from, ?string $to)
    {
        if ($from) $query->where('transaction_at', '>=', $from);
        if ($to)   $query->where('transaction_at', '<=', $to . ' 23:59:59');
        return $query;
    }
}
