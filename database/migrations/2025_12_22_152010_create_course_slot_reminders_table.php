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
        Schema::create('course_slot_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_slot_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('minutes_before');
            
            $table->enum('type', ['info', 'min_participants_check'])->default('info');
            // info | min_participants_check

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique([
                'course_slot_id',
                'minutes_before',
                'type'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_slot_reminders');
    }
};
