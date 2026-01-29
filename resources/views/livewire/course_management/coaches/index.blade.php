<?php

use Livewire\Volt\Component;
use App\Services\Course\CoachService;
use App\Models\Course\Coach;
use App\Models\User;

new class extends Component {

    
    public $coaches;
   


   public array $newCoach;

    public function mount(CoachService $coachService)
    {
        #$this->authorize('viewAny', Course::class);
        $this->initializeNewCoach();

        $this->loadCoaches($coachService);
    }

    private function initializeNewCoach(){
        $this->newCoach = [
            'name' => '', // Defaultwert
            'active' => true,
            'user_id' => null
        ];
    }
    public function loadCoaches(CoachService $service)
    {
        $this->coaches = $service->listCoaches();
    }

    public function createCoach(CoachService $service)
    {
        $service->store($this->newCoach);
        
        // Modal schließen
        Flux::modal('coach')->close();
        $this->initializeNewCoach();

         $this->loadCoaches($service);
    }
    
    public function deleteCoach(CoachService $service,Coach $coachToDelete)
    {
        $service->delete($coachToDelete);

        $this->loadCoaches($service);
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Trainer')" :subheading="__('Trainer')">
    @can('create', Coach::class)

    <div class="flex justify-end">
                <flux:modal.trigger name="coach">
                    <flux:button icon="plus">Neuen Trainer </flux:button>
                </flux:modal.trigger>
            </div>
    @endcan

     <div class=" grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach($coaches as $coach)
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Name</span>
                                <span>{{ $coach->name }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Status</span>
                                <span><flux:badge size="sm" color="{{ $coach->active?'green':'red' }}">{{ $coach->active?'aktiv':'inaktiv' }}</flux:badge></span>
                            </div>
                            <div class="flex justify-center mt-1">
                                
                                <span> @can('update',Coach::class)
                    <flux:button size="xs" variant="danger" wire:click="deleteCoach({{ $coach }})">Löschen</flux:button>
                    <flux:button size="xs" href="{{ route('course_management.coaches.show', $coach) }}">Details</flux:button>
                    @endcan</span>
                            </div>
                        </div>
            </div>


            
            @endforeach
</div>


    
    <flux:modal name="coach" :dismissible="false" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Neuen Trainer erstellen</flux:heading>
                <flux:text class="mt-2"></flux:text>
            </div>
            <form wire:submit.prevent="createCoach" class="space-y-4">
            <flux:input label="Name" placeholder="Name des Trainers" type="text" :value="$newCoach['name']"
    wire:change="$set('newCoach.name', $event.target.value)" />
            <flux:field variant="inline">
                <flux:checkbox wire:model="newCoach.active" />
                <flux:label>Aktiv</flux:label>
            </flux:field>
           
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Trainer erstellen</flux:button>
            </div>
            </form>
        </div>
    </flux:modal>    
        
    </x-courses.layout>
</section>