
<?php

use Livewire\Volt\Component;
use App\Services\Course\CourseSlotService;
use App\Models\Course\Course;
use App\Models\User;

new class extends Component {

    public $slots;
    public $search = '';
    public $coachId = null;
    public $perPage = 10;

    public function mount(CourseSlotService $service)
    {
        $this->authorize('viewAny', Course::class);
        $this->loadSlots($service);
    }


    public function loadSlots(CourseSlotService $service)
    {
        $filters = [
            'limit' => 3
        ];
        $this->slots = $service->listSlots($filters);
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout  :subheading="__('Deine Kurse')">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            @foreach($slot as $slots)
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">

                <div>
                    <h3 class="font-bold text-lg">{{ $slot->title }}</h3>

                    <p class="text-sm text-neutral-600 dark:text-neutral-300 mt-1">
                        📅 {{ $slot->start->format('d.m.Y') }}
                    </p>

                    <p class="text-sm text-neutral-600 dark:text-neutral-300">
                        ⏰ {{ $slot->start->format('H:i') }} – {{ $slot->end->format('H:i') }}
                    </p>
                </div>

                <div class="mt-4">
                    <span class="inline-flex items-center text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        👍 Zusagen: {{ $slot->attendees()->count() }}
                    </span>
                </div>

            </div>
        @empty
            <div class="col-span-3 text-neutral-500">
                Keine Slots vorhanden.
            </div>
        @endforelse
        </div>
        
        </div>

    
        
    </x-courses.layout>
</section>
