<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Models\Course\Course;
use App\Models\User;

new class extends Component {

   public Course $course;
   public array $newCourseSlots;

    public function mount(Course $course, CourseService $service)
    {   
        $this->authorize('update', $course);

        $this->course = $course;

        #$this->loadCourse($service);
    }

    public function loadCourse(CourseService $service)
    {
        $this->course = $service->loadCourse($course);
    }

    
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="$course->title" :subheading="__('Deine Kurse')">
    
            <flux:input label="Titel" placeholder="Name des Kurses" type="text" :value="$course->title"
             />
            
            <flux:textarea
                label="Beschreibung"
                placeholder="Beschreibung des Kurses"
                :value="$course->description"
                
            />
            <flux:field>
            <flux:label>Kurstyp</flux:label>
            <flux:select  :value="$course->booking_type" placeholder="Wähle den Kurstyp">
                <flux:select.option value="all">Alle Termine ein Gesamtpreis</flux:select.option>
                <flux:select.option value="per_slot">Jeder Termin ein Preis</flux:select.option>
            </flux:select>
            </flux:field>

            @if($course->booking_type === 'all')
             <flux:input wire:model="$course->price" label="Preis" type="number" />
            @endif

            <flux:input wire:model="$course->capacity" label="Kapazität" placeholder="Maximale Teilnehmer je Termin" min="1"  type="number" />
            <flux:input wire:model="$course->min_participants" label="Mindestteilnehmer" placeholder="Mindestteilnehmer je Termin" min="1"  type="number" />
            
            <flux:label>Coach</flux:label>
            <flux:select :value="$course->coach_id" wire:change="$set('newCourse.coach_id', $event.target.value)" placeholder="Trainer auswählen">
                @foreach(User::role('coach')->get() as $coach)
                    <flux:select.option value="{{$coach->id}}">{{ $coach->name }}</flux:select.option>
                @endforeach
            </flux:select>
            
            
        
    </x-courses.layout>
</section>