<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Member\Member;
use App\Models\Member\MemberStatusHistory;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Für alle bestehenden Mitglieder einen 'joined' Eintrag erstellen
        Member::chunk(100, function ($members) {
            foreach ($members as $member) {
                // Prüfen ob bereits ein joined Eintrag existiert
                $hasJoined = $member->statusHistory()
                    ->where('action', 'joined')
                    ->exists();

                if (!$hasJoined) {
                    MemberStatusHistory::create([
                        'member_id' => $member->id,
                        'action' => 'joined',
                        'action_date' => $member->entry_date,
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // joined Einträge entfernen
        MemberStatusHistory::where('action', 'joined')->delete();
    }
};
