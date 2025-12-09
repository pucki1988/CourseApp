<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CourseSlotService
{
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

        return $created;
    }

    public function listSlots(array $filters = [])
    {
        /*CourseSlot::whereHas('course', function ($q) use ($filters) {
            if (auth()->user()->hasRole('coach')) {
                $q->where('coach_id', auth()->id());
            }

            if (!empty($filters['coach_id'])) {
                $q->where('coach_id', $filters['coach_id']);
            }
        })
        ->where('date', '>=', now())
        ->orderBy('date')
        ->limit($filters['limit'] ?? 3)*/
        $query = CourseSlot::with(['course']);

        $query->get();
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
    public function rescheduleSlot(CourseSlot $slot, array $input)
    {
        $validator = Validator::make($input, [
            'date'       => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $data['rescheduled_at'] = now();

        $slot->update($data);

        return $slot;
    }


    /**
     * Cancel a slot (by trainer).
     */
    public function cancelSlot(CourseSlot $slot)
    {
        $slot->update([
            'status' => 'cancelled'
        ]);

        return $slot;
    }

    
}