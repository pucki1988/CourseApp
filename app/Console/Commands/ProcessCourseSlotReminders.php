<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Course\CourseSlotReminder;
use App\Models\Course\CourseSlot;
use Carbon\Carbon;

class ProcessCourseSlotReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-course-slot-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process reminders for course slots';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        CourseSlot::query()
            ->where('status', 'active')
            ->with(['reminders', 'bookedSlots'])
            ->chunk(50, function ($slots) use ($now) {
                foreach ($slots as $slot) {
                    $this->processSlot($slot, $now);
                }
            });

        return Command::SUCCESS;
    }

    protected function processSlot(CourseSlot $slot, Carbon $now)
    {
        foreach ($slot->reminders as $reminder) {

            if ($reminder->sent_at !== null) {
                continue;
            }

            $triggerTime = $slot->startDateTime
                ->copy()
                ->subMinutes($reminder->minutes_before);

            if ($now->lessThan($triggerTime)) {
                continue;
            }

            $this->triggerReminder($slot, $reminder);
        }
    }

    protected function triggerReminder(
        CourseSlot $slot,
        CourseSlotReminder $reminder
    ) {
        match ($reminder->type) {
            'info' => SendCourseSlotReminderMail::dispatch($slot, $reminder),
            'min_participants_check' => CheckMinParticipants::dispatch($slot),
        };

        $reminder->update(['sent_at' => now()]);
    }
}
