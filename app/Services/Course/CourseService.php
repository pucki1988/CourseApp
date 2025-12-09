<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CourseService
{
    /**
     * Kurse auslesen
     */
    public function listCourses(array $filters = [])
    {
        $query = Course::with(['slots', 'coach']);

        // Manager → alles
        if (auth()->user()->hasRole('coach')) {
            $query->where('coach_id', auth()->id());
        }

        if (!empty($filters['coach_id'])) {
            $query->where('coach_id', $filters['coach_id']);
        }

        return $query->get();
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
        return $course->load(['slots', 'coach']);
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
            'booking_type' => ['required', Rule::in(['all', 'per_slot'])],
            'price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'coach_id' => 'nullable|exists:users,id',

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
            'booking_type' => ['sometimes', Rule::in(['all', 'per_slot'])],
            'price' => 'sometimes|nullable|numeric|min:0',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'coach_id' => 'sometimes|nullable|exists:users,id',

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