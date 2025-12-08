<?php

namespace App\Http\Controllers\Course;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Course\CourseSlot;
use App\Models\Course\Course;
use App\Services\Course\CourseSlotService;

class CourseSlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Course $course, CourseSlotService $service)
    {
        $this->authorize('create', [CourseSlot::class, $course]);

        $slots = $service->createSlots($request->all(), $course);

        return response()->json([
            'message' => 'Slot(s) erstellt',
            'slots' => $slots
        ]);
    }
    

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CourseSlot $slot,CourseSlotService $service)
    {
        $this->authorize('update', $slot);

        $slots = $service->updateSlot($request->all(), $slot);

        return response()->json(['message' => 'Slot aktualisiert', 'slot' => $slot]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseSlot $slot,CourseSlotService $service)
    {
        $this->authorize('delete', $slot);
    
        $service->deleteSlot($slot);

        return response()->json([
            'message' => 'Slot erfolgreich gelöscht'
        ], 200);
    }

    public function reschedule(Request $request, CourseSlot $courseSlot,CourseSlotService $service)
    {
        $this->authorize('reschedule', $courseSlot);
        $courseSlot=$service->rescheduleSlot($courseSlot,$request->all());

        return response()->json([
            'message' => 'Slot wurde verschoben.',
            'slot' => $courseSlot
        ]);
    }
    
    //Trainer sagt Slot ab.
    public function cancel(CourseSlot $slot,CourseSlotService $service)
    {
        $this->authorize('cancel', $slot);

        $slot=$service->cancelSlot($slot);

        return response()->json([
            'message' => 'Slot wurde abgesagt.',
            'slot' => $slot
        ]);
    }
}
