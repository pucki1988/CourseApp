<flux:modal name="bookings" class="md:w-96" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Buchungen</flux:heading>
        </div>
        @foreach (($this->showSlot->bookings) ?? [] as $index => $booking)
        <flux:text><flux:badge>{{ ($index + 1) }}</flux:badge>{{ $booking->user->name }}
        @foreach($booking->slots as $slot)
            <flux:badge size="sm">{{ $slot->pivot->status }}</flux:badge>
        @endforeach
        </flux:text>
        @endforeach

    </div>
</flux:modal>