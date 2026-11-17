<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_bookings', function (Blueprint $table) {
            $table->string('coupon_code')->nullable();
            $table->decimal('coupon_discount', 10, 2)->nullable();
        });

        Schema::table('course_booking_slots', function (Blueprint $table) {
            $table->decimal('coupon_refund_price', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('course_booking_slots', function (Blueprint $table) {
            $table->dropColumn('coupon_refund_price');
        });
        Schema::table('course_bookings', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'coupon_discount']);
        });
    }
};
