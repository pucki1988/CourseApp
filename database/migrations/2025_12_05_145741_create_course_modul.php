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
            $table->enum('booking_type', ['all', 'per_slot'])->default('per_slot');
            $table->decimal('price', 8, 2)->nullable(); // nur bei all
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
            $table->decimal('price', 8, 2)->nullable(); // nur bei per_date
            $table->integer('capacity')->nullable();
            $table->timestamps();
        });

        // Bookings
        Schema::create('course_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_price', 8, 2);
            $table->enum('status', ['confirmed','waitlist'])->default('confirmed');
            $table->enum('payment_status', ['open','pending','paid','canceled','expired','failed'])->default('open');
            $table->string('payment_transaction_id')->nullable();
            $table->timestamps();
        });

        // Pivot: Booked Slots
        Schema::create('course_booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_booking_id')->constrained('course_bookings')->cascadeOnDelete();
            $table->foreignId('course_slot_id')->constrained('course_slots')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_booking_slots');
        Schema::dropIfExists('course_bookings');
        Schema::dropIfExists('course_slot');
        Schema::dropIfExists('courses');
    }
};
