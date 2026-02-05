<?php

use Livewire\Volt\Component;
use App\Services\Course\CoachService;
use App\Models\Course\Coach;
use App\Models\Course\Course;

new class extends Component {

    
    public $coaches;
    public $users;
    public $editingId = null;

   public array $newCoach;

    public function mount(CoachService $coachService)
    {
        #$this->authorize('viewAny', Course::class);
        $this->initializeNewCoach();
        $this->loadUsers();

        $this->loadCoaches($coachService);
    }

    public function loadUsers()
    {
        $this->users = \App\Models\User::all();
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
        // Konvertiere leeren String zu null
        if (empty($this->newCoach['user_id'])) {
            $this->newCoach['user_id'] = null;
        }
        $service->store($this->newCoach);
        
        // Modal schließen
        Flux::modal('coach')->close();
        $this->initializeNewCoach();

         $this->loadCoaches($service);
    }

    public function edit(Coach $coach)
    {
        $this->editingId = $coach->id;
        $this->newCoach = [
            'name' => $coach->name,
            'active' => $coach->active,
            'user_id' => $coach->user_id
        ];
        Flux::modal('coach')->show();
    }

    public function updateCoach(CoachService $service)
    {
        // Konvertiere leeren String zu null
        if (empty($this->newCoach['user_id'])) {
            $this->newCoach['user_id'] = null;
        }
        $coach = Coach::find($this->editingId);
        $service->update($coach, $this->newCoach);
        
        Flux::modal('coach')->close();
        $this->initializeNewCoach();
        $this->editingId = null;
        $this->loadCoaches($service);
    }
    
    public function deleteCoach(CoachService $service, $coachId)
    {
        $coach = Coach::find($coachId);
        if ($coach) {
            $service->delete($coach);
        }

        $this->loadCoaches($service);
    }

    public function cancel()
    {
        $this->initializeNewCoach();
        $this->editingId = null;
        Flux::modal('coach')->close();
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Trainer')" :subheading="__('Verwalte deine Trainer')">
        <div class="space-y-6">
            @can('create', Course::class)
            <div class="flex justify-end">
                <flux:modal.trigger name="coach">
                    <flux:button icon="plus">Neuen Trainer</flux:button>
                </flux:modal.trigger>
            </div>
            @endcan
            <div class="grid gap-4">
                @forelse($coaches as $coach)
                <div class="border rounded-lg p-4 bg-white shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="font-semibold text-lg">{{ $coach->name }}</h3>
                            <div class="mt-2 space-y-2">
                                <flux:badge color="{{ $coach->active ? 'green' : 'red' }}">
                                    {{ $coach->active ? 'Aktiv' : 'Inaktiv' }}
                                </flux:badge>
                                @if($coach->user_id)
                                    <div class="text-sm text-gray-600">
                                        <span class="font-medium">User:</span> {{ $coach->user?->name ?? 'N/A' }}
                                    </div>
                                @else
                                    <div class="text-sm text-gray-400">
                                        Kein User zugewiesen
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="edit({{ $coach->id }})">
                                Bearbeiten
                            </flux:button>
                            <flux:button size="sm" variant="danger" 
                                wire:click="deleteCoach({{ $coach->id }})" 
                                onclick="return confirm('Wirklich löschen?')">
                                Löschen
                            </flux:button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    <p>Keine Trainer vorhanden.</p>
                </div>
                @endforelse
            </div>
        </div>
    </x-courses.layout>

    
    <flux:modal name="coach" :dismissible="false" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Trainer bearbeiten' : 'Neuen Trainer erstellen' }}</flux:heading>
                <flux:text class="mt-2"></flux:text>
            </div>
            <form wire:submit.prevent="{{ $editingId ? 'updateCoach' : 'createCoach' }}" class="space-y-4">
            <flux:input label="Name" placeholder="Name des Trainers" type="text" wire:model="newCoach.name" />
            <flux:field>
                <flux:label>User zuweisen</flux:label>
                <flux:select wire:model="newCoach.user_id" placeholder="Wähle einen User oder lasse leer">
                    <flux:select.option value="">Keinen User</flux:select.option>
                    @foreach($users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field variant="inline">
                <flux:checkbox wire:model="newCoach.active" />
                <flux:label>Aktiv</flux:label>
            </flux:field>
           
            <div class="flex gap-2">
                <flux:spacer />
                <flux:button type="button" variant="ghost" wire:click="cancel">Abbrechen</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Trainer aktualisieren' : 'Trainer erstellen' }}</flux:button>
            </div>
            </form>
        </div>
    </flux:modal>
</section>
</section>