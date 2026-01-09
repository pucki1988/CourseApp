<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Course\CourseSlot;
use App\Actions\Course\CancelCourseAction;

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