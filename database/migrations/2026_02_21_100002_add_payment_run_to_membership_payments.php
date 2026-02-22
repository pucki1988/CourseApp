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
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->foreignId('payment_run_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('payment_runs')
                ->nullOnDelete();
                
            $table->index('payment_run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_run_id']);
            $table->dropColumn('payment_run_id');
        });
    }
};
