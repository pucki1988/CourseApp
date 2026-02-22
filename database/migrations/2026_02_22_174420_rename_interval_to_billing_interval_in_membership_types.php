<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create new column with extended enum values
        Schema::table('membership_types', function (Blueprint $table) {
            $table->enum('billing_interval', ['monthly', 'quarterly', 'semi_annual', 'annual'])->nullable()->after('billing_mode');
        });
        
        // Migrate data: 'yearly' → 'annual', 'monthly' stays 'monthly'
        DB::table('membership_types')->where('interval', 'yearly')->update(['billing_interval' => 'annual']);
        DB::table('membership_types')->where('interval', 'monthly')->update(['billing_interval' => 'monthly']);
        
        // Drop old column
        Schema::table('membership_types', function (Blueprint $table) {
            $table->dropColumn('interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Create old column
        Schema::table('membership_types', function (Blueprint $table) {
            $table->enum('interval', ['monthly', 'yearly'])->nullable()->after('billing_mode');
        });
        
        // Migrate data back: 'annual' → 'yearly', 'monthly' stays 'monthly'
        DB::table('membership_types')->where('billing_interval', 'annual')->update(['interval' => 'yearly']);
        DB::table('membership_types')->where('billing_interval', 'monthly')->update(['interval' => 'monthly']);
        
        // Drop new column
        Schema::table('membership_types', function (Blueprint $table) {
            $table->dropColumn('billing_interval');
        });
    }
};
