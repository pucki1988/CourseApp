<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseBookingService;
use App\Models\Course\CourseBooking;
use App\Models\User;

new class extends Component {

    public $bookings;
    public string $statusFilter = ''; // '' = alle
    public array $allowedStatuses = ['pending','paid','partially_refunded','refunded'];
    public $perPage = 10;

    public function mount(CourseBookingService $service)
    {
        $this->authorize('viewAny', CourseBooking::class);
        $this->loadBookings($service);
    }

    /*public function updated($property, CourseBookingService $service)
    {
        $this->loadCloadBookingsourses($service);
    }*/

    public function updatedStatusFilter($property, CourseBookingService $service)
    {
        
            $filters = [
                'status' => $this->statusFilter
            ];
             $this->bookings=$service->listBookings($filters);
        
    }

    public function loadBookings(CourseBookingService $service)
    {
        $this->bookings = $service->listBookings();
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Buchungen')" :subheading="__('Deine Buchungen')">
        
    <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

        <!-- Suche -->

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
        
        
       
        

        

    </div>

    <!-- COURSES LIST -->
    <table class="min-w-full divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Nr</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Gebucht</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Kurs</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Aktionen</th>
            </tr>
        </thead>
        <tbody class=" divide-gray-100">
            @forelse($bookings as $booking)
            <tr>
                <td class="px-4 py-2">{{ $booking->id }}</td>
                <td class="px-4 py-2">{{ date_format($booking->created_at,'d.m.Y') }}</td>
                <td class="px-4 py-2">{{ $booking->course->title }}</td>
                <td class="px-4 py-2"><flux:badge color="{{ $booking->status == 'paid' ? 'green' : ($booking->status == 'pending' ? 'red' : 'gray') }}">{{ $booking->status }}</flux:badge></td>
                <td class="px-4 py-2 text-right">
                    
                <flux:button size="xs" href="{{ route('course_management.bookings.show', $booking) }}">Details</flux:button>
                    
                </td>
            </tr>
            @empty
                <flux:text>Keine Buchungen gefunden</flux:text>
            @endforelse
            
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="mt-4">
        
    </div>    
        
    </x-courses.layout>
</section>





