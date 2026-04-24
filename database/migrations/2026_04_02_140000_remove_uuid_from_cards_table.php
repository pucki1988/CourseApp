<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        Schema::table('cards', function (Blueprint $table) use ($isSqlite) {
            // SQLite refuses to DROP COLUMN when a named index still references it.
            if ($isSqlite) {
                $table->dropUnique('cards_uuid_unique');
            }
            $table->dropColumn('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });
    }
};
