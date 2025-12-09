<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseBookingService;
use App\Models\Course\CourseBooking;
use App\Models\User;

new class extends Component {

    public $bookings;
    public $search = '';
    public $coachId = null;
    public $perPage = 10;

    public function mount(CourseBookingService $service)
    {
        $this->authorize('viewAny', CourseBooking::class);
        $this->loadBookings($service);
    }

    public function updated($property, CourseBookingService $service)
    {
        $this->loadCloadBookingsourses($service);
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
        <tbody class="divide-y divide-gray-100">
            @foreach($bookings as $booking)
            <tr>
                <td class="px-4 py-2">{{ $booking->id }}</td>
                <td class="px-4 py-2">{{ date_format($booking->created_at,'d.m.Y') }}</td>
                <td class="px-4 py-2">{{ $booking->course->title }}</td>
                <td class="px-4 py-2"><flux:badge color="green">{{ $booking->status }}</flux:badge></td>
                <td class="px-4 py-2 text-right">
                    
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="mt-4">
        
    </div>    
        
    </x-courses.layout>
</section>





