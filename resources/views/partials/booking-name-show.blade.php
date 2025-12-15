<flux:modal name="bookings" class="md:w-96" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Buchungen</flux:heading>
        </div>
        @foreach (($this->showSlot->bookedSlots) ?? [] as $index => $bookedSlot)
        <flux:text><flux:badge>{{ ($index + 1) }}</flux:badge>{{ $bookedSlot->booking->user->name }}
        </flux:text>
        @endforeach

    </div>
</flux:modal>