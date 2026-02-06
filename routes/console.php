<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Course\CourseSlot;
use App\Actions\Course\CancelCourseAction;
use App\Models\User;
use App\Mail\CourseConfirmedMail;
use Illuminate\Support\Facades\Mail;
use App\Services\Loyalty\LoyaltyPointService;
use App\Models\Loyalty\LoyaltyAccount;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:cancel-course {slot_id} {--reason=}', function ($slot_id) {
    $slot = CourseSlot::find($slot_id);

    if (! $slot) {
        $this->error("CourseSlot mit ID {$slot_id} wurde nicht gefunden.");
        return 1;
    }

    $reason = $this->option('reason');

    // Action ausführen
    app(CancelCourseAction::class)->execute($slot, $reason);

    $this->info("CourseSlot ID {$slot_id} erfolgreich abgesagt.");
    return 0;
})->describe('Cancel a CourseSlot for testing purposes');

Artisan::command('test:course-mail', function () {
    $slot = CourseSlot::find(50);
    $user = User::find(20);

    Mail::to($user->email)->queue(
        new CourseConfirmedMail($slot, $user)
    );

    $this->info('Mail getestet ✅');
});

Artisan::command('create:missing-loyalty-accounts', function () {
    $users = User::doesntHave(relation: 'loyaltyAccount')->get();

    foreach ($users as $user) {
        $account = LoyaltyAccount::create(['type' => 'user']);
        $user->loyalty_account_id = $account->id;
        $user->save();

        $this->info("LoyaltyAccount für User {$user->id} erstellt.");
    }

    $this->info("Fertig!");

});