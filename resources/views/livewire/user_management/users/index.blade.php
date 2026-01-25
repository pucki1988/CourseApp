<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Services\User\UserService;


new class extends Component {

    public $users;
    public $roles;
    public $username = '';
    public int $userId;
    

    public function mount(UserService $userService)
    {
        $this->loadUsers($userService);
        
    }

    public function updatedUsername(UserService $userService)
    {
        
        $this->loadUsers($userService);
    }

    private function loadUsers(UserService $userService){

        $filters = [
            'username' => $this->username
        ];
        $this->users=$userService->usersWithFrontendAccess($filters);
    }

   
    public function setAsMember(UserService $userService): void
    {
        $userService->approveMember($this->userId);
        $this->loadUsers($userService);
        Flux::modal('setMember')->close();
    }

    public function setAsManager(UserService $userService): void
    {
        $userService->approveManager($this->userId);
        $this->loadUsers($userService);
        Flux::modal('setManager')->close();
    }

    public function unsetAsMember(UserService $userService): void
    {
        $userService->unsetMember($this->userId);
        $this->loadUsers($userService);
        Flux::modal('unsetMember')->close();
    }

    public function modalSetAsMember(int $userId)
    {
        $this->userId = $userId;
        Flux::modal('setMember')->show();
    }

    public function modalUnsetAsMember(int $userId)
    {
        $this->userId = $userId;
        Flux::modal('unsetMember')->show();
    }

    public function modalSetAsManager(int $userId)
    {
        $this->userId = $userId;
        Flux::modal('setManager')->show();
    }

};
?>

<section class="w-full">
    @include('partials.users-heading')

    <x-users.layout :heading="__('User')" :subheading="__('Deine User')">

        <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

        <!-- Suche -->

        <flux:input
        wire:model.live.debounce.300ms="username"
        placeholder="Suche nach Name…"
        icon="magnifying-glass"
        />

    

    </div>

    <div class=" grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
     @foreach ($users as $user)
    <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Name</span>
                                <span>{{ $user->name }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Rolle</span>
                                <span>@foreach ($user->getRoleNames() as $role)<flux:badge size="sm">{{ $role }}</flux:badge>@endforeach</span>
                            </div>

                            @role(['admin', 'manager'])
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Als manager</span>
                                <span> 
                                @if(!$user->hasRole('manager'))               
                                <flux:button variant="primary" size="xs" color="green" icon="check" wire:click="modalSetAsManager({{ $user->id }})">Ja</flux:button>
                                @endif
                                </span>
                            </div>
                            @endrole

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Mitglied</span>
                                <span> 
                                    @if($user->hasRole('member'))
                    <flux:button variant="primary" size="xs" color="red" icon="x-mark" wire:click="modalUnsetAsMember({{ $user->id }})">Nein</flux:button>
                    @else
                    <flux:button variant="primary" size="xs" color="green" icon="check" wire:click="modalSetAsMember({{ $user->id }})">Ja</flux:button>
                    @endif</span>
                            </div>
                        </div>
    </div>
    @endforeach
    </div>
    </x-users.layout>
<flux:modal name="setMember" >
        <flux:heading size="lg">Mitgliedschaft</flux:heading>

        <flux:text class="mt-2">
            Soll der User als Mitglied gesetzt werden?
        </flux:text>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
            <flux:button
                variant="ghost"
            >
                Abbrechen
            </flux:button>
            </flux:modal.close>
            <flux:button
                variant="primary" color="green"
                wire:click="setAsMember"
            >
                Ja
            </flux:button>
        </div>
    </flux:modal>
    <flux:modal name="unsetMember" >
        <flux:heading size="lg">Mitgliedschaft</flux:heading>

        <flux:text class="mt-2">
            Soll die Mitgliedschaft des Users entfernt werden?
        </flux:text>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
            <flux:button
                variant="ghost"
            >
                Abbrechen
            </flux:button>
            </flux:modal.close>
            <flux:button
                variant="danger" 
                wire:click="unsetAsMember"
            >
                Ja
            </flux:button>
        </div>
    </flux:modal>
    <flux:modal name="setManager" >
        <flux:heading size="lg">Manager</flux:heading>

        <flux:text class="mt-2">
            Soll der User als Manager gesetzt werden?
        </flux:text>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
            <flux:button
                variant="ghost"
            >
                Abbrechen
            </flux:button>
            </flux:modal.close>
            <flux:button
                variant="primary" color="green"
                wire:click="setAsManager"
            >
                Ja
            </flux:button>
        </div>
    </flux:modal>
</section>