<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;

use App\Models\User;
use App\Services\User\UserService;
use Flux\Flux;


new class extends Component {

    use WithPagination;

    public $roles;
    public $username = '';
    public int $perPage = 12;
    public int $userId;

    public function updatedUsername()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function getUsersProperty(){

        $filters = [
            'username' => $this->username,
            'per_page' => $this->perPage,
        ];

        return app(UserService::class)->usersWithFrontendAccess($filters);
    }

   
    public function setAsMember(UserService $userService): void
    {
        $userService->approveMember($this->userId);
        Flux::modal('setMember')->close();
    }

    public function setAsManager(UserService $userService): void
    {
        $userService->approveManager($this->userId);
        Flux::modal('setManager')->close();
    }

    public function unsetAsMember(UserService $userService): void
    {
        $userService->unsetMember($this->userId);
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

        <!-- Suche -->

        <flux:input
        wire:model.live.debounce.300ms="username"
        placeholder="Suche nach Name…"
        icon="magnifying-glass"
        />

        <flux:select wire:model.live="perPage">
            <option value="12">12 pro Seite</option>
            <option value="24">24 pro Seite</option>
            <option value="48">48 pro Seite</option>
        </flux:select>

        <div class="text-sm text-gray-500 text-end">
        {{ $this->users->total() }}
        {{ $this->users->total() === 1 ? 'Ergebnis' : 'Ergebnisse' }}
        </div>

    

    </div>

    @php($users = $this->users)
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
            

                            

                            <div class="flex justify-between mt-2">
                                <span class="text-gray-500">Aktionen</span>
                                <span>
                                    <a href="{{ route('user_management.users.show', $user->id) }}">
                                        <flux:button size="xs">Details</flux:button>
                                    </a>
                                </span>
                            </div>

                            
                        </div>
    </div>
    @endforeach
    </div>
    <div class="mt-4">
        {{ $users->links() }}
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