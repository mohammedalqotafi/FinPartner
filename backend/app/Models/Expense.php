<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function payer()
    {
        return $this->belongsTo(Member::class, 'payer_id');
    }

    public function affectedMember()
    {
        return $this->belongsTo(Member::class, 'affected_member_id');
    }

    public function splits()
    {
        return $this->hasMany(ExpenseSplit::class);
    }
}
