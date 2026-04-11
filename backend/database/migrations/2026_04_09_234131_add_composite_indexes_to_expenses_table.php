<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * إضافة composite indexes لتحسين الأداء بناءً على أنماط الاستعلام الشائعة:
     * - البحث والفلترة حسب النوع والتاريخ
     * - البحث حسب النوع والتصنيف
     * - البحث حسب الدافع والتاريخ
     * - البحث حسب العضو المتأثر والتاريخ
     * 
     * Requirements: 15.2 - تحسين الأداء للاستعلامات المعقدة
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Composite index للفلترة حسب النوع والتاريخ (الأكثر شيوعاً)
            // يحسن أداء: WHERE expense_type = ? ORDER BY expense_datetime DESC
            $table->index(['expense_type', 'expense_datetime'], 'idx_type_datetime');
            
            // Composite index للفلترة حسب النوع والتصنيف
            // يحسن أداء: WHERE expense_type = ? AND category = ?
            $table->index(['expense_type', 'category'], 'idx_type_category');
            
            // Composite index للبحث حسب الدافع والتاريخ
            // يحسن أداء: WHERE payer_id = ? ORDER BY expense_datetime DESC
            $table->index(['payer_id', 'expense_datetime'], 'idx_payer_datetime');
            
            // Composite index للبحث حسب العضو المتأثر والتاريخ
            // يحسن أداء: WHERE affected_member_id = ? ORDER BY expense_datetime DESC
            $table->index(['affected_member_id', 'expense_datetime'], 'idx_affected_datetime');
            
            // Composite index للبحث حسب طريقة الدفع والتاريخ
            // يحسن أداء: WHERE payment_method = ? ORDER BY expense_datetime DESC
            $table->index(['payment_method', 'expense_datetime'], 'idx_payment_datetime');
        });

        Schema::table('expense_splits', function (Blueprint $table) {
            // Composite index للبحث حسب العضو والمبلغ (لحساب الأرصدة)
            // يحسن أداء: WHERE member_id = ? ORDER BY amount DESC
            $table->index(['member_id', 'amount'], 'idx_member_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_type_datetime');
            $table->dropIndex('idx_type_category');
            $table->dropIndex('idx_payer_datetime');
            $table->dropIndex('idx_affected_datetime');
            $table->dropIndex('idx_payment_datetime');
        });

        Schema::table('expense_splits', function (Blueprint $table) {
            $table->dropIndex('idx_member_amount');
        });
    }
};