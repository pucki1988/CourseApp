<?php

use Livewire\Volt\Component;
use App\Models\Member\Family;
use App\Models\Member\Member;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;

new class extends Component {

    public Collection $families;
    public Collection $members;

    public ?int $familyId = null;
    public string $name = '';
    public array $selectedMemberIds = [];
    public string $memberSearch = '';

    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->loadFamilies();
        $this->loadMembers();
    }

    private function loadFamilies(): void
    {
        $this->families = Family::with('members')
            ->orderBy('name')
            ->get();
    }

    private function loadMembers(): void
    {
        $this->members = Member::where('deceased_at', null)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function resetForm(): void
    {
        $this->familyId = null;
        $this->name = '';
        $this->selectedMemberIds = [];
        $this->memberSearch = '';
    }

    public function openCreate(): void
    {
        $this->resetForm();
        Flux::modal('createFamily')->show();
    }

    public function openEdit(int $familyId): void
    {
        $family = Family::with('members')->findOrFail($familyId);
        $this->familyId = $family->id;
        $this->name = $family->name;
        $this->selectedMemberIds = $family->members->pluck('id')->toArray();
        $this->memberSearch = '';

        Flux::modal('editFamily')->show();
    }

    public function openDelete(int $familyId): void
    {
        $this->deleteId = $familyId;
        Flux::modal('deleteFamily')->show();
    }

    public function create(): void
    {
        if (trim($this->name) === '') {
            session()->flash('error', 'Name ist erforderlich');
            return;
        }

        if (empty($this->selectedMemberIds)) {
            session()->flash('error', 'Bitte mindestens ein Mitglied auswählen');
            return;
        }

        $family = Family::create([
            'name' => trim($this->name),
        ]);

        foreach ($this->selectedMemberIds as $memberId) {
            $family->members()->attach($memberId, [
                'joined_at' => now()->toDateString(),
            ]);
        }

        $this->loadFamilies();
        Flux::modal('createFamily')->close();
    }

    public function update(): void
    {
        if (!$this->familyId) {
            return;
        }

        if (trim($this->name) === '') {
            session()->flash('error', 'Name ist erforderlich');
            return;
        }

        if (empty($this->selectedMemberIds)) {
            session()->flash('error', 'Bitte mindestens ein Mitglied auswählen');
            return;
        }

        $family = Family::with('members')->findOrFail($this->familyId);
        $family->update([
            'name' => trim($this->name),
        ]);

        $currentMemberIds = $family->members->pluck('id')->toArray();
        $toRemove = array_diff($currentMemberIds, $this->selectedMemberIds);
        $toAdd = array_diff($this->selectedMemberIds, $currentMemberIds);

        // Entfernte Mitglieder: left_at setzen (History behalten)
        foreach ($toRemove as $memberId) {
            $family->members()->updateExistingPivot($memberId, [
                'left_at' => now()->toDateString(),
            ]);
        }

        // Neue Mitglieder: Prüfen ob inaktiver Datensatz existiert und reaktivieren
        foreach ($toAdd as $memberId) {
            $existingPivot = \DB::table('family_member')
                ->where('family_id', $family->id)
                ->where('member_id', $memberId)
                ->first();

            if ($existingPivot) {
                // Reaktivieren: left_at auf null setzen
                \DB::table('family_member')
                    ->where('family_id', $family->id)
                    ->where('member_id', $memberId)
                    ->update([
                        'left_at' => null,
                        'joined_at' => now()->toDateString(),
                        'updated_at' => now(),
                    ]);
            } else {
                // Neu hinzufügen
                $family->members()->attach($memberId, [
                    'joined_at' => now()->toDateString(),
                ]);
            }
        }

        $this->loadFamilies();
        Flux::modal('editFamily')->close();
    }

    public function delete(): void
    {
        if (!$this->deleteId) {
            return;
        }

        $family = Family::findOrFail($this->deleteId);
        $family->delete();

        $this->loadFamilies();
        Flux::modal('deleteFamily')->close();
    }

}; ?>

<section>
    @include('partials.members-heading')

    @if (session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
            {{ session('warning') }}
        </div>
    @endif

    <x-members.layout :heading="__('Familien')" :subheading="__('Übersicht')">
        <div class="flex md:justify-end mb-3">
            <flux:button icon="plus" wire:click="openCreate">Neue Familie</flux:button>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach ($families as $family)
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                    <div class="text-sm">
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Name</span>
                            <span class="font-semibold">{{ $family->name }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Mitglieder</span>
                            <span>{{ $family->members->count() }}</span>
                        </div>
                        <div class="mt-2 text-xs text-gray-500 border-t pt-2">
                            @foreach($family->members as $member)
                                <div>{{ $member->first_name }} {{ $member->last_name }} | {{ $member->birth_date?->format('d.m.Y') ?? '-' }}</div>
                            @endforeach
                        </div>
                        <div class="flex justify-end mt-2 gap-2">
                            <flux:button size="xs" wire:click="openEdit({{ $family->id }})">Bearbeiten</flux:button>
                            <flux:button size="xs" variant="danger" wire:click="openDelete({{ $family->id }})">Löschen</flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-members.layout>

    <flux:modal name="createFamily">
        <flux:heading size="lg">Familie anlegen</flux:heading>
        <flux:text class="mt-2">Bitte Werte eingeben.</flux:text>

        <div class="mt-4 space-y-3">
            <flux:input label="Name" wire:model.live="name" />

            <flux:input label="Mitglieder suchen" wire:model.live="memberSearch" />

            <div class="space-y-2">
                <div class="text-sm font-semibold">Mitglieder auswählen</div>
                <div class="max-h-48 overflow-y-auto border rounded p-2 space-y-2">
                    @php
                        $search = trim(\Illuminate\Support\Str::lower($memberSearch));
                        $filteredMembers = $search === ''
                            ? $members
                            : $members->filter(function ($member) use ($search) {
                                $fullName = \Illuminate\Support\Str::lower($member->first_name . ' ' . $member->last_name);
                                return str_contains($fullName, $search);
                            });
                    @endphp

                    @forelse($filteredMembers as $member)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="selectedMemberIds" value="{{ $member->id }}" />
                            <span>{{ $member->first_name }} {{ $member->last_name }} ({{ $member->birth_date?->age ?? '-' }})</span>
                        </label>
                    @empty
                        <div class="text-sm text-gray-500">Keine Mitglieder gefunden.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="create">Erstellen</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="editFamily">
        <flux:heading size="lg">Familie bearbeiten</flux:heading>
        <flux:text class="mt-2">Bitte Werte ändern.</flux:text>

        <div class="mt-4 space-y-3">
            <flux:input label="Name" wire:model.live="name" />

            <flux:input label="Mitglieder suchen" wire:model.live="memberSearch" />

            <div class="space-y-2">
                <div class="text-sm font-semibold">Mitglieder auswählen</div>
                <div class="max-h-48 overflow-y-auto border rounded p-2 space-y-2">
                    @php
                        $search = trim(\Illuminate\Support\Str::lower($memberSearch));
                        $filteredMembers = $search === ''
                            ? $members
                            : $members->filter(function ($member) use ($search) {
                                $fullName = \Illuminate\Support\Str::lower($member->first_name . ' ' . $member->last_name);
                                return str_contains($fullName, $search);
                            });
                    @endphp

                    @forelse($filteredMembers as $member)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="selectedMemberIds" value="{{ $member->id }}" />
                            <span>{{ $member->first_name }} {{ $member->last_name }} ({{ $member->birth_date?->age ?? '-' }})</span>
                        </label>
                    @empty
                        <div class="text-sm text-gray-500">Keine Mitglieder gefunden.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="update">Speichern</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="deleteFamily">
        <flux:heading size="lg">Familie löschen</flux:heading>
        <flux:text class="mt-2">Soll diese Familie wirklich gelöscht werden?</flux:text>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">Löschen</flux:button>
        </div>
    </flux:modal>
</section>
