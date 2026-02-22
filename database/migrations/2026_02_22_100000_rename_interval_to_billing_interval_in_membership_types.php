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
        // Check if column is already renamed
        $columns = DB::select('SHOW COLUMNS FROM membership_types WHERE Field = ?', ['billing_interval']);
        $columnName = !empty($columns) ? 'billing_interval' : 'interval';
        
        // Schritt 1: ENUM temporär zu VARCHAR ändern
        DB::statement("ALTER TABLE membership_types MODIFY COLUMN `{$columnName}` VARCHAR(50) NULL");
        
        // Schritt 2: yearly → annual konvertieren
        DB::table('membership_types')
            ->where($columnName, 'yearly')
            ->update([$columnName => 'annual']);
        
        // Schritt 3: Umbenennen interval → billing_interval (falls noch nicht erfolgt)
        if ($columnName === 'interval') {
            Schema::table('membership_types', function (Blueprint $table) {
                $table->renameColumn('interval', 'billing_interval');
            });
        }
        
        // Schritt 4: Zurück zu ENUM mit erweiterten Werten
        DB::statement("ALTER TABLE membership_types MODIFY COLUMN billing_interval ENUM('monthly', 'quarterly', 'semi_annual', 'annual') DEFAULT 'monthly'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schritt 1: ENUM zu VARCHAR
        DB::statement("ALTER TABLE membership_types MODIFY COLUMN billing_interval VARCHAR(50) NULL");
        
        // Schritt 2: annual → yearly zurück
        DB::table('membership_types')
            ->where('billing_interval', 'annual')
            ->update(['billing_interval' => 'yearly']);
        
        // Schritt 3: Umbenennen zurück
        Schema::table('membership_types', function (Blueprint $table) {
            $table->renameColumn('billing_interval', 'interval');
        });
        
        // Schritt 4: Zurück zum alten ENUM
        DB::statement("ALTER TABLE membership_types MODIFY COLUMN `interval` ENUM('monthly', 'yearly') NULL");
    }
};
