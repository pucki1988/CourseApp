<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseBookingSlotService;
use App\Models\Course\Coach;


new class extends Component {

    public $slots;

    public function mount(CourseBookingSlotService $courseBookingSlotService)
    {
       $this->loadSlots($courseBookingSlotService);
    }

    public function loadSlots(CourseBookingSlotService $service)
    {
        $this->slots = $service->loadSettlements();
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Abrechnung')" :subheading="__('Abrechnung')">

     <div class=" grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach($slots as $slot)
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Kurs</span>
                                <span>{{ $slot->course->title }}</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Termin</span>
                                <span>{{ $slot->date->format("d.m.Y")  }} | {{  $slot->start_time->format("H:i") }} </span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Teilnehmende</span>
                                <span>{{ $slot->bookings_count }} </span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Min / Max</span>
                                <span>{{ $slot->min_participants }} / {{ $slot->course->capacity }}</span>
                            </div>
                            @role(['admin', 'manager'])
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Einnamen</span>
                                <span>{{ $slot->revenue }} €</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Zahlungsgebühren (ca.)</span>
                                <span>{{ $slot->bookings_count * 0.5 }} €</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Gesamt</span>
                                <span>{{ $slot->revenue - ($slot->bookings_count * 0.5) }} €</span>
                            </div>
                            @endrole
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Eingecheckt</span>
                                <span>{{ $slot->checked_in_users }}</span>
                            </div>
                             <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Zahlung an Trainer</span>
                                @if($slot->bookings_count >= $slot->min_participants)
                                <span>{{ ($slot->min_participants * ($slot->price - $slot->course->member_discount)) + ((($slot->bookings_count - $slot->min_participants) /2) * ($slot->price -$slot->course->member_discount))  }} €</span>
                                @else
                                 <span>{{ ($slot->bookings_count * ($slot->price - $slot->course->member_discount))  }} €</span>
                                @endif
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Trainer</span>
                                <span>{{ $slot->course?->coach?->name }} </span>
                            </div>
                            
                        </div>
            </div>
            @endforeach
</div>
    </x-courses.layout>
</section>