<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Services\Course\CourseSlotService;
use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use Carbon\Carbon;
use App\Models\User;
use App\Actions\Course\CancelCourseSlotAction;

new class extends Component {

   public Course $course;
   public array $newCourseSlots;
   public array $assistent;

   

    
    public ?CourseSlot $slotToCancel = null;
    public ?CourseSlot $slotToDelete = null;

    public ?CourseSlot $showSlot=null;

    public array $slotToReschedule = [];

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

    public function loadCourse()
    {
        $service = app(CourseService::class);   // Service automatisch aus Container holen
        $this->course = $service->loadCourse($this->course);
        
    }

    public function createSlots(CourseSlotService $service)
    {
        $service->createSlots($this->newCourseSlots,$this->course);
        $this->clearSlots();
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

    public function confirmCancel(CourseSlot $slot)
    {
        $this->slotToCancel = $slot;
        Flux::modal('confirm')->show();
    }

    
    public function cancel(CourseSlotService $service,CancelCourseSlotAction $courseSlotAction)
    {
        if (!$this->slotToCancel) return;
        $this->authorize('cancel', $this->slotToCancel);
        $courseSlotAction->execute($this->slotToCancel);
        
        // Modal schließen
        Flux::modal('confirm')->close();
        $this->slotToCancel = null;

        // Liste neu laden
        $this->loadCourse();
    }

    public function confirmReschedule(CourseSlot $slot)
    {
        $this->slotToReschedule =[
        'id'          => $slot->id,
        'date'        => $slot->date?->format('Y-m-d'),
        'start_time'  => $slot->start_time?->format('H:i'),
        'end_time'    => $slot->end_time?->format('H:i')
        ];
        Flux::modal('reschedule')->show();
    }

    public function reschedule(CourseSlotService $service)
    {
        $service->rescheduleSlot($this->slotToReschedule);
        // Modal schließen (Doku-konform)
        Flux::modal('reschedule')->close();
        $this->loadCourse();
    }

    public function confirmDelete(CourseSlot $slot)
    {
        $this->slotToDelete = $slot;
        Flux::modal('delete')->show();
    }

    public function delete(CourseSlotService $service)
    {
        $service->deleteSlot($this->slotToDelete);
        $this->loadCourse();
    }


    public function showBookings(CourseSlot $slot){
        $this->showSlot=$slot;
        Flux::modal('bookings')->show();
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

            @if($course->booking_type === 'per_course')
            <div class="grid auto-rows-min gap-4 xl:grid-cols-4">
            <flux:field>
            <flux:label>Kurstyp</flux:label>
            <flux:select  :value="$course->booking_type" disabled>
            <flux:select.option selected value="per_course">Alle Termine ein Gesamtpreis</flux:select.option>
            </flux:select>
            </flux:field>
            
            <flux:input label="Coach" placeholder="Coach" type="text" :value="$course?->coach?->name" readonly disabled/>

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
                
                <flux:input label="Coach" placeholder="Coach des Kurses" type="text" :value="$course?->coach?->name" readonly disabled/>

                <flux:input :value="$course->capacity" label="Anzahl Teilnehmer (maximal)" placeholder="Maximale Teilnehmer je Termin" min="1"  type="number" readonly disabled/>
                
                
            @endif
            
            </div>
            </div>
            <div class="my-3">
            <flux:dropdown>
                <flux:button icon:trailing="chevron-down">Termin Optionen</flux:button>
                <flux:menu>
                    <flux:modal.trigger name="assistent">
                        <flux:menu.item icon="play">Termin Assistent starten</flux:menu.item>
                    </flux:modal.trigger>
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

            @if($this->course->slots->count() > 0)
            <flux:heading size="lg">Termine</flux:heading>
            @endif
            
            <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
            @forelse(($this->course->slots ?? []) as $index => $slot)
            <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">
                <div class="flex">
                    <div class="flex-1">
                    <flux:heading size="lg" class="mb-3">Termin {{ $index + 1 }} <flux:badge size="sm" class="ms-2" color="{{ $slot->status == 'active' ? 'green' : 'gray' }}" inset="top bottom">
    {{ $slot->status }}
</flux:badge></flux:heading>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="calendar">{{ $slot->date->format('d.m.Y') }}</flux:badge>
                    </flux:text>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="clock">{{ $slot->start_time->format('H:i') }} – {{ $slot->end_time->format('H:i') }}</flux:badge> 
                    </flux:text>
                    @if($course->booking_type == 'per_slot')
                    <flux:text class="mt-2">
                    <flux:badge icon="currency-euro">{{ $slot->price }}</flux:badge> 
                
                    @endif
                    </flux:text>
                    <flux:text class="mt-2">
                    Zusagen <flux:badge icon="information-circle" wire:click="showBookings({{ $slot }})">  {{ $slot->bookingSlots()->where('status', 'booked')->count() }} / {{ $slot->capacity }}</flux:badge>
                    </flux:text>
                    
                    
                    </div>
                </div>
                 <div class="flex gap-2">
                    <flux:spacer />
                    @if(auth()->user()->can('reschedule', $slot) || auth()->user()->can('cancel', $slot) || auth()->user()->can('delete', $slot))
                    <flux:dropdown position="top">
                        <flux:button size="sm" icon:trailing="ellipsis-vertical"></flux:button>
                    <flux:menu>
                    @can('reschedule', $slot)
                    <flux:menu.item icon="chevron-double-right" wire:click="confirmReschedule({{ $slot }})">Verschieben</flux:menu.item>
                    @endcan
                    @can('cancel', $slot)
                    <flux:menu.item icon="x-mark" wire:click="confirmCancel({{ $slot }})">Absagen</flux:menu.item>
                    @endcan
                    @can('delete', $slot)
                    <flux:menu.item icon="trash" wire:click="confirmDelete({{ $slot }})">Löschen</flux:menu.item>
                    @endcan
                    </flux:menu>
                    </flux:dropdown>
                    @endif
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
                        <flux:input wire:model="assistent.price" step="0.01" label="Preis pro Termin" type="number" required />
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


        <flux:modal name="confirm" >
        <flux:heading size="lg">Termin absagen</flux:heading>

        <flux:text class="mt-2">
            Bist du sicher, dass du diesen Termin absagen möchtest?
        </flux:text>

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
                wire:click="cancel"
            >
                Absagen
            </flux:button>
        </div>
        </flux:modal>


        <flux:modal name="delete" >
        <flux:heading size="lg">Termin endgültig löschen</flux:heading>
        
        
        <flux:text class="mt-2">    
            Bist du sicher, dass du diesen Termin engültig löschen möchtest? 
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

    <flux:modal name="reschedule" flyout>
        <form wire:submit="reschedule">
        
        <flux:heading size="lg" class="mb-2">Termin verschieben</flux:heading>

        <flux:input class="mb-2" type="date" label="Datum" wire:model="slotToReschedule.date"  />
        <flux:input class="mb-2" type="time" label="Beginn" wire:model="slotToReschedule.start_time"  />
        <flux:input class="mb-2" type="time" label="Ende" wire:model="slotToReschedule.end_time"  />

        <div class="mt-4 flex justify-end space-x-2">
            <flux:modal.close>
            <flux:button
                variant="ghost"
            >
                Abbrechen
            </flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="primary">
                Verschieben
            </flux:button>
        </div>
    </form>
    </flux:modal>

    @include('partials.booking-name-show')

    </x-courses.layout>
</section>