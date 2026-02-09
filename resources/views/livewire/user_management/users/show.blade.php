<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Models\Member\Member;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

new class extends Component {

    public User $user;
    public $roles;
    public $permissions;
    public $transactions = [];
    public $availableMembers = [];
    public ?int $memberToAssignId = null;
    public array $rolesSelected = [];
    public array $permissionsSelected = [];
    public array $roleDerived = [];

    public function mount($user)
    {
        if ($user instanceof User) {
            $this->user = $user->load('roles', 'members');
        } else {
            $this->user = User::with('roles', 'members')->findOrFail($user);
        }

        $this->roles = Role::all();
        $this->permissions = Permission::all();
        $this->rolesSelected = $this->user->roles->pluck('name')->toArray();
        $this->permissionsSelected = $this->user->getDirectPermissions()->pluck('name')->toArray();
        $this->roleDerived = Role::whereIn('name', $this->rolesSelected)
            ->with('permissions')
            ->get()
            ->pluck('permissions.*.name')
            ->flatten()
            ->unique()
            ->toArray();

        $this->loadAvailableMembers();
    }

    public function save(): void
    {
        $this->user->syncRoles($this->rolesSelected ?: []);
        $this->user->syncPermissions($this->permissionsSelected ?: []);
        $this->user->refresh();
        Flux::toast('Änderungen gespeichert');
    }

    public function updatedRolesSelected()
    {
        $this->roleDerived = Role::whereIn('name', $this->rolesSelected)
            ->with('permissions')
            ->get()
            ->pluck('permissions.*.name')
            ->flatten()
            ->unique()
            ->toArray();
    }

    public function loadAvailableMembers(): void
    {
        $this->availableMembers = Member::whereNull('user_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function openAssignMember(): void
    {
        $this->memberToAssignId = null;
        $this->loadAvailableMembers();
        Flux::modal('assignMember')->show();
    }

    public function assignMember(): void
    {
        if (!$this->memberToAssignId) {
            return;
        }

        $member = Member::whereNull('user_id')->findOrFail($this->memberToAssignId);
        $member->update(['user_id' => $this->user->id]);

        $this->user->load('members');
        $this->memberToAssignId = null;
        $this->loadAvailableMembers();
        Flux::modal('assignMember')->close();
    }

    public function unassignMember(int $memberId): void
    {
        $member = Member::where('user_id', $this->user->id)->findOrFail($memberId);
        $member->update(['user_id' => null]);

        $this->user->load('members');
        $this->loadAvailableMembers();
    }

    public function openTransactions(): void
    {
        $account = $this->user->loyaltyAccount;
        $this->transactions = $account
            ? $account->transactions()->orderByDesc('created_at')->get()
            : collect();

        Flux::modal('loyaltyTransactions')->show();
    }

};
?>

<section class="w-full">
    @include('partials.users-heading')
    <x-users.layout :heading="'Benutzer: ' . ($user->name ?? $user->email)">

        <form wire:submit.prevent="save">
            <div class="mb-4">
                <label class="font-semibold">Name</label>
                <div><flux:badge size="sm">{{ $user->name }}</flux:badge></div>
            </div>

            <div class="mb-4">
                <label class="font-semibold">E-Mail</label>
                <div><flux:badge size="sm">{{ $user->email }}</flux:badge></div>
            </div>

            <div class="mb-4">
                <label class="font-semibold">Treuepunkte</label>
                <div class="flex items-center gap-2">
                    <flux:badge color="lime" size="sm">{{ $user?->loyaltyAccount?->balance() ?? 0 }} Punkte</flux:badge>
                    <flux:button size="xs" icon="ellipsis-horizontal" variant="ghost" wire:click="openTransactions">mehr</flux:button>
                </div>
            </div>

        
            <div class="mb-4">
                <label class="font-semibold">Zugeordnete Member</label>
                <div class="mt-1 flex flex-wrap gap-2">
                    @forelse($user->members as $member)
                        <div class="flex items-center gap-1">
                            <flux:badge size="sm">
                                {{ $member->first_name }} {{ $member->last_name }} <flux:badge.close wire:click="unassignMember({{ $member->id }})" />
                            </flux:badge>
                            
                        </div>
                    @empty
                        <span class="text-sm text-gray-500">Keine</span>
                    @endforelse
                </div>
                <div class="mt-2">
                    <flux:button size="xs" variant="ghost" wire:click="openAssignMember">Member zuordnen</flux:button>
                </div>
            </div>

            <div class="mb-6">
                <label class="font-semibold">Rollen</label>
                <div class="mt-2 space-y-2">
                    @foreach($roles as $role)
                        <div>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="rolesSelected" value="{{ $role->name }}" @if(
                                    ($role->name === 'admin' && !auth()->user()->hasRole('admin')) ||
                                    ($role->name === 'manager' && !auth()->user()->hasRole('manager') && !auth()->user()->hasRole('admin'))
                                ) disabled @endif />
                                <span class="ml-2">{{ ucfirst($role->name) }}</span>
                                @if($role->name === 'admin' && !auth()->user()->hasRole('admin'))
                                    <span class="text-xs text-red-500">(nur Admin)</span>
                                @elseif($role->name === 'manager' && !auth()->user()->hasRole('manager') && !auth()->user()->hasRole('admin'))
                                    <span class="text-xs text-red-500">(nur Manager/Admin)</span>
                                @endif
                            </label>

                            @if($role->permissions->isNotEmpty())
                                <details class="mt-2">
                                    <summary class="text-xs uppercase tracking-wide text-gray-500 mb-1 cursor-pointer bg-primary">
                                        Berechtigungen
                                    </summary>

                                    @php
                                        $roleGrouped = $role->permissions
                                            ->groupBy(fn ($rp) => explode('.', $rp->name)[0]);
                                    @endphp

                                    @foreach($roleGrouped as $rGroup => $rPerms)
                                        <details class="mt-2">
                                            <summary class="text-xs uppercase tracking-wide text-gray-500 mb-1 cursor-pointer">
                                                {{ ucfirst($rGroup) }}
                                            </summary>
                                            <div class="mt-1">
                                                @foreach($rPerms as $rp)
                                                    @php
                                                        $rpParts = explode('.', $rp->name);
                                                        $rpLabel = (count($rpParts) >= 2 && end($rpParts) === 'own')
                                                            ? $rpParts[count($rpParts) - 2] . '.own'
                                                            : $rpParts[count($rpParts) - 1];
                                                    @endphp
                                                    <flux:badge size="xs" class="mt-1">{{ $rpLabel }}</flux:badge>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endforeach
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <details class="mb-6">
                @php
                    $groupedPermissions = $permissions
                        ->filter(fn ($permission) => !in_array($permission->name, $roleDerived))
                        ->groupBy(fn ($permission) => explode('.', $permission->name)[0]);

                    $selectedAdditionalTotal = $groupedPermissions
                        ->flatten()
                        ->pluck('name')
                        ->intersect($permissionsSelected)
                        ->count();
                @endphp
                <summary class="font-semibold cursor-pointer">Zusätzliche Berechtigungen (direkt) ({{ $selectedAdditionalTotal }})</summary>
                <div class="mt-2 space-y-2 max-h-64 overflow-auto">
                    @foreach($groupedPermissions as $group => $groupPermissions)
                        @php
                            $selectedInGroup = collect($groupPermissions)
                                ->pluck('name')
                                ->intersect($permissionsSelected)
                                ->count();
                        @endphp
                        <details class="mt-2">
                            <summary class="text-xs uppercase tracking-wide text-gray-500 mb-1 cursor-pointer">
                                {{ ucfirst($group) }} ({{ $selectedInGroup }})
                            </summary>
                            <div class="space-y-2 mt-1">
                                @foreach($groupPermissions as $permission)
                                    <div>
                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" wire:model="permissionsSelected" value="{{ $permission->name }}" />
                                            @php
                                                $permParts = explode('.', $permission->name);
                                                $permLabel = (count($permParts) >= 2 && end($permParts) === 'own')
                                                    ? $permParts[count($permParts) - 2] . '.own'
                                                    : $permParts[count($permParts) - 1];
                                            @endphp
                                            <span class="ml-2">{{ $permLabel }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            </details>

            <div class="flex gap-2">
                <flux:button type="submit">Speichern</flux:button>
                <flux:button variant="danger" href="{{ route('user_management.users.index') }}" class="flux:button secondary">Abbrechen</flux:button>
            </div>
        </form>

    </x-users.layout>

    <flux:modal name="loyaltyTransactions">
        <flux:heading size="lg">Treuepunkte</flux:heading>
        
        <div class="text-end my-1">
        <flux:badge>{{ $this->user->loyaltyAccount->balance() }} Punkte</flux:badge>
        </div>
        <div class="mt-4 max-h-80 overflow-auto">
            @if($transactions && count($transactions))
                <div class="space-y-2">
                    @foreach($transactions as $transaction)
                        <div class="border rounded-lg p-2 bg-white shadow-sm text-sm">
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-500">Datum</span>
                                <span><flux:badge size="sm" color="gray">{{ $transaction->created_at?->format('d.m.Y H:i') }}</flux:badge></span>
                            </div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-500">Punkte</span>
                                <span>
                                    <flux:badge size="sm" color="{{ $transaction->type === 'earn' ? 'green' : 'red' }}">
                                        + {{ $transaction->points }}
                                    </flux:badge>
                                </span>
                            </div>
                            
                            <div class="flex justify-between mb-1">
                                
                                <span class="text-gray-500">Herkunft</span>
                                <span><flux:badge size="sm" color="gray">
                                    {{ ucfirst($transaction->origin) }}
                                </flux:badge></span>
                            </div>
                            
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-sm text-gray-500">Keine Transaktionen vorhanden.</div>
            @endif
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Schließen</flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>

    <flux:modal name="assignMember">
        <flux:heading size="lg">Mitglied zuordnen</flux:heading>
        <flux:text class="mt-2">Bitte Mitglied auswählen.</flux:text>

        <div class="mt-4">
            <flux:select wire:model="memberToAssignId" placeholder="Mitglied auswählen">
                @foreach ($availableMembers as $member)
                    <flux:select.option :value="$member->id">
                        {{ $member->last_name }}, {{ $member->first_name }} ({{ $member->birth_date?->format('d.m.Y') }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="assignMember">Zuordnen</flux:button>
        </div>
    </flux:modal>
</section>
