<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            // منع تكرار نفس العضو في نفس المصروف
            $table->unique(['expense_id', 'member_id'], 'unique_expense_member');

            // Indexes للأداء
            $table->index('expense_id', 'idx_split_expense_id');
            $table->index('member_id', 'idx_split_member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_splits');
    }
};
