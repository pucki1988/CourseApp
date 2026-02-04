<?php

use Livewire\Volt\Component;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

new class extends Component {

    public User $user;
    public $roles;
    public $permissions;
    public array $rolesSelected = [];
    public array $permissionsSelected = [];
    public array $roleDerived = [];

    public function mount($user)
    {
        if ($user instanceof User) {
            $this->user = $user->load('roles');
        } else {
            $this->user = User::with('roles')->findOrFail($user);
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

};
?>

<section class="w-full">
    @include('partials.users-heading')
    <x-users.layout :heading="'Benutzer: ' . ($user->name ?? $user->email)">

        <form wire:submit.prevent="save">
            <div class="mb-4">
                <label class="font-semibold">Name</label>
                <div>{{ $user->name }}</div>
            </div>

            <div class="mb-4">
                <label class="font-semibold">E-Mail</label>
                <div>{{ $user->email }}</div>
            </div>

            <div class="mb-6">
                <label class="font-semibold">Rollen</label>
                <div class="mt-2 space-y-2">
                    @foreach($roles as $role)
                        <div>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="rolesSelected" value="{{ $role->name }}" @if($role->name === 'admin' && !auth()->user()->hasRole('admin')) disabled @endif />
                                <span class="ml-2">{{ ucfirst($role->name) }}</span>
                                @if($role->name === 'admin' && !auth()->user()->hasRole('admin'))
                                    <span class="text-xs text-red-500">(nur Admin)</span>
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
                <summary class="font-semibold cursor-pointer">Zusätzliche Berechtigungen (direkt)</summary>
                <div class="mt-2 space-y-2 max-h-64 overflow-auto">
                    @php
                        $groupedPermissions = $permissions
                            ->filter(fn ($permission) => !in_array($permission->name, $roleDerived))
                            ->groupBy(fn ($permission) => explode('.', $permission->name)[0]);
                    @endphp

                    @foreach($groupedPermissions as $group => $groupPermissions)
                        <details class="mt-2">
                            <summary class="text-xs uppercase tracking-wide text-gray-500 mb-1 cursor-pointer">
                                {{ ucfirst($group) }}
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
</section>
