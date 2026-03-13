
<?php

use App\Models\Course\Course;
use Livewire\Volt\Component;
use App\Services\Course\CourseBookingSlotService;
use App\Services\Course\CourseSlotService;
use App\Models\Course\CourseBookingSlot;
use App\Models\Course\CourseSlot;
use App\Actions\Course\CancelCourseSlotAction;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

new class extends Component {

    public $slots;
    public $search = '';
    public $coachId = null;
    public $perPage = 10;
    public bool $showCallout = false;
    public string $calloutMessage = '';
    public string $calloutHeading = 'Erfolg';

    public ?CourseSlot $activeSlot = null;

    public bool $scanLocked = false;

    public string $scanValue = '';
    public ?string $message = null;
    public string $state = 'idle'; // idle, success, error

    
    public ?CourseSlot $slotToCancel = null;
    public string $cancelReason = "";
    public array $slotToReschedule = [];
    public ?CourseSlot $showSlot=null;
    

    public function mount(CourseBookingSlotService $courseBookingSlotService)
    {
        #$this->authorize('viewAny', CourseSlot::class);
        $this->loadSlots($courseBookingSlotService);
    }

    public function loadSlots(CourseBookingSlotService $service)
    {
        $this->slots = $service->listBookedSlots();
    }

    
    
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Deine nächsten Termine')" :subheading="__('Deine Kurse')">
        
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
            @forelse($slots ?? [] as $slot)
            <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">

                <div class="flex">
                    <div class="flex-1">
                    <flux:heading size="lg">{{ $slot->slot->course->title }} 
                    </flux:heading>

                    <div class="mb-2">
                    <flux:tooltip content="Status des Termins">
                    <flux:badge size="sm">{{ $slot->slot->status }} </flux:badge>
                    </flux:tooltip>
                    @if($slot->slot->rescheduled_at !== null)
                    <flux:tooltip content="Wurde am {{ $slot->slot->rescheduled_at->format('d.m.Y') }} auf diesen Termin verschoben">
                    <flux:badge size="sm">verschoben</flux:badge>
                    </flux:tooltip>
                    @endif
                    </div>
                    
                    <flux:text class="mt-2">
                    <flux:badge icon="calendar">{{ $slot->slot->date->format('d.m.Y') }}</flux:badge> 
                    <flux:badge class="ms-1" icon="clock">{{ $slot->slot->start_time->format('H:i') }} – {{ $slot->slot->end_time->format('H:i') }}</flux:badge> 
                    </flux:text>
                    <flux:text class="mt-2">
                    Coach<flux:badge>
                    @if($slot->slot->course?->coach === null)
                    nicht festgelegt
                    @else
                    {{ $slot->slot->course?->coach?->name }}
                    @endif
                    </flux:badge>
                    </flux:text>
                    
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-neutral-500">
                Du hast noch keine Termine gebucht. Schau doch mal in unserem Kursangebot vorbei und melde dich an!
            </div>
        @endforelse
        </div>
        
        </div>

        
    </x-courses.layout>
</section>