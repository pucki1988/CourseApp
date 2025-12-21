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
    <div class="text-end">
    <flux:dropdown>
        <flux:button icon:trailing="chevron-down" class="mb-3">Optionen</flux:button>
        <flux:menu>
            <flux:modal.trigger name="coach">
                <flux:menu.item icon="plus">Neuen Trainer erstellen</flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>
    </div>    
    @endcan
    
        

    <!-- COACHES LIST -->
    <table class="min-w-full divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Aktiv</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Aktionen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($coaches as $coach)
            <tr>
                <td class="px-4 py-2">{{ $coach->name }}</td>
                <td class="px-4 py-2">
                <flux:badge color="{{ $coach->active?'green':'red' }}">{{ $coach->active?'aktiv':'inaktiv' }}</flux:badge>
                </td>
                <td class="px-4 py-2 text-right">
                    @can('update',Coach::class)
                    <flux:button size="xs" variant="danger" wire:click="deleteCoach({{ $coach }})">Löschen</flux:button>
                    <flux:button size="xs" href="{{ route('course_management.coaches.show', $coach) }}">Details</flux:button>
                    @endcan
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>


    
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