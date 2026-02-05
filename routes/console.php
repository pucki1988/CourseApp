<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Course\CourseSlot;
use App\Actions\Course\CancelCourseAction;
use App\Models\User;
use App\Mail\CourseConfirmedMail;
use Illuminate\Support\Facades\Mail;
use App\Services\Loyalty\LoyaltyPointService;

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

Artisan::command('loyalty:recalc {userId?}', function (?int $userId = null) {
    $service = app(LoyaltyPointService::class);

    if ($userId) {
        $user = User::find($userId);

        if (! $user) {
            $this->error("User {$userId} nicht gefunden.");
            return 1;
        }

        $balance = $service->recalculate($user);
        $this->info("User {$userId} neu berechnet: {$balance}");
        return 0;
    }

    User::chunkById(200, function ($users) use ($service) {
        foreach ($users as $user) {
            $service->recalculate($user);
        }
    });

    $this->info('Alle User neu berechnet.');
    return 0;
});