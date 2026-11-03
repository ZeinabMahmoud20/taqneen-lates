<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_no');             // 1..7
            $table->date('due_date');                                  // تاريخ الاستحقاق
            $table->decimal('amount', 14, 2);                          // قيمة القسط الأصلية
            $table->decimal('carried_in_amount', 14, 2)->default(0);   // مرحّل من القسط السابق
            $table->decimal('paid_amount', 14, 2)->default(0);         // المدفوع فعلياً
            $table->decimal('carried_out_amount', 14, 2)->default(0);  // المرحّل للقسط التالي
            $table->date('paid_at')->nullable();                       // تاريخ السداد الفعلي
            // pending = لم يُسدد | paid = مسدد بالكامل | carried_over = اتقفل وباقيه اترحّل
            $table->enum('status', ['pending', 'paid', 'carried_over'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'installment_no']);
            $table->index(['status', 'due_date']);   // استعلام المتأخرات
        });

        Schema::create('installment_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained('installments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_notes');
        Schema::dropIfExists('installments');
    }
};
