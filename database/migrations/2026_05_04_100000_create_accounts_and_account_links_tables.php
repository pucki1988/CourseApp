<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });

        $this->backfillAccounts();
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::dropIfExists('accounts');
    }

    private function backfillAccounts(): void
    {
        DB::table('users')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                if ($user->account_id) {
                    continue;
                }

                $accountId = DB::table('accounts')->insertGetId([
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('users')->where('id', $user->id)->update([
                    'account_id' => $accountId,
                ]);
            }
        });

        DB::table('members')->orderBy('id')->chunkById(200, function ($members): void {
            foreach ($members as $member) {
                if ($member->account_id) {
                    continue;
                }

                $accountId = DB::table('users')->where('id', $member->user_id)->value('account_id');

                if (! $accountId) {
                    $accountId = DB::table('accounts')->insertGetId([
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('members')->where('id', $member->id)->update([
                    'account_id' => $accountId,
                ]);
            }
        });

        DB::table('cards')->orderBy('id')->chunkById(200, function ($cards): void {
            foreach ($cards as $card) {
                if ($card->account_id) {
                    continue;
                }

                $accountId = DB::table('members')->where('id', $card->member_id)->value('account_id');

                if (! $accountId) {
                    $accountId = DB::table('accounts')->insertGetId([
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('cards')->where('id', $card->id)->update([
                    'account_id' => $accountId,
                ]);
            }
        });
    }
};