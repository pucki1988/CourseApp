<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Services\User\UserService;


new class extends Component {

    public $users;
    public $roles;
    public $username = '';
    

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
        $this->users=$userService->usersWithBackendAccess($filters);
    }

   
    public function setAsMember(int $userId,UserService $userService): void
    {
        $userService->approveMember($userId);
        $this->loadUsers($userService);
    }

    public function unsetAsMember(int $userId,UserService $userService): void
    {
        $userService->unsetMember($userId);
        $this->loadUsers($userService);
    }

    public function unsetAsManager(int $userId,UserService $userService): void
    {
        $userService->unsetManager($userId);
        $this->loadUsers($userService);
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
                                @if($user->hasRole('manager'))               
                                <flux:button variant="primary" size="xs" color="red" icon="x-mark" wire:click="unsetAsManager({{ $user->id }})">Nein</flux:button>
                                @endif
                                </span>
                            </div>
                            @endrole

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Mitglied</span>
                                <span> 
                                    @if($user->hasRole('member'))
                    <flux:button variant="primary" size="xs" color="red" icon="x-mark" wire:click="unsetAsMember({{ $user->id }})">Nein</flux:button>
                    @else
                    <flux:button variant="primary" size="xs" color="green" icon="check" wire:click="setAsMember({{ $user->id }})">Ja</flux:button>
                    @endif</span>
                            </div>
                        </div>
    </div>
    @endforeach
    </div>

        

    </x-users.layout>

</section>