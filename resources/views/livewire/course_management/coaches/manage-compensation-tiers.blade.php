<?php

use Livewire\Volt\Component;
use App\Models\Course\Coach;
use App\Models\Course\CoachCompensationTier;

new class extends Component {
    public Coach $coach;
    public $tiers = [];
    
    // Form fields for new/edit tier
    public $tierIdBeingEdited = null;
    public $minParticipants = '';
    public $maxParticipants = '';
    public $compensation = '';

    protected $messages = [
        'minParticipants.required' => 'Mindestanzahl ist erforderlich',
        'minParticipants.min' => 'Mindestanzahl muss mindestens 1 sein',
        'maxParticipants.required' => 'Maximalanzahl ist erforderlich',
        'maxParticipants.gte' => 'Maximalanzahl muss größer oder gleich Mindestanzahl sein',
        'compensation.required' => 'Vergütung ist erforderlich',
        'compensation.min' => 'Vergütung muss positiv sein',
    ];

    public function mount(Coach $coach)
    {
        $this->coach = $coach;
        $this->loadTiers();
    }

    public function loadTiers()
    {
        $this->tiers = $this->coach->compensationTiers()->get()->toArray();
    }

    public function openCreateModal()
    {
        $this->reset(['tierIdBeingEdited', 'minParticipants', 'maxParticipants', 'compensation']);
        Flux::modal('tier-modal')->show();
    }

    public function openEditModal($tierId)
    {
        $tier = CoachCompensationTier::findOrFail($tierId);
        
        $this->tierIdBeingEdited = $tier->id;
        $this->minParticipants = $tier->min_participants;
        $this->maxParticipants = $tier->max_participants;
        $this->compensation = $tier->compensation;
        
        Flux::modal('tier-modal')->show();
    }

    public function saveTier()
    {
        $this->validate([
            'minParticipants' => 'required|integer|min:1',
            'maxParticipants' => 'required|integer|min:1|gte:minParticipants',
            'compensation' => 'required|numeric|min:0',
        ]);

        if ($this->tierIdBeingEdited) {
            // Update existing
            $tier = CoachCompensationTier::findOrFail($this->tierIdBeingEdited);
            $tier->update([
                'min_participants' => $this->minParticipants,
                'max_participants' => $this->maxParticipants,
                'compensation' => $this->compensation,
            ]);
            
            session()->flash('message', 'Vergütungsstufe aktualisiert');
        } else {
            // Create new
            CoachCompensationTier::create([
                'coach_id' => $this->coach->id,
                'min_participants' => $this->minParticipants,
                'max_participants' => $this->maxParticipants,
                'compensation' => $this->compensation,
                'sort_order' => $this->coach->compensationTiers()->count(),
            ]);
            
            session()->flash('message', 'Vergütungsstufe erstellt');
        }

        $this->loadTiers();
        $this->closeModal();
    }

    public function deleteTier($tierId)
    {
        CoachCompensationTier::findOrFail($tierId)->delete();
        $this->loadTiers();
        session()->flash('message', 'Vergütungsstufe gelöscht');
    }

    public function closeModal()
    {
        Flux::modal('tier-modal')->close();
        $this->reset(['tierIdBeingEdited', 'minParticipants', 'maxParticipants', 'compensation']);
        $this->resetValidation();
    }
};
?>

<div class="space-y-4">
    <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold">Vergütungsmodell für {{ $coach->name }}</h3>
        <flux:modal.trigger name="tier-modal">
            <flux:button icon="plus">Neue Stufe</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
        </div>
    @endif

    @if(count($tiers) > 0)
        <div class="bg-white shadow overflow-hidden rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Teilnehmerzahl
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Vergütung
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Aktionen
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($tiers as $tier)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $tier['min_participants'] }} - {{ $tier['max_participants'] }} Teilnehmer
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ number_format($tier['compensation'], 2, ',', '.') }} €
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <flux:button size="sm" wire:click="openEditModal({{ $tier['id'] }})" variant="ghost">
                                    Bearbeiten
                                </flux:button>
                                <flux:button size="sm" wire:click="deleteTier({{ $tier['id'] }})" 
                                        onclick="return confirm('Wirklich löschen?')"
                                        variant="danger">
                                    Löschen
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-gray-50 border border-gray-200 rounded p-6 text-center text-gray-500">
            Noch keine Vergütungsstufen definiert. Klicken Sie auf "Neue Stufe" um zu beginnen.
        </div>
    @endif

    <!-- Flux Modal -->
    <flux:modal name="tier-modal" :dismissible="false" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $tierIdBeingEdited ? 'Vergütungsstufe bearbeiten' : 'Neue Vergütungsstufe' }}</flux:heading>
            </div>

            <form wire:submit.prevent="saveTier" class="space-y-4">
                <flux:input 
                    label="Minimale Teilnehmerzahl" 
                    type="number" 
                    wire:model="minParticipants" 
                    min="1"
                />

                <flux:input 
                    label="Maximale Teilnehmerzahl" 
                    type="number" 
                    wire:model="maxParticipants" 
                    min="1"
                />

                <flux:input 
                    label="Vergütung (€)" 
                    type="number" 
                    wire:model="compensation" 
                    step="0.01"
                    min="0"
                />

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeModal">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">Speichern</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>