<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Models\Member\Member;
use App\Services\User\UserService;


new class extends Component {

    public $users;
    public $roles;
    public int $userId;
    public $availableMembers = [];
    public ?int $memberToAssignId = null;
    public User $user;

    public function mount(UserService $userService)
    {
        $this->loadUsers($userService);
        $this->roles=['','user','member'];
    }

    private function loadUsers(UserService $userService){
        $this->users=$userService->usersWithMemberRequest();
    }

    public function disapproveMember(UserService $userService): void
    {
        $userService->disapproveMember($this->userId);
        $this->loadUsers($userService);
        Flux::modal('disapproveMember')->close();
    }

    public function modalDisapproveMember(int $userId)
    {
        $this->userId = $userId;
        Flux::modal('disapproveMember')->show();
    }

    public function loadAvailableMembers(): void
    {
        $this->availableMembers = Member::whereNull('user_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function openAssignMember(int $userId): void
    {
        $this->memberToAssignId = null;
        $this->userId = $userId;
        $this->user = User::find($userId);
        $this->loadAvailableMembers();
        Flux::modal('assignMember')->show();

    }

    public function assignMember(): void
    {
        if (!$this->memberToAssignId) {
            return;
        }

        $member = Member::whereNull('user_id')->findOrFail($this->memberToAssignId);
        $member->update(['user_id' => $this->userId]);
        $this->user->update(['member_requested' => false]);
        $this->loadUsers(app(UserService::class));
        $this->memberToAssignId = null;
        Flux::modal('assignMember')->close();
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
                                    <flux:button variant="primary" size="xs" color="green" wire:click="openAssignMember({{ $user->id }})">Zuweisen</flux:button>
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