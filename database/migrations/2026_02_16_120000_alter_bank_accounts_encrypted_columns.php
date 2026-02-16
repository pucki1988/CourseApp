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
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->text('iban')->change();
            $table->text('bic')->nullable()->change();
            $table->text('account_holder')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('iban', 34)->change();
            $table->string('bic', 11)->nullable()->change();
            $table->string('account_holder')->change();
        });
    }
};
