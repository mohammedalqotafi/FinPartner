<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseSplit extends Model
{
    protected $guarded = ['id'];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
