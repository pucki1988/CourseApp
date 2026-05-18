<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('managed_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('members')
            ->whereNotNull('user_id')
            ->whereNull('managed_by_user_id')
            ->update([
                'managed_by_user_id' => DB::raw('user_id'),
            ]);

        $duplicateUserIds = DB::table('members')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        if ($duplicateUserIds->isNotEmpty()) {
            throw new \RuntimeException(
                'Die Migration auf User-zu-Member 1:1 wurde abgebrochen. Bitte bereinige doppelte members.user_id Werte fuer User IDs: '
                . $duplicateUserIds->implode(', ')
            );
        }

        Schema::table('members', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropConstrainedForeignId('managed_by_user_id');
        });
    }
};
