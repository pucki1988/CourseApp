<?php

use Livewire\Volt\Component;
use App\Services\Course\CoachService;
use App\Models\Course\Coach;

new class extends Component {
    public Coach $coach;
    public $name;
    public $active;
    public $userId;
    public $users;

    public function mount(Coach $coach)
    {
        $this->coach = $coach;
        $this->name = $coach->name;
        $this->active = $coach->active;
        $this->userId = $coach->user_id;
        $this->loadUsers();
    }

    public function loadUsers()
    {
        $this->users = \App\Models\User::all();
    }

    public function save(CoachService $service)
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'active' => 'boolean',
            'userId' => 'nullable|exists:users,id',
        ]);

        $service->update($this->coach, [
            'name' => $this->name,
            'active' => $this->active,
            'user_id' => $this->userId ?: null,
        ]);

        session()->flash('message', 'Trainer erfolgreich aktualisiert');
        
        return redirect()->route('course_management.coaches.index');
    }

    public function cancel()
    {
        return redirect()->route('course_management.coaches.index');
    }
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="'Trainer bearbeiten: ' . $coach->name" :subheading="__('Bearbeite die Trainer-Daten und Vergütungsmodell')">
        <div class="space-y-6">
            <!-- Back Button -->
            <div>
                <flux:button wire:click="cancel" variant="ghost" icon="arrow-left">
                    Zurück zur Übersicht
                </flux:button>
            </div>

            @if (session()->has('message'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('message') }}
                </div>
            @endif

            <!-- Coach Basic Data -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Grunddaten</h3>
                
                <form wire:submit.prevent="save" class="space-y-4">
                    <flux:input 
                        label="Name" 
                        placeholder="Name des Trainers" 
                        type="text" 
                        wire:model="name" 
                    />

                    <flux:field>
                        <flux:label>User zuweisen</flux:label>
                        <flux:select wire:model="userId" placeholder="Wähle einen User oder lasse leer">
                            <flux:select.option value="">Keinen User</flux:select.option>
                            @foreach($users as $user)
                                <flux:select.option value="{{ $user->id }}">
                                    {{ $user->name }} ({{ $user->email }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:checkbox wire:model="active" />
                        <flux:label>Aktiv</flux:label>
                    </flux:field>

                    <div class="flex gap-2 pt-4">
                        <flux:button type="submit" variant="primary">
                            Änderungen speichern
                        </flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancel">
                            Abbrechen
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Compensation Tiers -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Vergütungsmodell</h3>
                @livewire('course_management.coaches.manage-compensation-tiers', ['coach' => $coach], key('comp-tiers-edit-'.$coach->id))
            </div>
        </div>
    </x-courses.layout>
</section>
