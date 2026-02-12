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
        Schema::create('coach_compensation_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->integer('min_participants');
            $table->integer('max_participants');
            $table->decimal('compensation', 10, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Ensure no overlapping ranges for the same coach
            $table->index(['coach_id', 'min_participants', 'max_participants'], 'coach_comp_tiers_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_compensation_tiers');
    }
};
