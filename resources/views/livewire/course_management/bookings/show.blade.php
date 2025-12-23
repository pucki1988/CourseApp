<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Services\Course\CourseBookingService;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseSlot;
use Carbon\Carbon;
use App\Models\User;


new class extends Component {

   public CourseBooking $booking;
   
    public function mount(CourseBookingService $service,CourseBooking $booking)
    {   
       

        $this->booking = $booking;

        

        
        #$this->loadCourse($service);
        

       
    }

    

    
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Buchung')"   :subheading="__('Deine Kurse')">
    
            
            <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            
            <div class="grid auto-rows-min gap-4 xl:grid-cols-4 mb-3">
            <div>
            <flux:heading size="sm">Buchungsnummer</flux:heading>
            <flux:badge>{{ $booking->id }}</flux:badge>
            </div>
            <div>
            <flux:heading size="sm">Datum</flux:heading>
            <flux:badge>{{ $booking->created_at->format('d.m.Y H:i') }}</flux:badge>
            </div>
            <div>
            <flux:heading size="sm">Status</flux:heading>
            <flux:badge color="{{ $booking->status == 'paid' ? 'green' : ($booking->status == 'pending' ? 'red' : 'gray') }}">{{ $booking->status }}</flux:badge>
            </div>
            <div>
            <flux:heading size="sm">Zahlungsstatus</flux:heading>
            <flux:badge color="{{ $booking->payment_status == 'paid' ? 'green' : ($booking->status == 'pending' ? 'gray' : 'red') }}">{{ $booking->payment_status }}</flux:badge>
            </div>
            </div>
            <flux:separator />
            <flux:heading size="lg">Benutzer</flux:heading>
            <div class="grid auto-rows-min gap-4 xl:grid-cols-4 mb-3">
            <div>
            <flux:heading size="sm" class="mt-1">Name</flux:heading>
            <flux:badge>{{ $booking->user->name }}</flux:badge>
            </div>
            <div>
            <flux:heading size="sm" class="mt-3">E-Mail</flux:heading>
            <flux:badge>{{ $booking->user->email }}</flux:badge>
            </div>
            </div>
            <flux:separator />

            @if($booking->bookingSlots->count() > 0)
            <flux:heading size="lg" class="mt-3">Gebuchte Termine</flux:heading>
            <table class="min-w-full divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">#</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Datum</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Betrag</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody class=" divide-gray-100">
            @foreach($booking->bookingSlots as $index => $bookingSlot)
                <tr>
                <td class="px-4 py-2">{{ ($index+1) }}</td>
                <td class="px-4 py-2">{{ $bookingSlot->slot->date->format('d.m.Y') }} | {{ $bookingSlot->slot->start_time->format('H:i') }}</td>
                <td class="px-4 py-2">€ {{ $bookingSlot->price }}</td>
                <td class="px-4 py-2"><flux:badge color="{{ $bookingSlot->status == 'booked' ? 'green' : ($bookingSlot->status == 'canceled' ? 'red' : 'gray') }}">{{ $bookingSlot->status }}</flux:badge></td>
                </tr>
            @endforeach
            </tbody>
            </table>
            @endif

            

            @if($booking->refunds->count() > 0)
            <flux:separator />
            <flux:heading size="lg">Erstattungen</flux:heading>
            <table class="min-w-full divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Nr</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Betrag</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody class=" divide-gray-100">
            @foreach($booking->refunds as $index => $refund)
                <tr>
                <td class="px-4 py-2">{{ ($index + 1) }}</td>
                <td class="px-4 py-2">{{ $refund->amount }}</td>
                <td class="px-4 py-2"><flux:badge color="{{ $refund->status == 'completed' ? 'green' : ($refund->status == 'canceled' ? 'red' : 'gray') }}">{{ $refund->status }}</flux:badge></td>
                
                </tr>
            @endforeach
            </tbody>
            </table>
            @endif
            </div>

             
        
       
            
            
        
        
    
    </x-courses.layout>
</section>