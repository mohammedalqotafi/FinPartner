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

    public function expenseSplits(): HasMany
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    public function personalExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'affected_member_id')->where('expense_type', 'personal');
    }

    public function paidExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'payer_id');
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
     * صافي التغيير من المعاملات المباشرة
     */
    public function getNetChangeAttribute(): float
    {
        return $this->total_deposits - $this->total_withdrawals;
    }

    /**
     * الرصيد النهائي المحسوب (Unified Ledger)
     * = رصيد افتتاحي + إيداعات - سحوبات - تقسيمات مشاركة - مصاريف شخصية + مصاريف دفعها نيابة عن غيره
     */
    public function getCalculatedBalanceAttribute(): float
    {
        $depositWithdrawNet = $this->net_change;
        $sharedSplits = (float) $this->expenseSplits()->sum('amount');
        $personalExpenses = (float) $this->personalExpenses()->sum('amount');
        $paidContributions = (float) $this->paidExpenses()->sum('amount');

        return (float) $this->opening_balance 
            + $depositWithdrawNet 
            - $sharedSplits 
            - $personalExpenses 
            + $paidContributions;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotNull('name');
    }
}
