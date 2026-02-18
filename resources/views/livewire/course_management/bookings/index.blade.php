<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Services\Course\CourseBookingService;

new class extends Component {

    use WithPagination;

    public string $statusFilter = ''; // '' = alle
    public ?int $bookingId =null;
    public string $username='';
    public array $allowedStatuses = ['pending','paid','partially_refunded','refunded'];
    public int $perPage = 10;

    public function updatedBookingId()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedUsername()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function getBookingsProperty()
    {
        $filters = array_filter([
        'bookingId' => $this->bookingId !== null ? $this->bookingId : null,
        'status'    => $this->statusFilter,
        'username'  => $this->username,
        ], fn ($value) => $value !== null);

        return app(CourseBookingService::class)->listBookings($filters, $this->perPage);
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Buchungen')" :subheading="__('Deine Buchungen')">
        
    <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">

        <!-- Suche -->
            <flux:field class="mb-4">
            <flux:label>Buchungsnummer</flux:label>
            <flux:input
            wire:model.live.debounce.300ms="bookingId"
            placeholder="Suche nach Buchung…"
            icon="magnifying-glass"
            type="number"
            />
        </flux:field>

        <flux:field class="mb-4">
            <flux:label>Name</flux:label>
            <flux:input
            wire:model.live.debounce.300ms="username"
            placeholder="Suche nach Name…"
            icon="magnifying-glass"
            />
        </flux:field>

        <flux:field class="mb-4">
            <flux:label>Status</flux:label>
            <flux:select wire:model.live="statusFilter" placeholder="Status wählen">
                <flux:select.option value="">Alle</flux:select.option>
                @foreach($allowedStatuses as $status)
                    <flux:select.option :value="$status">
                        {{ ucfirst($status) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field class="mb-4">
            <flux:label>Pro Seite</flux:label>
            <flux:select wire:model.live="perPage">
                <flux:select.option value="10">10</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
            </flux:select>
        </flux:field>
        
        
       
        

        

    </div>
    @php($bookings = $this->bookings)
    <div class=" grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @forelse($bookings as $booking)
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Buchungsnummer</span>
                                <span>{{ $booking->id }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Name</span>
                                <span>{{  $booking->user->name  }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Betrag</span>
                                <span>€ {{  $booking->total_price }}</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Status</span>
                                <span><flux:badge size="sm" color="{{ $booking->status == 'paid' ? 'green' : ($booking->status == 'pending' ? 'red' : 'gray') }}">{{ $booking->status }}</flux:badge></span>
                            </div>
                             @can('coursebookings.update')
                            <div class="flex justify-center mt-1">
                               
                                <span><flux:button size="xs" href="{{ route('course_management.bookings.show', $booking) }}">Details</flux:button></span>
                                
                            </div>
                            @endcan
                        </div>
            </div>
            @empty
                <flux:text>Keine Buchungen gefunden</flux:text>
            @endforelse
            </div>
        

    <!-- Pagination -->
    <div class="mt-4">
        {{ $bookings->links() }}
    </div>    
        
    </x-courses.layout>
</section>





