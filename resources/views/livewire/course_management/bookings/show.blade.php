<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseService;
use App\Services\Course\CourseBookingService;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
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

    
    public function cancelBookingSlot(CourseBooking $courseBooking,CourseBookingSlot $courseBookingSlot)
    {
        $service = app(CourseBookingService::class);   // Service automatisch aus Container holen
        $service->cancelBookingSlot($courseBooking,$courseBookingSlot);
        
    }
    
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Buchung')"   :subheading="__('Deine Kurse')">
    
            
            <div class="grid auto-rows-min gap-4 xl:grid-cols-2 mb-3">
            
            <div>
            <flux:heading size="lg">Buchung</flux:heading>
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                            <div class="text-sm">
                                <div class="flex justify-between mt-1">
                                    <span class="text-gray-500">Buchungsnummer</span>
                                    <span>{{ $booking->id }}</span>
                                </div>

                                <div class="flex justify-between mt-1">
                                    <span class="text-gray-500">Datum</span>
                                    <span>{{ $booking->created_at->format('d.m.Y H:i') }}</span>
                                </div>
                                <div class="flex justify-between mt-1">
                                    <span class="text-gray-500">Buchungsstatus</span>
                                    <span><flux:badge size="sm" color="{{ $booking->status == 'paid' ? 'green' : ($booking->status == 'pending' ? 'red' : 'gray') }}">{{ $booking->status }}</flux:badge></span>
                                </div>
                                <div class="flex justify-between mt-1">
                                    <span class="text-gray-500">Zahlungsstatus</span>
                                    <span><flux:badge size="sm" color="{{ $booking->payment_status == 'paid' ? 'green' : ($booking->status == 'pending' ? 'gray' : 'red') }}">{{ $booking->payment_status }}</flux:badge></span>
                                </div>
                            </div>
                </div>
            </div>
            <div>
            <flux:heading size="lg">Benutzer</flux:heading>
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Name</span>
                                <span>{{ $booking->user->name }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">E-Mail</span>
                                <span>{{ $booking->user->email }}</span>
                            </div>
                            
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Rollen</span>
                                <span>
                                @foreach ($booking->user->getRoleNames() as $role)
                                <flux:badge size="sm">{{ $role }}</flux:badge>
                                @endforeach
                                </span>
                            </div>

                        </div>
                </div>
                
            </div>
            

            
            </div>
            

            @if($booking->bookingSlots->count() > 0)
            <flux:heading size="lg" class="mt-2">Gebuchte Termine</flux:heading>
            <div class="space-y-3 grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
                @foreach ($booking->bookingSlots as $index => $bookingSlot)
                    <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Datum</span>
                                <span>{{ $bookingSlot->slot->date->format('d.m.Y') }} || {{ $bookingSlot->slot->start_time->format('H:i') }}</span>
                            </div>

                            @if($booking->booking_type ==="per_slot")
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Betrag</span>
                                <span>€ {{  $bookingSlot->price }}</span>
                            </div>
                            @endif

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Status</span>
                                <span>
                                    <flux:badge size="sm" color="{{ $bookingSlot->status == 'booked' ? 'green' : ($bookingSlot->status == 'canceled' ? 'red' : 'gray') }}">{{ $bookingSlot->status }}</flux:badge>
                                </span>
                            </div>
                            @if($booking->booking_type==='per_slot' && $bookingSlot->status === 'booked')
                            
                            @can('coursebookingslots.update')
                            @can('coursebookings.update')
                            <div class="flex justify-end mt-2">
                            <flux:dropdown>
                                <flux:button size="sm" icon:trailing="ellipsis-vertical"></flux:button>
                                <flux:menu>
                                    <flux:menu.item icon="x-mark" wire:click="cancelBookingSlot({{ $booking }} ,{{ $bookingSlot }})">stornieren</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                            @endcan
                            @endcan
                            </div>
                            @endif

                        </div>
                    </div>
                @endforeach
                

            </div>
            
            @endif


            @if($booking->refunds->count() > 0)
            <flux:heading size="lg">Erstattungen</flux:heading>
            <div class="space-y-3 grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
                @foreach ($booking->refunds as $index => $refund)
                    <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Datum</span>
                                <span>{{ $refund?->refunded_at?->format('d.m.Y H:i') }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Betrag</span>
                                <span>€ {{ $refund->amount }}</span>
                            </div>
                            

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Status</span>
                                <span><flux:badge size="sm" color="{{ $refund->status == 'completed' ? 'green' : ($refund->status == 'canceled' ? 'red' : 'gray') }}">{{ $refund->status }}</flux:badge></span>
                            </div>
                        </div>
                    </div>
                @endforeach
                

            </div>
            @endif
            <flux:heading size="lg">Zusammenfassung</flux:heading>
            <div class="border rounded-lg p-3 bg-white shadow-sm mt-3">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-900">Buchungen</span>
                                <span class="font-medium">
                                <flux:badge color="green" size="sm">{{ $booking->total_price }} €</flux:badge></span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-900">Erstattungen</span>
                                <span class="font-medium">
                                <flux:badge color="red" size="sm">- {{ number_format($booking->refunds->where('status', 'completed')->sum('amount'), 2, '.', '') }} €</flux:badge>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-900">Gesamt</span>
                                <span class="font-medium">
                                <flux:badge color="green" size="sm">{{ number_format($booking->total_price -$booking->refunds->where('status', 'completed')->sum('amount'), 2, '.', '')}} €</flux:badge></span>
                            </div>
                        </div>
                </div>
            
            
             
        
       
            
            
        
        
    
    </x-courses.layout>
</section>