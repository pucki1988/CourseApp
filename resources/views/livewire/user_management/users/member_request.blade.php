<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Services\User\UserService;


new class extends Component {

    public $users;
    public $roles;
    public int $userId;

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

    public function approveMember(UserService $userService): void
    {
        $userService->approveMember($this->userId);
        $this->loadUsers($userService);
        Flux::modal('approveMember')->close();
    }

    public function disapproveMember(UserService $userService): void
    {
        $userService->disapproveMember($this->userId);
        $this->loadUsers($userService);
        Flux::modal('disapproveMember')->close();
    }

    public function modalApproveMember(int $userId)
    {
        $this->userId = $userId;
        Flux::modal('approveMember')->show();
    }

    public function modalDisapproveMember(int $userId)
    {
        $this->userId = $userId;
        Flux::modal('disapproveMember')->show();
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
                                    <flux:button variant="primary" size="xs" color="green" wire:click="modalApproveMember({{ $user->id }})">Bestätigen</flux:button>
                                    <flux:button variant="primary" size="xs" color="red" wire:click="modalDisapproveMember({{ $user->id }})">Ablehnen</flux:button>
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
<flux:modal name="approveMember" >
        <flux:heading size="lg">Anfragen</flux:heading>

        <flux:text class="mt-2">
            Soll die Mitgliedschaft bestätigt werden?
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
                wire:click="approveMember"
            >
                Ja
            </flux:button>
        </div>
    </flux:modal>
    <flux:modal name="disapproveMember" >
        <flux:heading size="lg">Anfragen</flux:heading>

        <flux:text class="mt-2">
            Soll die Mitgliedschaft abgelehnt werden?
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
                wire:click="disapproveMember"
            >
                Ja
            </flux:button>
        </div>
    </flux:modal>
</section>