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
            $table->string('reference')->unique();
            $table->enum('expense_type', ['shared', 'operational', 'personal']);
            $table->foreignId('affected_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->foreignId('payer_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('payment_method');
            $table->text('description')->nullable();
            $table->dateTime('expense_datetime');
            $table->timestamps();
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
