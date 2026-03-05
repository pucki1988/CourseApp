<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_monthly_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('total_slots')->default(0);
            $table->decimal('total_compensation', 10, 2)->default(0);
            $table->string('status')->default('generated');
            $table->string('mail_recipient')->nullable();
            $table->timestamp('mail_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['coach_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });

        Schema::create('coach_monthly_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_monthly_billing_id')->constrained('coach_monthly_billings')->cascadeOnDelete();
            $table->foreignId('course_slot_id')->nullable()->constrained('course_slots')->nullOnDelete();
            $table->date('date');
            $table->string('course_title');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('participant_count')->default(0);
            $table->decimal('compensation', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_monthly_billing_items');
        Schema::dropIfExists('coach_monthly_billings');
    }
};
