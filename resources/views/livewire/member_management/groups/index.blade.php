<?php

use Livewire\Volt\Component;
use App\Models\Member\MemberGroup;

new class extends Component {

    public $groups;
    public string $name = '';
    public ?int $groupId = null;

    public function mount()
    {
        $this->loadGroups();
    }

    private function loadGroups(): void
    {
        $this->groups = MemberGroup::orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->name = '';
        $this->groupId = null;
        Flux::modal('createGroup')->show();
    }

    public function openEdit(int $groupId): void
    {
        $group = MemberGroup::findOrFail($groupId);
        $this->groupId = $group->id;
        $this->name = $group->name;
        Flux::modal('editGroup')->show();
    }

    public function create(): void
    {
        if (trim($this->name) === '') {
            Flux::toast('Name ist erforderlich');
            return;
        }

        MemberGroup::create([
            'name' => trim($this->name),
        ]);

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
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="update">Speichern</flux:button>
        </div>
    </flux:modal>
</section>
