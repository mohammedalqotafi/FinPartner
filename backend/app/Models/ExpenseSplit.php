<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseSplit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * الـ casts لضمان الدقة العشرية
     * Requirements: 2.1
     */
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * المصروف المرتبط بهذا التقسيم
     * Requirements: 17.1
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * العضو المشارك في هذا التقسيم
     * Requirements: 17.1
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
