<?php

use Livewire\Volt\Component;
use App\Models\Member\MembershipType;
use Illuminate\Support\Facades\Validator;

new class extends Component {

    public $types;

    public ?int $membershipTypeId = null;
    public string $name = '';
    public string $slug = '';
    public string $baseAmount = '';
    public string $billingMode = 'recurring';
    public ?string $interval = 'monthly';
    public string $conditionsJson = '';
    public bool $active = true;
    public bool $isClubMembership = false;
    public int $sortOrder = 0;

    public function mount()
    {
        $this->loadTypes();
    }

    private function loadTypes(): void
    {
        $this->types = MembershipType::orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function resetForm(): void
    {
        $this->membershipTypeId = null;
        $this->name = '';
        $this->slug = '';
        $this->baseAmount = '';
        $this->billingMode = 'recurring';
        $this->interval = 'monthly';
        $this->conditionsJson = '';
        $this->active = true;
        $this->isClubMembership = false;
        $this->sortOrder = 0;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        Flux::modal('createMembershipType')->show();
    }

    public function openEdit(int $membershipTypeId): void
    {
        $type = MembershipType::findOrFail($membershipTypeId);

        $this->membershipTypeId = $type->id;
        $this->name = $type->name;
        $this->slug = $type->slug;
        $this->baseAmount = (string) $type->base_amount;
        $this->billingMode = $type->billing_mode;
        $this->interval = $type->interval;
        $this->conditionsJson = $type->conditions ? json_encode($type->conditions, JSON_UNESCAPED_UNICODE) : '';
        $this->active = (bool) $type->active;
        $this->isClubMembership = (bool) $type->is_club_membership;
        $this->sortOrder = (int) $type->sort_order;

        Flux::modal('editMembershipType')->show();
    }

    public function openDelete(int $membershipTypeId): void
    {
        $this->membershipTypeId = $membershipTypeId;
        Flux::modal('deleteMembershipType')->show();
    }

    private function parseConditions(): ?array
    {
        if (trim($this->conditionsJson) === '') {
            return null;
        }

        $decoded = json_decode($this->conditionsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Flux::toast('Bedingungen müssen gültiges JSON sein');
            return null;
        }

        return $decoded;
    }

    private function validateInput(?int $ignoreId = null): bool
    {
        $interval = $this->billingMode === 'one_time' ? null : $this->interval;

        $validator = Validator::make([
            'name' => $this->name,
            'slug' => $this->slug,
            'base_amount' => $this->baseAmount,
            'billing_mode' => $this->billingMode,
            'interval' => $interval,
            'sort_order' => $this->sortOrder,
            'active' => $this->active,
            'is_club_membership' => $this->isClubMembership,
        ], [
            'name' => 'required|string',
            'slug' => 'required|alpha_dash|unique:membership_types,slug' . ($ignoreId ? ',' . $ignoreId : ''),
            'base_amount' => 'required|numeric|min:0',
            'billing_mode' => 'required|in:recurring,one_time',
            'interval' => 'nullable|in:monthly,yearly',
            'sort_order' => 'nullable|integer|min:0',
            'active' => 'boolean',
            'is_club_membership' => 'boolean',
        ]);

        if ($validator->fails()) {
            Flux::toast($validator->errors()->first());
            return false;
        }

        return true;
    }

    public function create(): void
    {
        if (!$this->validateInput()) {
            return;
        }

        $conditions = $this->parseConditions();
        if ($this->conditionsJson !== '' && $conditions === null) {
            return;
        }

        $interval = $this->billingMode === 'one_time' ? null : $this->interval;

        MembershipType::create([
            'name' => trim($this->name),
            'slug' => trim($this->slug),
            'base_amount' => $this->baseAmount,
            'billing_mode' => $this->billingMode,
            'interval' => $interval,
            'conditions' => $conditions,
            'active' => $this->active,
            'is_club_membership' => $this->isClubMembership,
            'sort_order' => $this->sortOrder,
        ]);

        $this->loadTypes();
        Flux::modal('createMembershipType')->close();
    }

    public function update(): void
    {
        if (!$this->membershipTypeId) {
            return;
        }

        if (!$this->validateInput($this->membershipTypeId)) {
            return;
        }

        $conditions = $this->parseConditions();
        if ($this->conditionsJson !== '' && $conditions === null) {
            return;
        }

        $interval = $this->billingMode === 'one_time' ? null : $this->interval;

        $type = MembershipType::findOrFail($this->membershipTypeId);
        $type->update([
            'name' => trim($this->name),
            'slug' => trim($this->slug),
            'base_amount' => $this->baseAmount,
            'billing_mode' => $this->billingMode,
            'interval' => $interval,
            'conditions' => $conditions,
            'active' => $this->active,
            'is_club_membership' => $this->isClubMembership,
            'sort_order' => $this->sortOrder,
        ]);

        $this->loadTypes();
        Flux::modal('editMembershipType')->close();
    }

    public function delete(): void
    {
        if (!$this->membershipTypeId) {
            return;
        }

        $type = MembershipType::findOrFail($this->membershipTypeId);
        if ($type->memberships()->exists()) {
            Flux::toast('Mitgliedschaftstyp kann nicht gelöscht werden (bereits verwendet)');
            return;
        }

        $type->delete();
        $this->loadTypes();
        Flux::modal('deleteMembershipType')->close();
    }

};
?>

<section class="w-full">
    @include('partials.members-heading')

    <x-members.layout :heading="__('Mitgliedschaftstypen')" :subheading="__('Übersicht')">
        <div class="flex md:justify-end mb-3">
            <flux:button icon="plus" wire:click="openCreate">Neuer Typ</flux:button>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach ($types as $type)
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                    <div class="text-sm">
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Name</span>
                            <span>{{ $type->name }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Slug</span>
                            <span>{{ $type->slug }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Betrag</span>
                            <span>{{ number_format($type->base_amount, 2, ',', '.') }} €</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Modus</span>
                            <span>{{ $type->billing_mode === 'one_time' ? 'Einmalig' : 'Wiederkehrend' }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Intervall</span>
                            <span>{{ $type->interval ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Aktiv</span>
                            <span>{{ $type->active ? 'Ja' : 'Nein' }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Vereinsmitgliedschaft</span>
                            <span>{{ $type->is_club_membership ? 'Ja' : 'Nein' }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Sortierung</span>
                            <span>{{ $type->sort_order }}</span>
                        </div>
                        <div class="flex justify-end mt-2 gap-2">
                            <flux:button size="xs" wire:click="openEdit({{ $type->id }})">Bearbeiten</flux:button>
                            <flux:button size="xs" variant="danger" wire:click="openDelete({{ $type->id }})">Löschen</flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-members.layout>

    <flux:modal name="createMembershipType">
        <flux:heading size="lg">Mitgliedschaftstyp anlegen</flux:heading>
        <flux:text class="mt-2">Bitte Werte eingeben.</flux:text>

        <div class="mt-4 space-y-3">
            <flux:input label="Name" wire:model.live="name" />
            <flux:input label="Slug" wire:model.live="slug" />
            <flux:input label="Betrag" wire:model.live="baseAmount" type="number" step="0.01" />
            <flux:select label="Abrechnungsmodus" wire:model.live="billingMode">
                <option value="recurring">Wiederkehrend</option>
                <option value="one_time">Einmalig</option>
            </flux:select>
            <flux:select label="Intervall" wire:model.live="interval">
                <option value="monthly">Monatlich</option>
                <option value="yearly">Jährlich</option>
            </flux:select>
            <flux:textarea label="Bedingungen (JSON)" wire:model.live="conditionsJson" rows="3" />
            <flux:input label="Sortierung" wire:model.live="sortOrder" type="number" />
            <flux:checkbox wire:model="active" :label="__('Aktiv')" />
            <flux:checkbox wire:model="isClubMembership" :label="__('Vereinsmitgliedschaft')" />
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="create">Erstellen</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="editMembershipType">
        <flux:heading size="lg">Mitgliedschaftstyp bearbeiten</flux:heading>
        <flux:text class="mt-2">Bitte Werte anpassen.</flux:text>

        <div class="mt-4 space-y-3">
            <flux:input label="Name" wire:model.live="name" />
            <flux:input label="Slug" wire:model.live="slug" />
            <flux:input label="Betrag" wire:model.live="baseAmount" type="number" step="0.01" />
            <flux:select label="Abrechnungsmodus" wire:model.live="billingMode">
                <option value="recurring">Wiederkehrend</option>
                <option value="one_time">Einmalig</option>
            </flux:select>
            <flux:select label="Intervall" wire:model.live="interval">
                <option value="monthly">Monatlich</option>
                <option value="yearly">Jährlich</option>
            </flux:select>
            <flux:textarea label="Bedingungen (JSON)" wire:model.live="conditionsJson" rows="3" />
            <flux:input label="Sortierung" wire:model.live="sortOrder" type="number" />
            <flux:checkbox wire:model="active" :label="__('Aktiv')" />
            <flux:checkbox wire:model="isClubMembership" :label="__('Vereinsmitgliedschaft')" />
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="update">Speichern</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="deleteMembershipType">
        <flux:heading size="lg">Mitgliedschaftstyp löschen</flux:heading>
        <flux:text class="mt-2">Soll dieser Typ wirklich gelöscht werden?</flux:text>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">Löschen</flux:button>
        </div>
    </flux:modal>
</section>
