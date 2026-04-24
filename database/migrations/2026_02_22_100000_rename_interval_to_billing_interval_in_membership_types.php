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
        $isSqlite = DB::getDriverName() === 'sqlite';

        // Determine current column name via platform-agnostic helper
        $hasNewName = Schema::hasColumn('membership_types', 'billing_interval');
        $columnName = $hasNewName ? 'billing_interval' : 'interval';

        if ($isSqlite) {
            // SQLite: rename column if needed, then update values.
            // ENUMs and MODIFY COLUMN are not supported; the column stays VARCHAR.
            if ($columnName === 'interval') {
                Schema::table('membership_types', function (Blueprint $table) {
                    $table->renameColumn('interval', 'billing_interval');
                });
            }

            DB::table('membership_types')
                ->where('billing_interval', 'yearly')
                ->update(['billing_interval' => 'annual']);

            return;
        }

        // MySQL / MariaDB path
        // Step 1: Relax ENUM to VARCHAR so we can store the new values
        DB::statement("ALTER TABLE membership_types MODIFY COLUMN `{$columnName}` VARCHAR(50) NULL");

        // Step 2: Map old value
        DB::table('membership_types')
            ->where($columnName, 'yearly')
            ->update([$columnName => 'annual']);

        // Step 3: Rename interval → billing_interval if needed
        if ($columnName === 'interval') {
            Schema::table('membership_types', function (Blueprint $table) {
                $table->renameColumn('interval', 'billing_interval');
            });
        }

        // Step 4: Restore ENUM with extended set
        DB::statement("ALTER TABLE membership_types MODIFY COLUMN billing_interval ENUM('monthly', 'quarterly', 'semi_annual', 'annual') DEFAULT 'monthly'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if ($isSqlite) {
            DB::table('membership_types')
                ->where('billing_interval', 'annual')
                ->update(['billing_interval' => 'yearly']);

            Schema::table('membership_types', function (Blueprint $table) {
                $table->renameColumn('billing_interval', 'interval');
            });

            return;
        }

        // MySQL / MariaDB path
        DB::statement("ALTER TABLE membership_types MODIFY COLUMN billing_interval VARCHAR(50) NULL");

        DB::table('membership_types')
            ->where('billing_interval', 'annual')
            ->update(['billing_interval' => 'yearly']);

        Schema::table('membership_types', function (Blueprint $table) {
            $table->renameColumn('billing_interval', 'interval');
        });

        DB::statement("ALTER TABLE membership_types MODIFY COLUMN `interval` ENUM('monthly', 'yearly') NULL");
    }
};
