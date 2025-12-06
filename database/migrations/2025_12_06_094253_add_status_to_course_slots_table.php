<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_slots', function (Blueprint $table) {
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamp('rescheduled_at')->nullable();
            $table->unsignedInteger('min_participants')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_slots', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('rescheduled_at');
            $table->dropColumn('min_participants');
        });
    }
};
