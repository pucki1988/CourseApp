<?php

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Course\CourseService;

class CourseController extends Controller
{
    public function store(Request $request, CourseService $service)
    {
        $this->authorize('create', Course::class);

        $course = $service->createCourse($request->all());

        return response()->json([
            'message' => 'Kurs erstellt',
            'course' => $course
        ]);
    }

    /**
     * Zeigt alle Kurse an.
     */
    public function index(Request $request, CourseService $service)
    {
        $this->authorize('viewAny', Course::class);
        $courses = $service->listCourses($request->only(['coach_id', 'booking_type']));
        return response()->json($courses);
    }

    /**
     * Zeigt einen einzelnen Kurs inklusive Termine und Coach.
     */
    public function show(Course $course, CourseService $service)
    {
        return response()->json($service->loadCourse($course));
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