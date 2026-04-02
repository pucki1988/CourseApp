<?php

use App\Models\Member\Card;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Card::query()
            ->doesntHave('checkinToken')
            ->chunkById(200, function ($cards) {
                foreach ($cards as $card) {
                    $card->issueCheckinToken();
                }
            });
    }

    public function down(): void
    {
        // Intentionally left blank: do not remove existing card tokens on rollback.
    }
};
