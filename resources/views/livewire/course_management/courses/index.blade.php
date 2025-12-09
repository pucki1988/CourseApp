<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Models\Course\Course;
use App\Models\User;

new class extends Component {

    public $courses;
    public $search = '';
    public $coachId = null;
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
            'paginate' => $this->perPage
        ];
        $this->courses = $service->listCourses($filters);
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Kurse')" :subheading="__('Deine Kurse')">
        
    <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

        <!-- Suche -->
        <flux:input
            wire:model.debounce.300ms="search"
            placeholder="Suche nach Titel…"
            icon="magnifying-glass"
        />

        <!-- Coach Filter -->
        <flux:select wire:model="coachId" placeholder="Trainer auswählen">
            <flux:select.option value="">Alle Trainer</flux:select.option>
            @foreach(User::role('coach')->get() as $coach)
                <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
            @endforeach
        </flux:select>

        

    </div>

    <!-- COURSES LIST -->
    <table class="min-w-full divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Titel</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Trainer</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Termine</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Aktionen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($courses as $course)
            <tr>
                <td class="px-4 py-2">{{ $course->title }}</td>
                <td class="px-4 py-2">{{ $course->coach?->name ?? '-' }}</td>
                <td class="px-4 py-2"><flux:badge>{{ $course->slots()->count() }}</flux:badge></td>
                <td class="px-4 py-2 text-right">
                    <flux:button size="xs" href="{{ route('course_management.courses.show', $course) }}">Details</flux:button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="mt-4">
        
    </div>    
        
    </x-courses.layout>
</section>





