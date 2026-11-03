<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // نوع السداد: كاش / 3 سنوات / 5 سنوات / 7 سنوات
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                          // "ثلاث سنوات"
            $table->unsignedTinyInteger('installments_count')->default(0); // 0 = كاش
            $table->boolean('is_cash')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
