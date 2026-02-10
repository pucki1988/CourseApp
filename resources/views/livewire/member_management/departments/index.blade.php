<?php

use Livewire\Volt\Component;
use App\Models\Member\Department;

new class extends Component {

    public $departments;
    public string $name = '';
    public ?string $blsvId = null;
    public ?int $departmentId = null;

    public function mount()
    {
        $this->loadDepartments();
    }

    private function loadDepartments(): void
    {
        $this->departments = Department::orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->name = '';
        $this->blsvId = null;
        $this->departmentId = null;
        Flux::modal('createDepartment')->show();
    }

    public function openEdit(int $departmentId): void
    {
        $department = Department::findOrFail($departmentId);
        $this->departmentId = $department->id;
        $this->name = $department->name;
        $this->blsvId = $department->blsv_id;
        Flux::modal('editDepartment')->show();
    }

    public function create(): void
    {
        if (trim($this->name) === '') {
            Flux::toast('Name ist erforderlich');
            return;
        }

        Department::create([
            'name' => trim($this->name),
            'blsv_id' => $this->blsvId ? trim($this->blsvId) : null,
        ]);

        $this->loadDepartments();
        Flux::modal('createDepartment')->close();
    }

    public function update(): void
    {
        if (!$this->departmentId) {
            return;
        }

        if (trim($this->name) === '') {
            Flux::toast('Name ist erforderlich');
            return;
        }

        $department = Department::findOrFail($this->departmentId);
        $department->update([
            'name' => trim($this->name),
            'blsv_id' => $this->blsvId ? trim($this->blsvId) : null,
        ]);

        $this->loadDepartments();
        Flux::modal('editDepartment')->close();
    }

};
?>

<section class="w-full">
    @include('partials.members-heading')

    <x-members.layout :heading="__('Sparten')" :subheading="__('Übersicht')">
        <div class="flex md:justify-end mb-3">
            <flux:button icon="plus" wire:click="openCreate">Neue Sparte</flux:button>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach ($departments as $department)
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                    <div class="text-sm">
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Name</span>
                            <span>{{ $department->name }}</span>
                        </div>
                        
                        <div class="flex justify-end mt-2">
                            <flux:button size="xs" wire:click="openEdit({{ $department->id }})">Bearbeiten</flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-members.layout>

    <flux:modal name="createDepartment">
        <flux:heading size="lg">Sparte anlegen</flux:heading>
        <flux:text class="mt-2">Bitte Name und optional VerbandsID eingeben.</flux:text>

        <div class="mt-4 space-y-3">
            <flux:input label="Name" wire:model.live="name" />
            <flux:input label="VerbandsID" wire:model.live="blsvId" />
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="create">Erstellen</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="editDepartment">
        <flux:heading size="lg">Sparte bearbeiten</flux:heading>
        <flux:text class="mt-2">Bitte Werte anpassen.</flux:text>

        <div class="mt-4 space-y-3">
            <flux:input label="Name" wire:model.live="name" />
            <flux:input label="VerbandsID" wire:model.live="blsvId" />
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="update">Speichern</flux:button>
        </div>
    </flux:modal>
</section>
