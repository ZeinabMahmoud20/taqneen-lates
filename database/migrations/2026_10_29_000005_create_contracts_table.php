<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المواطن ممكن يكون له أكتر من عقد
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')->constrained('citizens')->cascadeOnDelete();
            $table->foreignId('payment_plan_id')->constrained('payment_plans');
            $table->unsignedInteger('serial_no')->nullable();          // م
            $table->decimal('down_payment_amount', 14, 2)->default(0); // مبلغ الدفعة المقدمة
            $table->date('down_payment_date')->nullable();             // تاريخ المقدم
            $table->decimal('interest_amount', 14, 2)->default(0);     // الفائدة (إدخال يدوي)
            $table->decimal('total_price', 14, 2)->default(0);         // إجمالي الثمن بالفايدة (إدخال يدوي)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['citizen_id', 'payment_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
