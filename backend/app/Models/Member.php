<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'opening_balance',
        'balance',
        'join_date',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'balance'         => 'decimal:2',
        'join_date'       => 'date:Y-m-d',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function completedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('status', 'completed');
    }

    public function pendingTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('status', 'pending');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * إجمالي الإيداعات والتسويات المكتملة
     */
    public function getTotalDepositsAttribute(): float
    {
        return (float) $this->completedTransactions()
            ->whereIn('type', ['deposit', 'adjustment'])
            ->sum('amount');
    }

    /**
     * إجمالي السحوبات والتحويلات المكتملة
     */
    public function getTotalWithdrawalsAttribute(): float
    {
        return (float) $this->completedTransactions()
            ->whereIn('type', ['withdraw', 'transfer'])
            ->sum('amount');
    }

    /**
     * صافي التغيير = الإيداعات - السحوبات
     */
    public function getNetChangeAttribute(): float
    {
        return $this->total_deposits - $this->total_withdrawals;
    }

    /**
     * الرصيد النهائي المحسوب = رصيد افتتاحي + صافي التغيير
     * (يُستعمَل للتحقق من صحة الرصيد المخزون)
     */
    public function getCalculatedBalanceAttribute(): float
    {
        return (float) $this->opening_balance + $this->net_change;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotNull('name');
    }
}
