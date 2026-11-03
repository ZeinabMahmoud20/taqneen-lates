<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * لو تاريخ المقدم فاضي في الإكسيل، القسط بيتسجل من غير موعد استحقاق (مع تنبيه)
 * والمستخدم يقدر يضيفه يدوي وقت السداد. القسط من غير موعد مش بيظهر في المتأخرات.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE installments MODIFY due_date DATE NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE installments SET due_date = COALESCE(paid_at, CURDATE()) WHERE due_date IS NULL');
        DB::statement('ALTER TABLE installments MODIFY due_date DATE NOT NULL');
    }
};
