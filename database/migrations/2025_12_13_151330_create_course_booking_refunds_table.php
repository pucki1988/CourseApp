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
        Schema::create('course_booking_refunds', function (Blueprint $table) {
             $table->id();

            $table->foreignId('course_booking_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('reason')->nullable();
            $table->string('status')->default('pending'); 
            // pending | completed | failed

            $table->string('payment_refund_id')->nullable(); // Stripe / Mollie
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_booking_refunds');
    }
};
