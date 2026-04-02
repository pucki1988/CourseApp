<?php

use App\Models\Member\Card;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('checkin_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->morphs('tokenable');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        $now = now();

        $userRows = DB::table('users')
            ->select('id')
            ->get()
            ->map(function ($row) use ($now) {
                return [
                    'token' => (string) Str::uuid(),
                    'tokenable_type' => User::class,
                    'tokenable_id' => $row->id,
                    'revoked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        if (!empty($userRows)) {
            DB::table('checkin_tokens')->insert($userRows);
        }

        $cardRows = DB::table('cards')
            ->where('active', true)
            ->select('id')
            ->get()
            ->map(function ($row) use ($now) {
                return [
                    'token' => (string) Str::uuid(),
                    'tokenable_type' => Card::class,
                    'tokenable_id' => $row->id,
                    'revoked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        if (!empty($cardRows)) {
            DB::table('checkin_tokens')->insert($cardRows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_tokens');
    }
};
