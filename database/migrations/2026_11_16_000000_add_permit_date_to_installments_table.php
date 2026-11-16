<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            // تاريخ الأذن: تاريخ إضافي بيتسجل من ملف «إضافة أقساط» ومش بيأثر على الاستحقاق
            $table->date('permit_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn('permit_date');
        });
    }
};
