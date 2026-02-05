<?php

use Livewire\Volt\Component;
use App\Models\Course\SportType;
use App\Models\Course\Course;

new class extends Component {
    public $sportTypes;
    public $editingId = null;
    
    public $name = '';
    public $description = '';

    public function mount()
    {
        $this->loadSportTypes();
    }

    public function loadSportTypes()
    {
        $this->sportTypes = SportType::all();
    }

    public function create()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:sport_types,name',
            'description' => 'nullable|string',
        ]);

        SportType::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->reset(['name', 'description']);
        $this->loadSportTypes();
        Flux::modal('sport-form')->close();
    }

    public function edit($id)
    {
        $sportType = SportType::find($id);
        $this->editingId = $id;
        $this->name = $sportType->name;
        $this->description = $sportType->description;
        Flux::modal('sport-form')->show();
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:sport_types,name,' . $this->editingId,
            'description' => 'nullable|string',
        ]);

        $sportType = SportType::find($this->editingId);
        $sportType->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->reset(['name', 'description', 'editingId']);
        $this->loadSportTypes();
        Flux::modal('sport-form')->close();
    }

    public function delete($id)
    {
        SportType::find($id)->delete();
        $this->loadSportTypes();
    }

    public function cancel()
    {
        $this->reset(['name', 'description', 'editingId']);
        Flux::modal('sport-form')->close();
    }
};
?>

<section class="w-full">
    @include('partials.courses-heading')
    <x-courses.layout :heading="__('Sportarten')" :subheading="__('Verwalte deine Sportarten')">
        <div class="space-y-6">
            @can('create', Course::class)
            <div class="flex justify-end">
                <flux:modal.trigger name="sport-form">
                    <flux:button icon="plus">Neue Sportart</flux:button>
                </flux:modal.trigger>
            </div>
            @endcan
            <div class="grid gap-4">
                @forelse($sportTypes as $sport)
                <div class="border rounded-lg p-4 bg-white shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="font-semibold text-lg">{{ $sport->name }}</h3>
                            @if($sport->description)
                            <p class="text-sm text-gray-600 mt-1">{{ $sport->description }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            @can('courses.update')
                            <flux:button size="sm" wire:click="edit({{ $sport->id }})">
                                Bearbeiten
                            </flux:button>
                            @endcan
                            @can('courses.delete')
                            <flux:button size="sm" variant="danger" 
                                wire:click="delete({{ $sport->id }})" 
                                onclick="return confirm('Wirklich löschen?')">
                                Löschen
                            </flux:button>
                            @endcan
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    <p>Keine Sportarten vorhanden.</p>
                </div>
                @endforelse
            </div>
        </div>
    </x-courses.layout>

    <flux:modal name="sport-form" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Sportart bearbeiten' : 'Neue Sportart erstellen' }}</flux:heading>
            </div>
            <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}" class="space-y-4">
                <flux:input 
                    label="Name" 
                    wire:model="name" 
                    placeholder="z.B. Yoga"
                />
                
                <flux:textarea 
                    label="Beschreibung" 
                    wire:model="description" 
                    placeholder="Beschreibung der Sportart"
                    rows="3"
                />

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="cancel">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $editingId ? 'Aktualisieren' : 'Erstellen' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
