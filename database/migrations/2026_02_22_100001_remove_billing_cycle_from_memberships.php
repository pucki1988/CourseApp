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
        Schema::table('memberships', function (Blueprint $table) {
            // billing_cycle entfernen - wird jetzt vom Type geerbt
            if (Schema::hasColumn('memberships', 'billing_cycle')) {
                $table->dropColumn('billing_cycle');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annual', 'annual'])
                ->nullable()
                ->after('payer_member_id');
        });
    }
};
