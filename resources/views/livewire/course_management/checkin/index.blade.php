<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Services\Course\CourseBookingSlotService;
use App\Models\Course\CourseSlot;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;


new class extends Component {

    public $slots;

    public ?CourseSlot $activeSlot = null;

    public bool $scanLocked = false;

    public string $scanValue = '';
    public ?string $message = null;
    public string $state = 'idle'; // idle, success, error


    public function mount(CourseBookingSlotService $service)
    {
        $this->loadSlots($service);
    }

    public function loadSlots(CourseBookingSlotService $service)
    {
        $this->slots = $service->listBookedSlots([
            'limit' => 6,
            'status' => 'active',
        ]);
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

            $alreadyCheckedIn = (clone $baseQuery)
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
    <x-courses.layout heading="Mögliche Check-Ins">

        <div class="grid gap-4 xl:grid-cols-3">
            @forelse($slots as $slot)
                <div class="rounded-xl border p-4 flex flex-col justify-between">

                    <div>
                        <flux:heading size="lg">
                            {{ $slot->booking->course->title }}
                        </flux:heading>

                        <flux:text class="mt-2">
                            <flux:badge icon="calendar">
                                {{ $slot->slot->date->format('d.m.Y') }}
                            </flux:badge>
                            <flux:badge icon="clock" class="ms-1">
                                {{ $slot->slot->start_time->format('H:i') }}
                                –
                                {{ $slot->slot->end_time->format('H:i') }}
                            </flux:badge>
                        </flux:text>
                    </div>

                    <flux:button class="mt-2"
                        variant="ghost"
                        size="sm"
                        wire:click="openCheckin({{ $slot->slot->id }})">
                        Check-In
                    </flux:button>

                </div>
            @empty
                <div class="text-neutral-500">
                    Keine Slots vorhanden
                </div>
            @endforelse
        </div>

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
    setTimeout(startScanner, 1200);
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