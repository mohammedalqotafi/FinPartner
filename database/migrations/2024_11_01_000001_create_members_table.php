<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الجدول: members
     * يخزن بيانات أعضاء الفريق (ليسوا مستخدمين للنظام، فقط سجلات مالية).
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable();

            /*
             * opening_balance: الرصيد الافتتاحي (قبل أي عملية)
             * balance: الرصيد الحالي المحسوب = opening_balance + deposits - withdrawals
             * يُحدَّث atomically مع كل عملية مكتملة لضمان الأداء.
             */
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);

            $table->date('join_date')->nullable();

            $table->timestamps();
            $table->softDeletes(); // حذف ناعم لحفظ السجل المحاسبي
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
