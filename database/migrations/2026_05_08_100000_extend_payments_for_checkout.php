<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->char('currency', 3)->default('EUR')->after('amount');
            $table->string('provider')->nullable()->after('method');
            $table->string('provider_payment_id')->nullable()->unique()->after('provider');
            $table->string('checkout_url')->nullable()->after('provider_payment_id');
            $table->json('meta')->nullable()->after('reference');
            $table->timestamp('failed_at')->nullable()->after('paid_at');
            $table->timestamp('canceled_at')->nullable()->after('failed_at');
            $table->timestamp('refunded_at')->nullable()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'currency', 'provider', 'provider_payment_id',
                'checkout_url', 'meta',
                'failed_at', 'canceled_at', 'refunded_at',
            ]);
        });
    }
};
