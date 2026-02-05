<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Services\Course\CoachService;
use App\Models\Course\Course;
use App\Models\Course\SportType;
use App\Models\Course\EquipmentItem;
use App\Models\User;

new class extends Component {

    public $courses;
    public $coaches;
    public $sportTypes;
    public $equipmentItems;
    public $search = '';
    public $coachId = null;
    public $perPage = 10;

    public ?Course $courseToDelete = null;

    public ?string $message = null;
    public string $state = 'idle'; // idle, success, error

   public array $newCourse;
   public array $selectedSportTypes = [];
   public array $selectedEquipmentItems = [];

    public function mount(CourseService $service, CoachService $coachService)
    {
        $this->authorize('viewAny', Course::class);
        $this->initializeNewCourse();
        $this->loadCourses($service);
        $this->loadCoaches($coachService);
        $this->loadSportTypes();
        $this->loadEquipmentItems();
    }

    private function initializeNewCourse(){
        $this->newCourse = [
            'booking_type' => 'per_slot', // Defaultwert
            'price' => null,
            'title' => '',
            'description' => '',
            'capacity' => null,
            'coach_id' => null,
            'location' => '',
            'member_discount' => null,
            'difficulty_level' => null
        ];
        $this->selectedSportTypes = [];
        $this->selectedEquipmentItems = [];
    }

    public function loadSportTypes()
    {
        $this->sportTypes = SportType::all();
    }

    public function loadEquipmentItems()
    {
        $this->equipmentItems = EquipmentItem::all();
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

    public function loadCoaches(CoachService $service)
    {
        $this->coaches = $service->listCoaches(array("active" => true));
    }


    public function createCourse(CourseService $service)
    {
         $course=$service->createCourse($this->newCourse);
        
        // Sportarten speichern
        if (!empty($this->selectedSportTypes)) {
            $course->sportTypes()->sync($this->selectedSportTypes);
        }
        
        // Equipment speichern
        if (!empty($this->selectedEquipmentItems)) {
            $course->equipmentItems()->sync($this->selectedEquipmentItems);
        }
        
        // Modal schließen
        Flux::modal('course')->close();
        #$this->initializeNewCourse();

         #$this->loadCourses($service);
         return $this->redirect(
         route('course_management.courses.show', $course->id),
              navigate: true
            );
    }

    public function confirmDelete(Course $course)
    {
        $this->courseToDelete = $course;
        Flux::modal('delete')->show();
    }

    public function delete(CourseService $service)
    {
        $service->deleteCourse($this->courseToDelete);
        Flux::modal('delete')->close();
        $this->loadCourses($service);
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')


    <x-courses.layout :heading="__('Kurse')" :subheading="__('Deine Kurse')">
        <div class="space-y-6">
    @can('create', Course::class)
    
        <div class="flex justify-end">
            <flux:modal.trigger name="course">
                <flux:button icon="plus">Neuer Kurs</flux:button>
            </flux:modal.trigger>
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
            @foreach($coaches as $coach)
                <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
            @endforeach
        </flux:select>

        

    </div>

            <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach($courses as $course)

            <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Titel</span>
                                <span>{{ $course->title }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Trainer</span>
                                <span>{{ $course?->coach?->name ?? '-' }}</span>
                            </div>
                            <div class="flex justify-center mt-1">
                                @can('update', $course)
                                <flux:button size="xs" href="{{ route('course_management.courses.show', $course) }}">Details</flux:button>
                                @endcan
                                
                                @can('delete', $course)
                                <flux:button size="xs" variant="danger" class="ms-2"  wire:click="confirmDelete({{ $course }})">Löschen</flux:button>
                                @endcan
                            </div>
                        </div>
            </div>
            @endforeach
        </div>
        </div>

    <!-- Pagination -->
    <div class="mt-4">
        
    </div>
    
    <flux:modal name="course" flyout>
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
                <flux:select.option value="per_course">Alle Termine ein Gesamtpreis</flux:select.option>
                <flux:select.option value="per_slot">Jeder Termin ein Preis</flux:select.option>
            </flux:select>
            </flux:field>

            @if($newCourse['booking_type'] === 'per_course')
             <flux:input wire:model="newCourse.price" label="Preis" step="0.01" type="number" />
            @endif

            <flux:input label="Ort" placeholder="Ort des Kurses" type="text" :value="$newCourse['location']"
    wire:change="$set('newCourse.location', $event.target.value)" />

            <flux:input wire:model="newCourse.capacity" label="Kapazität" placeholder="Maximale Teilnehmer je Termin" min="1"  type="number" />
            
            <flux:label>Coach</flux:label>
            <flux:select :value="$newCourse['coach_id']" wire:change="$set('newCourse.coach_id', $event.target.value)" placeholder="Trainer auswählen">
                @foreach($coaches as $coach)
                    <flux:select.option value="{{$coach->id}}">{{ $coach?->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="newCourse.member_discount" label="Mitglieder Rabatt" step="0.01" type="number" />

            <flux:field>
            <flux:label>Schwierigkeitsgrad</flux:label>
            <flux:select wire:model="newCourse.difficulty_level" placeholder="Wähle den Schwierigkeitsgrad">
                <flux:select.option value="">Kein Schwierigkeitsgrad</flux:select.option>
                <flux:select.option value="beginner">Anfänger</flux:select.option>
                <flux:select.option value="intermediate">Fortgeschrittene</flux:select.option>
                <flux:select.option value="advanced">Fortgeschrittene+</flux:select.option>
                <flux:select.option value="expert">Experte</flux:select.option>
            </flux:select>
            </flux:field>

            <flux:field>
            <flux:label>Sportarten</flux:label>
            <div class="space-y-2">
                @foreach($sportTypes as $sport)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" 
                               wire:model="selectedSportTypes" 
                               value="{{ $sport->id }}"
                               class="rounded" />
                        <span>{{ $sport->name }}</span>
                    </label>
                @endforeach
            </div>
            </flux:field>

            <flux:field>
            <flux:label>Benötigte Ausrüstung</flux:label>
            <div class="space-y-2">
                @foreach($equipmentItems as $equipment)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" 
                               wire:model="selectedEquipmentItems" 
                               value="{{ $equipment->id }}"
                               class="rounded" />
                        <span>{{ $equipment->name }}</span>
                    </label>
                @endforeach
            </div>
            </flux:field>
                <flux:spacer />
                <flux:button type="submit" variant="primary">Kurs erstellen</flux:button>
            </div>
            </form>
        </div>
    </flux:modal>
    
    <flux:modal name="delete" >
        <flux:heading size="lg">Kurs endgültig löschen</flux:heading>
        
        
        <flux:text class="mt-2">    
            Bist du sicher, dass du den Kurs engültig löschen möchtest? 
        </flux:text>
        <flux:callout variant="warning" class="my-2" icon="exclamation-circle" heading="Die Aktion kann nicht mehr rückgängig gemacht werden" />

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
            <flux:button
                variant="ghost"
            >
                Abbrechen
            </flux:button>
            </flux:modal.close>
            <flux:button
                variant="danger"
                wire:click="delete"
            >
                Löschen
            </flux:button>
        </div>
        </flux:modal>
        
    </x-courses.layout>
</section>





