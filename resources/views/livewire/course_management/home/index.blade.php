
<?php

use App\Models\Course\Course;
use Livewire\Volt\Component;
use App\Services\Course\CourseBookingSlotService;
use App\Services\Course\CourseSlotService;
use App\Models\Course\CourseBookingSlot;
use App\Models\Course\CourseSlot;
use App\Actions\Course\CancelCourseSlotAction;

use App\Models\User;

new class extends Component {

    public $slots;
    public $search = '';
    public $coachId = null;
    public $perPage = 10;
    public bool $showCallout = false;
    public string $calloutMessage = '';
    public string $calloutHeading = 'Erfolg';

    
    public ?CourseSlot $slotToCancel = null;
    public array $slotToReschedule = [];
    public ?CourseSlot $showSlot=null;
    

    public function mount(CourseBookingSlotService $courseBookingSlotService)
    {
        #$this->authorize('viewAny', CourseSlot::class);
        $this->loadSlots($courseBookingSlotService);
    }

    
    public function confirmCancel(CourseSlot $slot)
    {
        $this->slotToCancel = $slot;
        Flux::modal('confirm')->show();
    }

    public function cancel(CourseBookingSlotService $courseBookingSlotService,CancelCourseSlotAction $courseSlotAction)
    {
        if (!$this->slotToCancel) return;
        $this->authorize('cancel', $this->slotToCancel);
        $courseSlotAction->execute($this->slotToCancel);
        
        // Modal schließen
        Flux::modal('confirm')->close();
        $this->slotToCancel = null;

        // Liste neu laden
        $this->loadSlots($courseBookingSlotService);

        // Optional: Toast / Notification
        $this->calloutHeading = 'Termin wurde abgesagt';
        $this->showCallout = true;
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

    public function reschedule(CourseSlotService $courseSlotService,CourseBookingSlotService $courseBookingSlotService)
    {
        
        $courseSlotService->rescheduleSlot($this->slotToReschedule);

        // Modal schließen (Doku-konform)
        Flux::modal('reschedule')->close();

        // Optional: Toast / Notification
        $this->calloutHeading = 'Termin wurde verschoben';
        $this->showCallout = true;

        $this->loadSlots($courseBookingSlotService);
        
    }


    public function loadSlots(CourseBookingSlotService $service)
    {
        $filters = [
            'limit' => 6,
            'status' => 'active'
        ];

        $this->slots = $service->listBookedSlots($filters);
    }

    public function hideCallout()
    {
        $this->showCallout = false;
    }

    public function showBookings(CourseSlot $slot){
        $this->showSlot=$slot;
        Flux::modal('bookings')->show();
    }
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Die nächsten Termine')" :subheading="__('Deine Kurse')">
        @if($showCallout)
            <flux:callout wire:poll.3000ms="hideCallout" variant="success" icon="check-circle" class="my-2" heading="{{ $calloutHeading }}"/>
        @endif
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
            @forelse($slots ?? [] as $slot)
            <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">

                <div class="flex">
                    <div class="flex-1">
                    <flux:heading size="lg">{{ $slot->booking->course->title }} 
                    </flux:heading>

                    <div class="mb-2">
                    <flux:tooltip content="Status des Termins">
                    <flux:badge size="sm">{{ $slot->status }} </flux:badge>
                    </flux:tooltip>
                    @if($slot->slot->rescheduled_at !==null)
                    <flux:tooltip content="Wurde am {{ $slot->slot->rescheduled_at->format('d.m.Y') }} auf diesen Termin verschoben">
                    <flux:badge size="sm">verschoben</flux:badge>
                    </flux:tooltip>
                    @endif
                    </div>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="calendar">{{ $slot->slot->date->format('d.m.Y') }}</flux:badge> 
                    <flux:badge class="ms-1" icon="clock">{{ $slot->slot->start_time->format('H:i') }} – {{ $slot->slot->end_time->format('H:i') }}</flux:badge> 
                    </flux:text>
                    <flux:text class="mt-2">
                    Zusagen <flux:badge icon="information-circle" wire:click="showBookings({{ $slot->slot }})">{{ $slot->booking->course->capacity-$slot->slot->availableSlots()  }} / {{ $slot->booking->course->capacity }}</flux:badge>
                    </flux:text>
                    
                    <flux:text class="mt-2">
                    Coach<flux:badge>
                    @if($slot->course?->coach === null)
                    nicht festgelegt
                    @else
                    {{ $slot->course?->coach?->name }}
                    @endif
                    </flux:badge>
                    </flux:text>
                    
                    </div>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    @can('reschedule', $slot->slot)
                        <flux:button size="sm" variant="primary" wire:click="confirmReschedule({{ $slot->slot }})">Verschieben</flux:button>
                    @endcan
                    @can('cancel', $slot->slot)
                        <flux:button size="sm" variant="danger" wire:click="confirmCancel({{ $slot->slot }})">Absagen</flux:button>
                    @endcan
                </div>

            </div>
        @empty
            <div class="col-span-3 text-neutral-500">
                Keine Slots vorhanden.
            </div>
        @endforelse
        </div>
        
        </div>

        <!-- 🔥 Bestätigungsmodal -->
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
