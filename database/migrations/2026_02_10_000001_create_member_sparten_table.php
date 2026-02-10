<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('blsv_id')->nullable();
            $table->timestamps();
        });

        Schema::create('department_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_sparte_member');
        Schema::dropIfExists('member_sparten');
    }
};
