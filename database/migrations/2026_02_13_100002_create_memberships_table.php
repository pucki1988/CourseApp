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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_type_id')->constrained('membership_types')->onDelete('restrict');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->enum('status', ['active', 'ended', 'cancelled'])->default('active');
            $table->foreignId('payer_member_id')->constrained('members')->onDelete('restrict');
            $table->decimal('calculated_amount', 10, 2)->nullable();
            $table->enum('billing_cycle', ['monthly', 'yearly', 'once']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
