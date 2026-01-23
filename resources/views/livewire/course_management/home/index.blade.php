
<?php

use App\Models\Course\Course;
use Livewire\Volt\Component;
use App\Services\Course\CourseBookingSlotService;
use App\Services\Course\CourseSlotService;
use App\Models\Course\CourseBookingSlot;
use App\Models\Course\CourseSlot;
use App\Actions\Course\CancelCourseSlotAction;
use Livewire\Attributes\On;
use App\Models\User;

new class extends Component {

    public $slots;
    public $search = '';
    public $coachId = null;
    public $perPage = 10;
    public bool $showCallout = false;
    public string $calloutMessage = '';
    public string $calloutHeading = 'Erfolg';

    public ?CourseSlot $activeSlot = null;

    public bool $scanLocked = false;

    public string $scanValue = '';
    public ?string $message = null;
    public string $state = 'idle'; // idle, success, error

    
    public ?CourseSlot $slotToCancel = null;
    public array $slotToReschedule = [];
    public ?CourseSlot $showSlot=null;
    

    public function mount(CourseSlotService $courseSlotService)
    {
        #$this->authorize('viewAny', CourseSlot::class);
        $this->loadSlots($courseSlotService);
    }

    
    public function confirmCancel(CourseSlot $slot)
    {
        $this->slotToCancel = $slot;
        Flux::modal('confirm')->show();
    }

    public function cancel(CourseSlotService $courseSlotService,CancelCourseSlotAction $courseSlotAction)
    {
        if (!$this->slotToCancel) return;
        $this->authorize('cancel', $this->slotToCancel);
        $courseSlotAction->execute($this->slotToCancel);
        
        // Modal schließen
        Flux::modal('confirm')->close();
        $this->slotToCancel = null;

        // Liste neu laden
        $this->loadSlots($courseSlotService);

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

    public function reschedule(CourseSlotService $courseSlotService)
    {
        
        $courseSlotService->rescheduleSlot($this->slotToReschedule);

        // Modal schließen (Doku-konform)
        Flux::modal('reschedule')->close();

        // Optional: Toast / Notification
        $this->calloutHeading = 'Termin wurde verschoben';
        $this->showCallout = true;

        $this->loadSlots($courseSlotService);
        
    }


    public function loadSlots(CourseSlotService $service)
    {
        $filters = [
            'limit' => 6,
            'status' => 'active'
        ];

        $this->slots = $service->listAllBookedSlotsBackend($filters);
    }

    public function hideCallout()
    {
        $this->showCallout = false;
    }

    public function showBookings(CourseSlot $slot){

        $this->reset(['message', 'state']);
        $this->showSlot=$slot;
        Flux::modal('bookings')->show();
    }

    public function checkInCourseBookingSlot(CourseBookingSlot $courseBookingSlot){
            
            if($courseBookingSlot->booking->status ==="paid" ||  $courseBookingSlot->booking->status ==="partially_refunded"){
                $courseBookingSlot->update([
                    'checked_in_at' => now(),
                ]);
                $this->state = 'success';
                $this->message = 'Check-in erfolgreich';
            }else{
                $this->state = 'error';
                $this->message = 'Buchung nicht bezahlt';
            }
    }

    public function openCheckin(int $slotId)
    {
        
        $this->reset(['message', 'state']);

        $this->activeSlot = CourseSlot::findOrFail($slotId);

        Flux::modal('checkin')->show();
        $this->dispatch('startScanner');
    }

    #[On('qrScanned')]
    public function qrScanned($value)
    {

        $this->scanValue = $value ?? null;
        // scanValue muss vorher vom JS gesetzt werden
        if (!$this->scanValue) {
            $this->state = 'error';
            $this->message = 'Kein QR-Code erkannt';
            return;
        }

        $this->reset(['message', 'state']);

        try {
            if (!$this->activeSlot) {
                throw new \Exception('Kein Slot aktiv');
            }

            $request = Request::create($this->scanValue);

            if (!URL::hasValidSignature($request)) {
                throw new \Exception('QR-Code ungültig');
            }
            
            $userId = Route::getRoutes()->match($request)->parameter('user');
            if (!$userId) {
                throw new \Exception('User nicht erkannt');
            }

            $baseQuery = $this->activeSlot
            ->bookingSlots()
            ->whereHas('booking', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                ->whereIn('status', ['paid', 'partially_refunded']);
            });

            $hasBooking=(clone $baseQuery)
                ->where('status', 'booked')
                ->whereNull('checked_in_at')
                ->first();

             if (!$hasBooking) {

                $bookingSlotRefunded = $this->activeSlot
                    ->bookingSlots()
                    ->whereHas('booking', function ($q) use ($userId) {
                        $q->where('user_id', $userId)
                        ->where('status', 'refunded');
                    })
                    ->first();

                if($bookingSlotRefunded){
                    throw new \Exception('Termin wurde zurückerstattet');
                }
                
                throw new \Exception('Keine gültige Buchung für diesen Termin');
            }

             
            $alreadyCheckedIn = (clone $baseQuery)
                ->where('status', 'booked')
                ->whereNotNull('checked_in_at')
                ->exists();

            if ($alreadyCheckedIn) {
                throw new \Exception('Teilnehmer wurde bereits eingecheckt');
            }

            $bookingSlot = (clone $baseQuery)
                ->where('status', 'booked')
                ->whereNull('checked_in_at')
                ->first();

            if (!$bookingSlot) {

                $refundedSlot = (clone $baseQuery)
                ->whereIn('status', ['refunded','refund_failed'])
                ->whereNull('checked_in_at')
                ->first();

                if($refundedSlot){
                    throw new \Exception('Termin wurde zurückerstattet');
                }


                throw new \Exception('Keine gültige Buchung für diesen Termin');
            }

            $bookingSlot->update([
                'checked_in_at' => now(),
            ]);

            $this->state = 'success';
            $this->message = 'Check-in erfolgreich';

        } catch (\Throwable $e) {
            $this->state = 'error';
            $this->message = $e->getMessage();
        } finally {
            $this->dispatch('restartScanner');
        }

        // Reset für nächsten Scan
        $this->scanValue = '';
    }

    public function closeCheckin()
    {
        $this->dispatch('stopScanner');
        Flux::modal('checkin')->close();
        $this->reset(['activeSlot', 'message', 'state']);
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
                    <flux:heading size="lg">{{ $slot->course->title }} 
                    </flux:heading>

                    <div class="mb-2">
                    <flux:tooltip content="Status des Termins">
                    <flux:badge size="sm">{{ $slot->status }} </flux:badge>
                    </flux:tooltip>
                    @if($slot->rescheduled_at !== null)
                    <flux:tooltip content="Wurde am {{ $slot->rescheduled_at->format('d.m.Y') }} auf diesen Termin verschoben">
                    <flux:badge size="sm">verschoben</flux:badge>
                    </flux:tooltip>
                    @endif
                    </div>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="calendar">{{ $slot->date->format('d.m.Y') }}</flux:badge> 
                    <flux:badge class="ms-1" icon="clock">{{ $slot->start_time->format('H:i') }} – {{ $slot->end_time->format('H:i') }}</flux:badge> 
                    </flux:text>
                    <flux:text class="mt-2">
                    Zusagen <flux:badge icon="information-circle" wire:click="showBookings({{ $slot }})">{{ $slot->course->capacity-$slot->availableSlots()  }} / {{ $slot->course->capacity }}</flux:badge>
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

                @if($slot->status ==='active')
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:dropdown position="top">
                        <flux:button size="sm" icon:trailing="ellipsis-vertical"></flux:button>
                    <flux:menu>
                    @can('reschedule', $slot)
                    <flux:menu.item icon="chevron-double-right" wire:click="confirmReschedule({{ $slot }})">Verschieben</flux:menu.item>
                    @endcan
                    @can('cancel', $slot)
                    <flux:menu.item icon="x-mark" wire:click="confirmCancel({{ $slot }})">Absagen</flux:menu.item>
                    @endcan
                    @if(auth()->user()->canCheckIn())
                    <flux:menu.item icon="qr-code" wire:click="openCheckin({{ $slot->id }})">Check In</flux:menu.item>
                    @endif
                    </flux:menu>
                    </flux:dropdown>
                </div>
                @endif
                
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
    {{-- MODAL --}}
        <flux:modal name="checkin" :dismissible="false" @close="closeCheckin">
            @if($activeSlot)
                <div class="space-y-4">

                    <flux:heading size="lg">
                        {{ $activeSlot->course->title }}
                    </flux:heading>

                    <flux:text>
                        {{ $activeSlot->date->format('d.m.Y') }}
                        |
                        {{ $activeSlot->start_time->format('H:i') }}
                        –
                        {{ $activeSlot->end_time->format('H:i') }}
                    </flux:text>

                    {{-- QR Scanner --}}
                    <div id="qr-reader" class="w-full"></div>

                    {{-- Feedback --}}
                    @if($message)
                        <flux:callout
                            variant="{{ $state === 'success' ? 'success' : 'danger' }}">
                            {{ $message }}
                        </flux:callout>
                    @endif

                    <div class="flex justify-end">
                        <flux:button variant="ghost" wire:click="closeCheckin">
                            Schließen
                        </flux:button>
                    </div>

                </div>
            @endif
        </flux:modal>
    

    @include('partials.booking-name-show')
        
    </x-courses.layout>
</section>

<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let qrScanner = null;


function startScanner() {
    if (qrScanner) return; // 🚫 schon aktiv

    qrScanner = new Html5Qrcode("qr-reader");

        qrScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            decodedText => {
                Livewire.dispatch('qrScanned', {
                    value: decodedText
                });
            }
        ).catch(err => {
            console.error('Scanner start error', err);
        });
}

function stopScanner() {
    if (!qrScanner) return;

    qrScanner.stop()
            .then(() => {
                qrScanner.clear();
                qrScanner = null;
            })
            .catch(() => {
                qrScanner = null;
            });
}

function restartScanner() {
    stopScanner();
    setTimeout(startScanner, 2500);
}

/* Livewire Events */
window.addEventListener('startScanner', startScanner);
window.addEventListener('stopScanner', stopScanner);
window.addEventListener('restartScanner', restartScanner);

/* Modal Cleanup */
window.addEventListener('flux:modal-closed', () => {
    stopScanner();
});
</script>