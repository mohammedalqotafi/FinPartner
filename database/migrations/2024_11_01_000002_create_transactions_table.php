<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الجدول: transactions
     * يخزن جميع العمليات المالية (إيداعات، سحوبات، تحويلات، تسويات).
     * هذا هو قلب النظام وسجل دفتر الأستاذ.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            /*
             * reference: معرف العملية بصيغة TX-0001
             * يُولَّد تلقائياً بالـ Service قبل الحفظ.
             * unique + index لضمان عدم التكرار وسرعة البحث.
             */
            $table->string('reference', 20)->unique();

            $table->foreignId('member_id')
                  ->constrained('members')
                  ->cascadeOnDelete(); // إذا حُذف العضو حُذفت عملياته

            /*
             * type: نوع العملية
             *   - deposit    → إيداع (يزيد الرصيد)
             *   - withdraw   → سحب  (ينقص الرصيد)
             *   - transfer   → تحويل (ينقص الرصيد)
             *   - adjustment → تسوية (يزيد الرصيد - تعديل يدوي)
             */
            $table->enum('type', ['deposit', 'withdraw', 'transfer', 'adjustment']);

            /*
             * amount: المبلغ دائماً موجب.
             * اتجاه الحركة يُحدَّد بواسطة type.
             * decimal(15,2) لدقة عالية (تدعم حتى 999,999,999,999,99.99)
             */
            $table->decimal('amount', 15, 2);

            /*
             * transaction_at: التاريخ والوقت الفعلي للعملية
             * (قد يختلف عن created_at الذي هو وقت الإدخال)
             */
            $table->dateTime('transaction_at');

            $table->string('note')->nullable();

            /*
             * status: حالة العملية
             *   - completed → مكتملة ومحسوبة في الرصيد
             *   - pending   → معلقة وغير محسوبة في الرصيد
             *   - cancelled → ملغية
             */
            $table->enum('status', ['completed', 'pending', 'cancelled'])
                  ->default('completed');

            /*
             * balance_after: الرصيد بعد هذه العملية (snapshot)
             * يُسهِّل عرض الرصيد التراكمي بدون إعادة الحساب في كل مرة.
             */
            $table->decimal('balance_after', 15, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes للبحث والفلترة السريعة
            $table->index(['member_id', 'transaction_at']);
            $table->index(['member_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
