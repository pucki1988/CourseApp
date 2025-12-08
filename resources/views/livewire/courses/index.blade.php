<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Models\Course\Course;
use App\Models\User;

new class extends Component {

    public $courses;
    public $search = '';
    public $coachId = null;
    public $bookingType = null;
    public $perPage = 10;

    public function mount(CourseService $service)
    {
        $this->authorize('viewAny', Course::class);
        $this->loadCourses($service);
    }

    public function updated($property, CourseService $service)
    {
        if (in_array($property, ['search','coachId','bookingType'])) {
            $this->loadCourses($service);
        }
    }

    public function loadCourses(CourseService $service)
    {
        $filters = [
            'search' => $this->search,
            'coach_id' => $this->coachId,
            'booking_type' => $this->bookingType,
            'paginate' => $this->perPage
        ];
        $this->courses = $service->listCourses($filters);
    }

};
?>

<div class="space-y-4">

    <h1 class="text-2xl font-bold">Kurse</h1>

    <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

        

    </div>

    <!-- COURSES LIST -->
    <table class="min-w-full divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Titel</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Coach</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Typ</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Aktionen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($courses as $course)
            <tr>
                <td class="px-4 py-2">{{ $course->title }}</td>
                <td class="px-4 py-2">{{ $course->coach?->name ?? '-' }}</td>
                <td class="px-4 py-2"><flux:badge>{{ $course->booking_type }}</flux:badge></td>
                <td class="px-4 py-2 text-right">
                    
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="mt-4">
        
    </div>

</div>