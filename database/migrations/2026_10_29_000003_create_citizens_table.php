<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizens', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                 // كود (253190)
            $table->string('name');
            $table->string('national_id', 14)->nullable()->unique(); // اختياري
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->text('address')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        // أرقام هاتف المواطن (أكتر من رقم)
        Schema::create('citizen_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')->constrained('citizens')->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('label')->nullable();   // موبايل / منزل / واتساب
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_phones');
        Schema::dropIfExists('citizens');
    }
};
