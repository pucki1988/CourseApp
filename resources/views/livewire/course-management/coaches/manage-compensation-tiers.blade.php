<?php

use Livewire\Volt\Component;
use App\Models\Course\Coach;
use App\Models\Course\CoachCompensationTier;

new class extends Component {
    public Coach $coach;
    public $tiers = [];
    public $showModal = false;
    
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
        $this->showModal = true;
    }

    public function openEditModal($tierId)
    {
        $tier = CoachCompensationTier::findOrFail($tierId);
        
        $this->tierIdBeingEdited = $tier->id;
        $this->minParticipants = $tier->min_participants;
        $this->maxParticipants = $tier->max_participants;
        $this->compensation = $tier->compensation;
        
        $this->showModal = true;
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
        $this->showModal = false;
        $this->reset(['tierIdBeingEdited', 'minParticipants', 'maxParticipants', 'compensation']);
        $this->resetValidation();
    }
};
?>

<div class="space-y-4">
    <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold">Vergütungsmodell für {{ $coach->name }}</h3>
        <button wire:click="openCreateModal" 
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            + Neue Stufe
        </button>
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
                                <button wire:click="openEditModal({{ $tier['id'] }})" 
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                    Bearbeiten
                                </button>
                                <button wire:click="deleteTier({{ $tier['id'] }})" 
                                        onclick="return confirm('Wirklich löschen?')"
                                        class="text-red-600 hover:text-red-900">
                                    Löschen
                                </button>
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

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold mb-4">
                    {{ $tierIdBeingEdited ? 'Vergütungsstufe bearbeiten' : 'Neue Vergütungsstufe' }}
                </h3>

                <form wire:submit.prevent="saveTier" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Minimale Teilnehmerzahl
                        </label>
                        <input type="number" 
                               wire:model="minParticipants" 
                               class="w-full border border-gray-300 rounded px-3 py-2"
                               min="1">
                        @error('minParticipants') 
                            <span class="text-red-500 text-sm">{{ $message }}</span> 
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Maximale Teilnehmerzahl
                        </label>
                        <input type="number" 
                               wire:model="maxParticipants" 
                               class="w-full border border-gray-300 rounded px-3 py-2"
                               min="1">
                        @error('maxParticipants') 
                            <span class="text-red-500 text-sm">{{ $message }}</span> 
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Vergütung (€)
                        </label>
                        <input type="number" 
                               wire:model="compensation" 
                               step="0.01"
                               class="w-full border border-gray-300 rounded px-3 py-2"
                               min="0">
                        @error('compensation') 
                            <span class="text-red-500 text-sm">{{ $message }}</span> 
                        @enderror
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" 
                                wire:click="closeModal"
                                class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                            Abbrechen
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
