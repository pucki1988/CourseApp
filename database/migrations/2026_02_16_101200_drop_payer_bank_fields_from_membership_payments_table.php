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
            $table->dropColumn(['payer_account_holder', 'payer_iban', 'payer_bic']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->string('payer_account_holder')->nullable()->after('amount');
            $table->string('payer_iban', 34)->nullable()->after('payer_account_holder');
            $table->string('payer_bic', 11)->nullable()->after('payer_iban');
        });
    }
};
