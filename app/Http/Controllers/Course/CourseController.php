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
        $this->authorize('create', Course::class);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'booking_type' => ['required', Rule::in(['all', 'per_slot'])],
            'price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'coach_id' => 'nullable|exists:users,id',

             // Slots optional
            'slots' => 'nullable|array',
            'slots.*.date'        => 'required_with:slots|date',
            'slots.*.start_time'  => 'required_with:slots|date_format:H:i',
            'slots.*.end_time'    => 'required_with:slots|date_format:H:i|after:slots.*.start_time',
            'slots.*.price'       => 'nullable|numeric|min:0',
            'slots.*.capacity'    => 'nullable|integer|min:1',
            'slots.*.min_participants'    => 'nullable|integer|min:1',
        ]);

        $course = Course::create($data);

        // Falls Slots existieren → anlegen
        if (!empty($data['slots'])) {
            foreach ($data['slots'] as $slot) {
                $course->slots()->create([
                    'date'       => $slot['date'],
                    'start_time' => $slot['start_time'],
                    'end_time'   => $slot['end_time'],
                    'price'      => $slot['price'] ?? null,
                    'capacity'   => $slot['capacity'] ?? null,
                    'min_participants' => $slot['min_participants'] ?? null,
                ]);
            }
        }

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
                    'status' => $slot->status,
                    'rescheduled_at' => $slot->rescheduled_at,
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
        // Validierung

        $this->authorize('update', $course);
        $data = $request->validate([
            'title'         => 'sometimes|required|string|max:255',
            'description'   => 'sometimes|nullable|string',
            'booking_type'  => ['sometimes', Rule::in(['all','per_slot'])],
            'price'         => 'sometimes|nullable|numeric|min:0',
            'capacity'      => 'sometimes|nullable|integer|min:1',
            'coach_id'      => 'sometimes|nullable|exists:users,id',

            // Slots optional
            'slots' => 'nullable|array',
            'slots.*.id'         => 'nullable|exists:slots,id',
            'slots.*.date'       => 'required_with:slots|date',
            'slots.*.start_time' => 'required_with:slots|date_format:H:i',
            'slots.*.end_time'   => 'required_with:slots|date_format:H:i|after:slots.*.start_time',
            'slots.*.price'      => 'nullable|numeric|min:0',
            'slots.*.capacity'   => 'nullable|integer|min:1',
            'slots.*.min_participants' => 'required|integer|min:1',
        ]);

        // Kursdaten aktualisieren
        $course->update($data);

        // Slots nur aktualisieren, wenn welche im Request enthalten sind
        if ($request->has('slots')) {

            // IDs der übermittelten Slots sammeln
            $incomingIds = collect($data['slots'])
                ->pluck('id')
                ->filter() // null entfernen
                ->toArray();

            // 1. Alle Slots löschen, die nicht im Request sind
            $course->slots()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            // 2. Jeden Slot verarbeiten
            foreach ($data['slots'] as $slotData) {

                // Slot existiert → update
                if (!empty($slotData['id'])) {
                    $course->slots()
                        ->where('id', $slotData['id'])
                        ->update([
                            'date'       => $slotData['date'],
                            'start_time' => $slotData['start_time'],
                            'end_time'   => $slotData['end_time'],
                            'price'      => $slotData['price'] ?? null,
                            'capacity'   => $slotData['capacity'] ?? null,
                            'min_participants' => $slotData['capacity'] ?? null,
                        ]);

                } else {
                    // Neuer Slot → create
                    $course->slots()->create([
                        'date'       => $slotData['date'],
                        'start_time' => $slotData['start_time'],
                        'end_time'   => $slotData['end_time'],
                        'price'      => $slotData['price'] ?? null,
                        'capacity'   => $slotData['capacity'] ?? null,
                        'min_participants' => $slotData['capacity'] ?? null,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Kurs erfolgreich aktualisiert',
            'course' => $course->load('slots')
        ]);
    }   

    /**
     * Kurs löschen (optional)
     */
    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);
        $course->delete();

        return response()->json([
            'message' => 'Kurs gelöscht'
        ]);
    }
}