<?php

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Course\CourseService;
use App\Http\Resources\CourseResource;

class CourseController extends Controller
{
    public function store(Request $request, CourseService $service)
    {
        $this->authorize('create', Course::class);

        $course = $service->createCourse($request->all());
        
        // Sportarten speichern wenn vorhanden
        if ($request->has('sport_type_ids') && is_array($request->input('sport_type_ids'))) {
            $course->sportTypes()->sync($request->input('sport_type_ids'));
        }
        
        // Ausrüstung speichern wenn vorhanden
        if ($request->has('equipment_item_ids') && is_array($request->input('equipment_item_ids'))) {
            $course->equipmentItems()->sync($request->input('equipment_item_ids'));
        }
        
        // Lade die Relations für Response
        $course->load(['sportTypes', 'equipmentItems', 'slots', 'coach']);

        return response()->json([
            'message' => 'Kurs erstellt',
            'course' => new CourseResource($course)
        ]);
    }

    /**
     * Zeigt alle Kurse an.
     */
    public function index(Request $request, CourseService $service)
    {
        #$this->authorize('viewAny', Course::class);
        $courses = $service->listCourses($request->only(['coach_id', 'booking_type']));
        
        // Lade die neuen Relations
        $courses->load(['sportTypes', 'equipmentItems']);

        return CourseResource::collection($courses)->resolve();
        
    }

    /**
     * Zeigt einen einzelnen Kurs inklusive Termine und Coach.
     */
    public function show(Course $course, CourseService $service)
    {
        $course = $service->loadCourse($course);
        return response()->json(new CourseResource($course));
    }


    /**
     * Kurs aktualisieren
     */
    public function update(Request $request, Course $course, CourseService $service)
    {
        // Validierung

        $this->authorize('update', $course);
        
        $updated = $service->update($course, $request->all());

        return response()->json([
            'message' => 'Kurs erfolgreich aktualisiert',
            'course' => $updated
        ]);
    }   

    /**
     * Kurs löschen (optional)
     */
    public function destroy(Course $course, CourseService $service)
    {
        $this->authorize('delete', $course);
        $service->deleteCourse($course);

        return response()->json(['message' => 'Kurs gelöscht']);
    }
}