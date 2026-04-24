<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (Schema::hasColumn('membership_payments', 'status')) {
            if (! $isSqlite) {
                DB::statement("ALTER TABLE membership_payments MODIFY status ENUM('pending', 'paid', 'collected', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
            }

            DB::table('membership_payments')
                ->where('status', 'paid')
                ->update(['status' => 'collected']);

            DB::table('membership_payments')
                ->whereNotIn('status', ['pending', 'collected', 'failed', 'cancelled'])
                ->update(['status' => 'pending']);

            if (! $isSqlite) {
                DB::statement("ALTER TABLE membership_payments MODIFY status ENUM('pending', 'collected', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
            }
        }

        if (! $isSqlite && Schema::hasColumn('membership_payments', 'bank_account_id')) {
            Schema::table('membership_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('bank_account_id');
            });
        }

        if (! $isSqlite && Schema::hasColumn('membership_payments', 'payment_run_id')) {
            Schema::table('membership_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_run_id');
            });
        }

        $columnsToDrop = [];

        foreach (['method', 'reference', 'paid_at'] as $column) {
            if (Schema::hasColumn('membership_payments', $column)) {
                $columnsToDrop[] = $column;
            }
        }

        if (! empty($columnsToDrop)) {
            Schema::table('membership_payments', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (Schema::hasColumn('membership_payments', 'status') && ! $isSqlite) {
            DB::statement("ALTER TABLE membership_payments MODIFY status ENUM('pending', 'paid', 'collected', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('membership_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('membership_payments', 'method')) {
                $table->enum('method', ['sepa', 'cash'])->after('amount');
            }

            if (! Schema::hasColumn('membership_payments', 'reference')) {
                $table->string('reference')->nullable()->after('method');
            }

            if (! Schema::hasColumn('membership_payments', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('membership_payments', 'bank_account_id')) {
                $table->foreignId('bank_account_id')
                    ->nullable()
                    ->after('membership_id')
                    ->constrained('bank_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('membership_payments', 'payment_run_id')) {
                $table->foreignId('payment_run_id')
                    ->nullable()
                    ->after('bank_account_id')
                    ->constrained('payment_runs')
                    ->nullOnDelete();
                $table->index('payment_run_id');
            }
        });

        DB::table('membership_payments')
            ->where('status', 'collected')
            ->update(['status' => 'paid']);

        DB::table('membership_payments')
            ->where('status', 'failed')
            ->update(['status' => 'pending']);

        if (Schema::hasColumn('membership_payments', 'status') && ! $isSqlite) {
            DB::statement("ALTER TABLE membership_payments MODIFY status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
