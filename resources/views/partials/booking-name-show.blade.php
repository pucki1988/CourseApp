<flux:modal name="bookings" class="md:w-96" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Buchungen</flux:heading>
        </div>
        @foreach (($this->showSlot->bookedSlots) ?? [] as $index => $bookedSlot)
        <flux:tooltip content="Zur Buchung ({{ $bookedSlot->booking->id }})"><flux:button variant="ghost"  icon:trailing="information-circle" href="{{ route('course_management.bookings.show', $bookedSlot->booking) }}">{{ $bookedSlot->booking->user->name }}</flux:button>
        </flux:tooltip>
        @endforeach

    </div>
</flux:modal>

