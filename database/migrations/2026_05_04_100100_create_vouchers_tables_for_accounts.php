<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->morphs('model');
            $table->text('data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_voucher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['account_id', 'voucher_id']);
            $table->unique('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_voucher');
        Schema::dropIfExists('vouchers');
    }
};