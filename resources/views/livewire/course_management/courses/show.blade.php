<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Services\Course\CourseSlotService;
use App\Models\Course\Course;
use Carbon\Carbon;
use App\Models\User;

new class extends Component {

   public Course $course;
   public array $newCourseSlots;
   public array $assistent;

    public function mount(Course $course, CourseService $service)
    {   
        $this->authorize('update', $course);

        $this->course = $course;

        $this->assistent=[
            "date" => now()->format('Y-m-d'),
            "start_time" => "18:00",
            "end_time" => "20:00",
            "intervall" => "weekly",
            "price" => null,
            "repeat" => 1,
            "min_participants" => 1,
            "capacity"=> $course->capacity
        ];

        $this->loadCourse($service);

        $this->newCourseSlots=array("slots"=>[]);
    }

    public function loadCourse(CourseService $service)
    {
        $this->course = $service->loadCourse($this->course);
    }

    public function createSlots(CourseSlotService $service)
    {
        $service->createSlots($this->newCourseSlots,$this->course);
    }

    public function clearSlots()
    {
        $this->newCourseSlots=array("slots"=>[]);
    }

    public function generateCourseSlots()
    {
        $data = $this->assistent;

        $startDate = Carbon::parse($data['date']);

        // Resultat-Array
        $slots = [];

        for ($i = 0; $i <= $data['repeat']; $i++) {

            // Ausrechnen, welcher Tag es wird
            $slotDate = match ($data['intervall']) {
                'daily'   => $startDate->copy()->addDays($i),
                'weekly'  => $startDate->copy()->addWeeks($i),
                'bi_weekly' => $startDate->copy()->addWeeks($i*2),
                'monthly' => $startDate->copy()->addMonths($i),
                default   => $startDate->copy(),
            };

            // Start + Endzeit an das Datum hängen
            $slotStart = $slotDate->copy()->setTimeFromTimeString($data['start_time']);
            $slotEnd   = $slotDate->copy()->setTimeFromTimeString($data['end_time']);

            // Slot ins Array packen
            $slots[] = [
                'course_id'        => $this->course->id,
                'date'             => $slotDate->format('d.m.Y'),
                'start_time'       => $slotStart->format('H:i'),
                'end_time'         => $slotEnd->format('H:i'),
                'price'            => $data['price'],
                'capacity'         => $data['capacity'],
                'min_participants' => $data['min_participants'],
            ];
        }

        $this->newCourseSlots["slots"] = $slots;
        Flux::modal('assistent')->close();
    }

    
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="$course->title" :subheading="__('Deine Kurse')">
    
            <flux:input label="Titel" class="mb-3" placeholder="Name des Kurses" type="text" :value="$course->title" readonly disabled
             />
            
            <flux:textarea class="mb-5"
                label="Beschreibung"
                placeholder="Beschreibung des Kurses"
                rows="2"
                readonly disabled
            >
            {{ $course->description }}
            </flux:textarea>
            

            <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">

            @if($course->booking_type === 'all')
            <div class="grid auto-rows-min gap-4 xl:grid-cols-4">
            <flux:field>
            <flux:label>Kurstyp</flux:label>
            <flux:select  :value="$course->booking_type" disabled>
            <flux:select.option selected value="all">Alle Termine ein Gesamtpreis</flux:select.option>
            </flux:select>
            </flux:field>
            
            <flux:input label="Coach" placeholder="Name des Kurses" type="text" :value="$course->coach->name" readonly disabled/>

            <flux:input :value="$course->capacity" label="Anzahl Teilnehmer (maximal)" placeholder="Maximale Teilnehmer je Termin" min="1"  type="number" readonly disabled/>
            
             <flux:input :value="$course->price" label="Preis für den gesamten Kurs" type="number" readonly disabled />
            @else
                <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
                <flux:field>
                <flux:label>Kurstyp</flux:label>
                <flux:select :value="$course->booking_type" disabled>
                <flux:select.option selected value="per_slot">Jeder Termin ein Preis</flux:select.option>
                </flux:select>
                </flux:field>
                
                <flux:input label="Coach" placeholder="Name des Kurses" type="text" :value="$course->coach->name" readonly disabled/>

                <flux:input :value="$course->capacity" label="Anzahl Teilnehmer (maximal)" placeholder="Maximale Teilnehmer je Termin" min="1"  type="number" readonly disabled/>
                
                
            @endif
            
            </div>
            </div>
            <div class="my-3">
            <flux:dropdown>
                <flux:button icon:trailing="chevron-down">Optionen</flux:button>
                <flux:menu>
                    <flux:modal.trigger name="assistent">
                        <flux:menu.item icon="play">Termin Assistent starten</flux:menu.item>
                    </flux:modal.trigger>
                    <flux:menu.item icon="plus">Einzelnen Termin anlegen</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            @if($newCourseSlots["slots"] ?? [])
            <div class="my-2">
            <flux:button icon="check" variant="primary" color="green" wire:click="createSlots">Geplante Termine speichern</flux:button>
            <flux:button icon="trash" class="ms-2" variant="danger" wire:click="clearSlots">Geplante Termine löschen</flux:button>
            </div>
            @endif
            </div>

            @if($newCourseSlots["slots"] ?? [])
            <flux:heading size="lg">Geplante Termine</flux:heading>
            @endif
            <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
            @forelse(($newCourseSlots["slots"] ?? []) as $index => $slot)
            <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">
                <div class="flex">
                    <div class="flex-1">
                    <flux:heading size="lg">Termin {{ $index + 1 }}</flux:heading>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="calendar">{{ $slot["date"] }}</flux:badge>
                    </flux:text>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="clock">{{ $slot["start_time"] }} – {{ $slot["end_time"] }}</flux:badge> 
                    </flux:text>
                    </div>
                </div>
            </div>
            @empty
            @endforelse
            </div>

            @if($this->course->slots)
            <flux:heading size="lg">Termine</flux:heading>
            @endif
            
            <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
            @forelse(($this->course->slots ?? []) as $index => $slot)
            <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">
                <div class="flex">
                    <div class="flex-1">
                    <flux:heading size="lg">Termin {{ $index + 1 }}</flux:heading>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="calendar">{{ $slot->date->format('d.m.Y') }}</flux:badge>
                    </flux:text>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="clock">{{ $slot->start_time->format('H:i') }} – {{ $slot->end_time->format('H:i') }}</flux:badge> 
                    </flux:text>
                    </div>
                </div>
            </div>
            @empty
            @endforelse
            </div>
            <flux:modal name="assistent" :dismissible="false">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Termin Assistent</flux:heading>
                        <flux:text class="mt-2"></flux:text>
                    </div>
                    <form wire:submit.prevent="generateCourseSlots" class="space-y-4">
                    
                    

                    <flux:input class="mt-2" type="date" label="Startdatum" wire:model="assistent.date"  />
                    <flux:input class="mt-2" type="time" label="Beginn" wire:model="assistent.start_time"  />
                    <flux:input class="mt-2" type="time" label="Ende" wire:model="assistent.end_time"  />


                    <flux:field>
                    <flux:label>Intervall</flux:label>
                    <flux:select wire:model="assistent.intervall" placeholder="Intervall auswählen">
                        <flux:select.option value="daily">täglich</flux:select.option>
                        <flux:select.option value="weekly">wöchentlich</flux:select.option>
                        <flux:select.option value="bi_weekly">alle 2 Wochen</flux:select.option>
                        <flux:select.option value="monthly">monatlich</flux:select.option>
                    </flux:select>
                    </flux:field>
                    @if($course->booking_type === 'per_slot')
                        <flux:input wire:model="assistent.price" step="0.01" label="Preis pro Termin" type="number" />
                    @endif

                    <flux:input class="mt-2" min="0" type="number" label="Anzahl der Wiederholungen" wire:model="assistent.repeat"  />
                    <flux:input class="mt-2" min="1" type="number" label="Mindestteilnehmer je Termin" wire:model="assistent.min_participants"  />
                    <div class="flex">
                        <flux:spacer />
                        <flux:button type="submit" variant="primary">Terminvorschlag erstellen</flux:button>
                        <flux:modal.close><flux:button variant="ghost">Abbrechen</flux:button></flux:modal.close>
                    </div>
                    </form>
                </div>
            </flux:modal>
    </x-courses.layout>
</section>