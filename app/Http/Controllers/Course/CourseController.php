<?php

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'booking_type' => ['required', Rule::in(['all', 'per_slot'])],
            'price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'coach_id' => 'nullable|exists:users,id',
        ]);

        $course = Course::create($data);

        return response()->json([
            'message' => 'Kurs erstellt',
            'course' => $course
        ]);
    }

    /**
     * Zeigt alle Kurse an.
     */
    public function index(Request $request)
    {

        $query = Course::with(['slots', 'coach']);

        // Optional: nach Coach filtern
        if ($request->has('coach_id')) {
            $query->where('coach_id', $request->coach_id);
        }

        // Optional: nach Buchungstyp filtern
        if ($request->has('booking_type')) {
            $query->where('booking_type', $request->booking_type);
        }

        $courses = $query->get();

        return response()->json($courses);
    }

    /**
     * Zeigt einen einzelnen Kurs inklusive Termine und Coach.
     */
    public function show(Course $course)
    {
        $course->load(['slots', 'coach']);

        return response()->json([
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'booking_type' => $course->booking_type,
            'price' => $course->price,
            'capacity' => $course->capacity,
            'coach' => $course->coach ? [
                'id' => $course->coach->id,
                'name' => $course->coach->name,
                'email' => $course->coach->email,
            ] : null,
            'slots' => $course->slots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'date' => $slot->date,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'price' => $slot->price,
                    'capacity' => $slot->capacity,
                    'booked' => $slot->bookings()->where('status', 'confirmed')->count(),
                ];
            }),
        ]);
    }


    /**
     * Kurs aktualisieren
     */
    public function update(Request $request, Course $course)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'booking_type' => ['sometimes','required', Rule::in(['all', 'per_slot'])],
            'price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'coach_id' => 'nullable|exists:users,id',
        ]);

        $course->update($data);

        return response()->json([
            'message' => 'Kurs aktualisiert',
            'course' => $course
        ]);
    }

    /**
     * Kurs löschen (optional)
     */
    public function destroy(Course $course)
    {
        $course->delete();

        return response()->json([
            'message' => 'Kurs gelöscht'
        ]);
    }
}