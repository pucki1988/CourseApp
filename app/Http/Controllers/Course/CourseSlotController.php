<?php

namespace App\Http\Controllers\Course;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CourseSlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Course $course)
    {
        $this->authorize('create', [CourseSlot::class, $course]);

        $data = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'min_participants' => 'required|integer|min:1',
        ]);

        $slot = $course->slots()->create($data);

        return response()->json(['message' => 'Slot erstellt', 'slot' => $slot]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CourseSlot $slot)
    {
        $this->authorize('update', $slot);

        $data = $request->validate([
            'date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'price' => 'sometimes|nullable|numeric|min:0',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'min_participants' => 'required|integer|min:1',
        ]);

        $slot->update($data);

        return response()->json(['message' => 'Slot aktualisiert', 'slot' => $slot]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Slot $slot)
    {
        $this->authorize('delete', $slot);
        $slot->delete();

        return response()->json([
            'message' => 'Slot erfolgreich gelöscht'
        ], 200);
    }

    public function reschedule(Request $request, CourseSlot $slot)
    {
        $this->authorize('update', $slot);

        $validated = $request->validate([
            'date'       => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
            'rescheduled_at' => now(),
        ]);

        $slot->update($validated);

        return response()->json([
            'message' => 'Slot wurde verschoben.',
            'slot' => $slot
        ]);
    }
    
    public function cancel(CourseSlot $slot)
    {
        $this->authorize('cancel', $slot);

        // falls buchungen existieren → optional kontakt oder automatisches Handling
        $slot->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'Slot wurde abgesagt.',
            'slot' => $slot
        ]);
    }
}
