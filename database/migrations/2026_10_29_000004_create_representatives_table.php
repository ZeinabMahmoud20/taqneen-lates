<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // النائب عن المواطن - بيتغير مع الوقت فبنحتفظ بالتاريخ
        // النائب الحالي = ended_at IS NULL
        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')->constrained('citizens')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();   // صلة القرابة
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['citizen_id', 'ended_at']);
        });

        Schema::create('representative_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representative_id')->constrained('representatives')->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representative_phones');
        Schema::dropIfExists('representatives');
    }
};
