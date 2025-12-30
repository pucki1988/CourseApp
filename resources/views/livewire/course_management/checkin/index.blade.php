<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseBookingSlotService;
use App\Models\Course\CourseSlot;
use Illuminate\Support\Facades\URL;

new class extends Component {

    public $slots;

    public ?CourseSlot $activeSlot = null;

    public ?string $message = null;
    public string $state = 'idle';

    protected $listeners = ['qrScanned'];

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

    public function qrScanned(string $value)
    {
        $this->reset(['message', 'state']);

        try {
            if (!$this->activeSlot) {
                throw new Exception('Kein Slot aktiv');
            }

            if (!URL::hasValidSignature(request()->create($value))) {
                throw new Exception('QR-Code ungültig');
            }

            parse_str(parse_url($value, PHP_URL_QUERY), $query);
            $userId = $query['user'] ?? null;

            if (!$userId) {
                throw new Exception('User nicht erkannt');
            }

            $bookingSlot = $this->activeSlot
                ->bookingSlots()
                ->whereHas('booking', fn ($q) =>
                    $q->where('user_id', $userId)
                )
                ->where('status', 'booked')
                ->first();

            if (!$bookingSlot) {
                throw new Exception('Keine gültige Buchung');
            }

            $bookingSlot->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ]);

            $this->state = 'success';
            $this->message = 'Check-in erfolgreich';

        } catch (Throwable $e) {
            $this->state = 'error';
            $this->message = $e->getMessage();
        }
    }

    public function closeCheckin()
    {
        $this->dispatch('stopScanner');
        Flux::modal('checkin')->close();
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

                    <flux:button
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
        <flux:modal name="checkin" :dismissible="false">
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