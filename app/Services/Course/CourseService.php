<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class CourseService
{
    /**
     * Kurse auslesen
     */
    public function listCourses(array $filters = [])
    {
        $courses = Course::query()
        ->whereHas('slots', function ($q) {
            $q->where('status', 'active')
            ->whereRaw(
                "STR_TO_DATE(CONCAT(date, ' ', start_time), '%Y-%m-%d %H:%i:%s') > ?",
                [Carbon::now()]
            );
        })
        ->with([
            'slots' => function ($q) {
                $q->where('status', 'active')
                ->whereRaw(
                    "STR_TO_DATE(CONCAT(date, ' ', start_time), '%Y-%m-%d %H:%i:%s') > ?",
                    [Carbon::now()]
                )
                ->with(['bookedSlots', 'reminders']);
            },
            'coach'
        ])->get();

    

        if (!empty($filters['coach_id'])) {
            $query->where('coach_id', $filters['coach_id']);
        }


       
        $user=auth('sanctum')->user();
        
        $isMember = $user && $user->hasRole('member');

       $courses->each(function ($course) use ($isMember) {
            $course->slots->each(function ($slot) use ($isMember) {

                $slot->display_price = $isMember
                    ? max(0, $slot->price - 1)
                    : $slot->price;

            });
        });


        return $courses;

        
    }


    /**
     * Kurs + Slots erstellen
     */
    public function createCourse(array $input)
    {
        $data = $this->validateCourse($input);

        $course = Course::create($data);

        // Slots erstellen, falls vorhanden
        if (!empty($data['slots'])) {
            foreach ($data['slots'] as $slot) {
                $course->slots()->create([
                    'date' => $slot['date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'price' => $slot['price'] ?? null,
                    'capacity' => $slot['capacity'] ?? null,
                    'min_participants' => $slot['min_participants'] ?? 1,
                ]);
            }
        }

        return $course;
    }


    /**
     * Einzelnen Kurs mit Relationen laden
     */
    public function loadCourse(Course $course)
    {
        return $course->load(['slots' => function ($q) {
        $q->orderBy('date')->orderBy('start_time');
        }],'coach');
    }


    /**
     * Kurs + Slots aktualisieren
     */
    public function updateCourse(Course $course, array $input)
    {
        $data = $this->validateCourseUpdate($input, $course);

        $course->update($data);

        // Slots aktualisieren, falls vorhanden
        if (isset($data['slots'])) {

            $incomingIds = collect($data['slots'])
                ->pluck('id')
                ->filter()
                ->toArray();

            // 1. Slots löschen, die nicht mehr existieren sollen
            $course->slots()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            // 2. Slots aktualisieren / anlegen
            foreach ($data['slots'] as $slot) {
                if (!empty($slot['id'])) {
                    $course->slots()->where('id', $slot['id'])->update($slot);
                } else {
                    $course->slots()->create($slot);
                }
            }
        }

        return $course->load('slots');
    }


    /**
     * Kurs löschen
     */
    public function deleteCourse(Course $course)
    {
        $course->delete();
        return true;
    }

    /**
     * ---- VALIDIERUNG: CREATE ----
     */
    private function validateCourse(array $input): array
    {
        $validator = Validator::make($input, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'booking_type' => ['required', Rule::in(['per_course', 'per_slot'])],
            'price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'coach_id' => 'nullable|exists:coaches,id',
            'location' => 'required|string|max:255',

            // Slots optional
            'slots' => 'nullable|array',
            'slots.*.date' => 'required_with:slots|date',
            'slots.*.start_time' => 'required_with:slots|date_format:H:i',
            'slots.*.end_time' => 'required_with:slots|date_format:H:i|after:slots.*.start_time',
            'slots.*.price' => 'nullable|numeric|min:0',
            'slots.*.capacity' => 'nullable|integer|min:1',
            'slots.*.min_participants' => 'nullable|integer|min:1',
        ]);

        return $validator->validate();
    }

    /**
     * ---- VALIDIERUNG: UPDATE ----
     */
    private function validateCourseUpdate(array $input, Course $course): array
    {
        $validator = Validator::make($input, [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'booking_type' => ['sometimes', Rule::in(['per_course', 'per_slot'])],
            'price' => 'sometimes|nullable|numeric|min:0',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'coach_id' => 'sometimes|nullable|exists:coaches,id',
            'location' => 'sometimes|required|string|max:255',

            'slots' => 'nullable|array',
            'slots.*.id' => 'nullable|exists:slots,id',
            'slots.*.date' => 'required_with:slots|date',
            'slots.*.start_time' => 'required_with:slots|date_format:H:i',
            'slots.*.end_time' => 'required_with:slots|date_format:H:i|after:slots.*.start_time',
            'slots.*.price' => 'nullable|numeric|min:0',
            'slots.*.capacity' => 'nullable|integer|min:1',
            'slots.*.min_participants' => 'required|integer|min:1',
        ]);

        return $validator->validate();
    }
}