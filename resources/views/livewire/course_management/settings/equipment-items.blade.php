<?php

use Livewire\Volt\Component;
use App\Models\Course\EquipmentItem;
use App\Models\Course\Course;

new class extends Component {
    public $equipmentItems;
    public $editingId = null;
    
    public $name = '';
    public $description = '';

    public function mount()
    {
        $this->loadEquipmentItems();
    }

    public function loadEquipmentItems()
    {
        $this->equipmentItems = EquipmentItem::all();
    }

    public function create()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:equipment_items,name',
            'description' => 'nullable|string',
        ]);

        EquipmentItem::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->reset(['name', 'description']);
        $this->loadEquipmentItems();
        Flux::modal('equipment-form')->close();
    }

    public function edit($id)
    {
        $equipment = EquipmentItem::find($id);
        $this->editingId = $id;
        $this->name = $equipment->name;
        $this->description = $equipment->description;
        Flux::modal('equipment-form')->show();
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:equipment_items,name,' . $this->editingId,
            'description' => 'nullable|string',
        ]);

        $equipment = EquipmentItem::find($this->editingId);
        $equipment->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->reset(['name', 'description', 'editingId']);
        $this->loadEquipmentItems();
        Flux::modal('equipment-form')->close();
    }

    public function delete($id)
    {
        EquipmentItem::find($id)->delete();
        $this->loadEquipmentItems();
    }

    public function cancel()
    {
        $this->reset(['name', 'description', 'editingId']);
        Flux::modal('equipment-form')->close();
    }
};
?>

<section class="w-full">
    @include('partials.courses-heading')
    <x-courses.layout :heading="__('Ausrüstung')" :subheading="__('Verwalte deine Ausrüstung')">
        <div class="space-y-6">
            @can('create', Course::class)
            <div class="flex justify-end">
                <flux:modal.trigger name="equipment-form">
                    <flux:button icon="plus">Neue Ausrüstung</flux:button>
                </flux:modal.trigger>
            </div>
            @endcan
            <div class="grid gap-4">
                @forelse($equipmentItems as $equipment)
                <div class="border rounded-lg p-4 bg-white shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="font-semibold text-lg">{{ $equipment->name }}</h3>
                            @if($equipment->description)
                            <p class="text-sm text-gray-600 mt-1">{{ $equipment->description }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            @can('update', Course::class)
                            <flux:button size="sm" wire:click="edit({{ $equipment->id }})">
                                Bearbeiten
                            </flux:button>
                            @endcan
                            @can('delete', Course::class)
                            <flux:button size="sm" variant="danger" 
                                wire:click="delete({{ $equipment->id }})" 
                                onclick="return confirm('Wirklich löschen?')">
                                Löschen
                            </flux:button>
                            @endcan
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    <p>Keine Ausrüstung vorhanden.</p>
                </div>
                @endforelse
            </div>
        </div>
    </x-courses.layout>

    <flux:modal name="equipment-form" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ausrüstung bearbeiten' : 'Neue Ausrüstung erstellen' }}</flux:heading>
            </div>
            <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}" class="space-y-4">
                <flux:input 
                    label="Name" 
                    wire:model="name" 
                    placeholder="z.B. Yoga-Matte"
                />
                
                <flux:textarea 
                    label="Beschreibung" 
                    wire:model="description" 
                    placeholder="Beschreibung der Ausrüstung"
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
