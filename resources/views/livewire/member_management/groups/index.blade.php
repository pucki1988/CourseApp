<?php

use Livewire\Volt\Component;
use App\Models\Member\MemberGroup;
use App\Models\Member\Member;
use Illuminate\Database\Eloquent\Collection;

new class extends Component {

    public $groups;
    public Collection $members;
    public string $name = '';
    public ?int $groupId = null;
    public array $selectedMemberIds = [];
    public string $memberSearch = '';

    public function mount()
    {
        $this->loadGroups();
        $this->loadMembers();
    }

    private function loadGroups(): void
    {
        $this->groups = MemberGroup::orderBy('name')->get();
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
        $this->groupId = null;
        $this->name = '';
        $this->selectedMemberIds = [];
        $this->memberSearch = '';
    }

    public function openCreate(): void
    {
        $this->resetForm();
        Flux::modal('createGroup')->show();
    }

    public function openEdit(int $groupId): void
    {
        $group = MemberGroup::with('members')->findOrFail($groupId);
        $this->groupId = $group->id;
        $this->name = $group->name;
        $this->selectedMemberIds = $group->members->pluck('id')->toArray();
        $this->memberSearch = '';
        Flux::modal('editGroup')->show();
    }

    public function create(): void
    {
        if (trim($this->name) === '') {
            Flux::toast('Name ist erforderlich');
            return;
        }

        $group = MemberGroup::create([
            'name' => trim($this->name),
        ]);

        $group->members()->sync($this->selectedMemberIds ?: []);

        $this->loadGroups();
        Flux::modal('createGroup')->close();
    }

    public function update(): void
    {
        if (!$this->groupId) {
            return;
        }

        if (trim($this->name) === '') {
            Flux::toast('Name ist erforderlich');
            return;
        }

        $group = MemberGroup::findOrFail($this->groupId);
        $group->update([
            'name' => trim($this->name),
        ]);

        $group->members()->sync($this->selectedMemberIds ?: []);

        $this->loadGroups();
        Flux::modal('editGroup')->close();
    }

};
?>

<section class="w-full">
    @include('partials.members-heading')

    <x-members.layout :heading="__('Gruppen')" :subheading="__('Übersicht')">
        <div class="flex md:justify-end mb-3">
            <flux:button icon="plus" wire:click="openCreate">Neue Gruppe</flux:button>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach ($groups as $group)
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                    <div class="text-sm">
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Name</span>
                            <span>{{ $group->name }}</span>
                        </div>
                        <div class="flex justify-end mt-2">
                            <flux:button size="xs" wire:click="openEdit({{ $group->id }})">Bearbeiten</flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-members.layout>

    <flux:modal name="createGroup">
        <flux:heading size="lg">Gruppe anlegen</flux:heading>
        <flux:text class="mt-2">Bitte Name eingeben.</flux:text>

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

    <flux:modal name="editGroup">
        <flux:heading size="lg">Gruppe bearbeiten</flux:heading>
        <flux:text class="mt-2">Bitte Name anpassen.</flux:text>

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
</section>
