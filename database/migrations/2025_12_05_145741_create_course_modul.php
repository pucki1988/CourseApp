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
        // Courses
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('booking_type', ['per_course', 'per_slot'])->default('per_slot');
            $table->decimal('price', 10, 2)->nullable(); // nur bei all
            $table->integer('capacity')->nullable();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Course Dates
        Schema::create('course_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('price', 10, 2)->nullable(); 
            $table->integer('capacity')->nullable();
            $table->enum('status', ['active', 'canceled'])->default('active');
            $table->timestamp('rescheduled_at')->nullable();
            $table->unsignedInteger('min_participants')->default(1);
            $table->timestamps();
        });

        // Bookings
        Schema::create('course_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending','paid','partially_refunded','refunded'])->default('pending');
            $table->enum('payment_status', ['open','pending','paid','canceled','expired','failed'])->default('open');
            $table->string('payment_transaction_id')->nullable();
            $table->string('course_title')->nullable();
            $table->string('user_name')->nullable();
             $table->enum('booking_type', ['per_course', 'per_slot'])->default('per_slot');
            $table->timestamps();
        });

        Schema::create('course_booking_refunds', function (Blueprint $table) {
             $table->id();

            $table->foreignId('course_booking_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('reason')->nullable();
            $table->enum('status',['pending','completed','failed'])->default('pending'); 
            // pending | completed | failed

            $table->string('payment_refund_id')->nullable(); // Stripe / Mollie
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
        });

        // Pivot: Booked Slots
        Schema::create('course_booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_booking_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('course_slot_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('status',['booked','canceled','refunded','refund_failed'])->default('booked');
            // booked | cancelled | refunded
            $table->timestamps();
            /** Ein Slot darf pro Booking nur einmal vorkommen */
            $table->unique(['course_booking_id', 'course_slot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_booking_slots');
        Schema::dropIfExists('course_booking_refunds');
        Schema::dropIfExists('course_bookings');
        Schema::dropIfExists('course_slot');
        Schema::dropIfExists('courses');
        
    }
};
