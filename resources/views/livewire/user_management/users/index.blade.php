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
        <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
     @foreach ($users as $user)
        <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">
            <div>
                <flux:text weight="medium">
                    {{ $user->name }}
                </flux:text>

                <flux:text size="sm" class="text-gray-500">
                    {{ $user->email }}
                </flux:text>
                <flux:button variant="primary" color="green" wire:click="approveMember({{ $user->id }})">Bestätigen</flux:button>
                <flux:button variant="primary" color="red" wire:click="disapproveMember({{ $user->id }})">Ablehnen</flux:button>
            </div>

            

    </div>
    @endforeach
</div>
</div>
    </x-users.layout>

</section>