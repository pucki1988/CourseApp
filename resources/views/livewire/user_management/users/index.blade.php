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
                                <span>@if ($user->isMember())<flux:badge size="sm">Vereinsmitglied</flux:badge>@else<flux:badge size="sm">User</flux:badge> @endif</span>
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

    
    
</section>