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

   public array $newCourse;

    public function mount(CourseService $service)
    {
        $this->authorize('viewAny', Course::class);
        $this->initializeNewCourse();
        $this->loadCourses($service);
    }

    private function initializeNewCourse(){
        $this->newCourse = [
            'booking_type' => 'per_slot', // Defaultwert
            'price' => null,
            'title' => '',
            'description' => '',
            'capacity' => null,
            'coach_id' => null,
        ];
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

    public function createCourse(CourseService $service)
    {
        $service->createCourse($this->newCourse);
        
        // Modal schließen
        Flux::modal('course')->close();
        $this->initializeNewCourse();

         $this->loadCourses($service);
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Kurse')" :subheading="__('Deine Kurse')">
    @can('create', \App\Models\Course\Course::class)
    <div class="text-end">
    <flux:dropdown>
        <flux:button icon:trailing="chevron-down" class="mb-3">Optionen</flux:button>
        <flux:menu>
            <flux:modal.trigger name="course">
                <flux:menu.item icon="plus">Neuen Kurs erstellen</flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>
    </div>    
    @endcan
    
        
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
                <td class="px-4 py-2">{{ $course?->coach?->name ?? '-' }}</td>
                <td class="px-4 py-2"><flux:badge>{{ $course->slots()->count() }}</flux:badge></td>
                <td class="px-4 py-2 text-right">
                    @can('update', $course)
                    <flux:button size="xs" href="{{ route('course_management.courses.show', $course) }}">Details</flux:button>
                    @endcan
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="mt-4">
        
    </div>
    
    <flux:modal name="course" :dismissible="false" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Neuen Kurs erstellen</flux:heading>
                <flux:text class="mt-2"></flux:text>
            </div>
            <form wire:submit.prevent="createCourse" class="space-y-4">
            <flux:input label="Titel" placeholder="Name des Kurses" type="text" :value="$newCourse['title']"
    wire:change="$set('newCourse.title', $event.target.value)" />
            
            <flux:textarea
                label="Beschreibung"
                placeholder="Beschreibung des Kurses"
                rows="2"
                :value="$newCourse['description']"
    wire:change="$set('newCourse.description', $event.target.value)"
            />
            <flux:field>
            <flux:label>Kurstyp</flux:label>
            <flux:select  :value="$newCourse['booking_type']" wire:change="$set('newCourse.booking_type', $event.target.value)" placeholder="Wähle den Kurstyp">
                <flux:select.option value="all">Alle Termine ein Gesamtpreis</flux:select.option>
                <flux:select.option value="per_slot">Jeder Termin ein Preis</flux:select.option>
            </flux:select>
            </flux:field>

            @if($newCourse['booking_type'] === 'all')
             <flux:input wire:model="newCourse.price" label="Preis" step="0.01" type="number" />
            @endif

            <flux:input wire:model="newCourse.capacity" label="Kapazität" placeholder="Maximale Teilnehmer je Termin" min="1"  type="number" />
            
            <flux:label>Coach</flux:label>
            <flux:select :value="$newCourse['coach_id']" wire:change="$set('newCourse.coach_id', $event.target.value)" placeholder="Trainer auswählen">
                @foreach(User::role('coach')->get() as $coach)
                    <flux:select.option value="{{$coach->id}}">{{ $coach?->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Kurs erstellen</flux:button>
            </div>
            </form>
        </div>
    </flux:modal>    
        
    </x-courses.layout>
</section>





