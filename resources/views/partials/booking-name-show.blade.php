<flux:modal name="bookings" class="md:w-96" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Buchungen</flux:heading>
        </div>

        {{-- Feedback --}}
        @if($message)
            <flux:callout
                variant="{{ $state === 'success' ? 'success' : 'danger' }}">
                {{ $message }}
            </flux:callout>
        @endif

        @foreach (($this->showSlot->bookedSlots) ?? [] as $index => $bookedSlot)
        <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">

                <div class="flex">
                    <div class="flex-1">
                    <flux:heading size="sm">{{ $bookedSlot->booking->user->name }} 
                    </flux:heading>
                    <flux:badge size="sm">Buchung {{ $bookedSlot->booking->id }}</flux:badge>
                    @if($bookedSlot->checked_in_at !== null)
                        <flux:badge icon="check" size="sm">{{ $bookedSlot->checked_in_at->format('d.m.Y | H:i')}}</flux:badge>
                    @else
                        @can('checkin', $this->showSlot)
                        <flux:button size="xs" wire:click="checkInCourseBookingSlot({{ $bookedSlot }})">Check In</flux:button>
                        @endcan
                    @endif
                    </div>
                </div>
        </div>


        @endforeach

    </div>
</flux:modal>

