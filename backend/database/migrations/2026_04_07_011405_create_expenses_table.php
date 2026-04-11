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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // EXP-XXXX - فريد لكل مصروف
            $table->enum('expense_type', ['shared', 'operational', 'personal']);
            $table->foreignId('affected_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->foreignId('payer_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('payment_method');
            $table->text('description')->nullable();
            $table->dateTime('expense_datetime');
            $table->timestamps();

            // Indexes للأداء - البحث والفلترة
            $table->index('expense_type', 'idx_expense_type');
            $table->index('expense_datetime', 'idx_expense_datetime');
            $table->index('category', 'idx_category');
            $table->index('payer_id', 'idx_payer_id');
            $table->index('affected_member_id', 'idx_affected_member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
