<?php

namespace App\Http\Controllers\Course;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Course\CourseSlot;
use App\Models\Course\Course;

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

        $validated = $request->validate([
        'slots' => 'required|array|min:1',
        'slots.*.date' => 'required|date',
        'slots.*.start_time' => 'required|date_format:H:i',
        'slots.*.end_time' => 'required|date_format:H:i|after:slots.*.start_time',
        'slots.*.price' => 'nullable|numeric|min:0',
        'slots.*.capacity' => 'nullable|integer|min:1',
        ]);

        $createdSlots = [];

        foreach ($validated['slots'] as $slotData) {
            $createdSlots[] = $course->slots()->create($slotData);
        }

        return response()->json(['message' => 'Slot erstellt', 'slots' => $createdSlots]);
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
    public function destroy(CourseSlot $slot)
    {
        $this->authorize('delete', $slot);
        $slot->delete();

        return response()->json([
            'message' => 'Slot erfolgreich gelöscht'
        ], 200);
    }

    public function reschedule(Request $request, CourseSlot $courseSlot)
    {
        $this->authorize('reschedule', $courseSlot);

        $validated = $request->validate([
            'date'       => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
        ]);
        $validated['rescheduled_at'] = now();

        $courseSlot->update($validated);

        return response()->json([
            'message' => 'Slot wurde verschoben.',
            'slot' => $courseSlot
        ]);
    }
    
    //Trainer sagt Slot ab.
    public function cancel(CourseSlot $slot)
    {
        $this->authorize('cancel', $slot);

        
        $slot->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'Slot wurde abgesagt.',
            'slot' => $slot
        ]);
    }
}
