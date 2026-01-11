<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Services\User\UserService;


new class extends Component {

    public $users;
    public $roles;

    public function mount(UserService $userService)
    {
        $this->loadUsers($userService);
        $this->roles=['','user','member'];
    }

    private function loadUsers(UserService $userService){
        $this->users=$userService->usersWithMemberRequest();
    }

    public function updateRole(
        int $userId,
        string $role,
        UserService $userService
    ) {
        $userService->updateRole($userId, $role);
    }

    public function approveMember(int $userId,UserService $userService): void
    {
        $userService->approveMember($userId);
        $this->loadUsers($userService);
    }

    public function disapproveMember(int $userId,UserService $userService): void
    {
        $userService->disapproveMember($userId);
        $this->loadUsers($userService);
    }

};
?>

<section class="w-full">
    @include('partials.users-heading')

    <x-users.layout :heading="__('Anfragen Vereinsmitgliedschaft')" :subheading="__('Deine User')">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
       <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
     @forelse ($users as $user)
    <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Name</span>
                                <span>{{ $user->name }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">E-Mail</span>
                                <span>{{$user->email}}</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Anfrage</span>
                                <span> 
                                    <flux:button variant="primary" size="xs" color="green" wire:click="approveMember({{ $user->id }})">Bestätigen</flux:button>
                                    <flux:button variant="primary" size="xs" color="red" wire:click="disapproveMember({{ $user->id }})">Ablehnen</flux:button>
                                </span>
                            </div>
                        </div>
    </div>
    @empty
                <flux:text>Keine Anfragen gefunden</flux:text>
    @endforelse
</div>
</div>
    </x-users.layout>

</section>