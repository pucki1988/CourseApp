<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use App\Models\Course\CourseSlotReminder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use App\Events\CourseSlotRescheduled;

class CourseSlotService
{

    public function __construct(
        protected CourseBookingSlotService $courseBookingSlotService
    ) {}
    /**
     * Create multiple CourseSlots for a course.
     */
    public function createSlots(array $input, Course $course)
    {
        $validator = Validator::make($input, [
            'slots' => 'required|array|min:1',
            'slots.*.date' => 'required|date',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time' => 'required|date_format:H:i|after:slots.*.start_time',
            'slots.*.price' => 'nullable|numeric|min:0',
            'slots.*.min_participants' => 'required|integer|min:1',
            'slots.*.capacity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $created = [];

        foreach ($validated['slots'] as $slot) {
            $created[] = $course->slots()->create($slot);
        }

        $this->createRemindersForSlots($created);

        return $created;
    }

    public function listSlots(array $filters = [])
    {
        //Alle Slots
        $query=CourseSlot::with('bookings')->whereHas('course', function ($q) use ($filters) {
            
            
            if (!empty($filters['status'])) {
                $q->where('status', $filters['status']);
            }
        })

        


        ->whereRaw("TIMESTAMP(date, start_time) >= ?", [now()])
        ->orderBy('date');

        if (!empty($filters['limit'])) {
            $query->limit($filters['limit'] ?? 3);
        }
       
        $user=auth()->user();

        if ($user->hasAnyRole(['user', 'member'])) {
            $query->whereHas('bookings', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query->get();
    }


    public function listAllBookedSlotsBackend(array $filters = [])
    {
        //Nur Slots wo mindestens 1 Buchung vorhanden ist und mindestens 1 BookingSlot noch nicht eingecheckt
        $query = CourseSlot::query()
        ->with(['course.coach', 'bookingSlots.booking'])
        ->where('status', 'active')
        // 🔹 Nur zukünftige / laufende Slots
        ->where(function ($q) {
            $q->whereDate('date', '>', now()->addDays(-3))
            ->orWhere(function ($q2) {
                $q2->whereDate('date', now())
                    ->whereTime('start_time', '>=', now()->format('H:i'));
            });
        })

        // 🔹 Slot muss mind. eine gültige Buchung haben
        /*
        ->whereHas('bookingSlots.booking', function ($q) {
            $q->whereIn('status', ['paid', 'partially_refunded']);
        })*/

        // 🔹 Mind. ein Slot ist noch nicht eingecheckt
        /*
        ->whereHas('bookingSlots', function ($q) {
            $q->where('status', 'booked')
            ->whereNull('checked_in_at');
        })*/

        


        ->orderBy('date')
        ->orderBy('start_time');


        $user=auth()->user();

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return $query->get();
        }
            $coachId = $user->coach?->id;

            if ($coachId) {
                $query->whereHas('course', function ($q) use ($coachId) {
                    $q->where('coach_id', $coachId);
                });
            }    
            return $query->get();
    }


    /**
     * Update a single CourseSlot.
     */
    public function updateSlot(CourseSlot $slot, array $input)
    {
        $validator = Validator::make($input, [
            'date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'price' => 'sometimes|nullable|numeric|min:0',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'min_participants' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $slot->update($validator->validated());

        return $slot;
    }


    /**
     * Delete a CourseSlot.
     */
    public function deleteSlot(CourseSlot $slot)
    {
        $slot->delete();
        return true;
    }


    /**
     * Reschedule an existing slot.
     */
    public function rescheduleSlot(array $input)
    {
        $validator = Validator::make($input, [
            'id'         => 'required|exists:course_slots,id',
            'date'       => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $data['rescheduled_at'] = now();
        $slot = CourseSlot::findOrFail($data['id']);

        $oldData=["date" => $slot->date,"start_time" =>$slot->start_time];

        $slot->update($data);

        $slot->refresh();
        
        event(new CourseSlotRescheduled($slot,$oldData));

        return $slot;
    }

    private function createRemindersForSlots(array|Collection $slots): void
    {
        foreach ($slots as $courseSlot) {
            $this->createRemindersForSingleSlot($courseSlot);
        }
    }

    private function createRemindersForSingleSlot(CourseSlot $courseSlot): void
    {
        $definitions = [
        [
            'type' => 'info',
            'minutes_before' => 120, // 2 Stunden
        ],
        [
            'type' => 'min_participants_check',
            'minutes_before' => 1440, // 1 Tag
        ],
        ];

    foreach ($definitions as $definition) {

        $sentAt = $courseSlot->startDateTime()
            ->copy()
            ->subMinutes($definition['minutes_before']);

        // Reminder nur anlegen, wenn Zeitpunkt noch in der Zukunft liegt
        if ($sentAt->isPast()) {
            continue;
        }

        CourseSlotReminder::create([
            'course_slot_id' => $courseSlot->id,
            'type' => $definition['type'],
            'minutes_before' => $definition['minutes_before'],
        ]);
    }
    }
    
    
}