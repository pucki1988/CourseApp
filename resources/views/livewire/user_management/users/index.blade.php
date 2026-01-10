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
        $this->users=$userService->usersWithFrontendAccess($filters);
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


        <table class="min-w-full divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Rolle</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Mitglied</th>
            </tr>
        </thead>
        <tbody class=" divide-gray-100">
     @foreach ($users as $user)
    <tr>
     <td class="px-4 py-2">{{ $user->name }}</td>
     <td class="px-4 py-2"><div>
    @foreach ($user->getRoleNames() as $role)
    <flux:badge size="sm">{{ $role }}</flux:badge>
    @endforeach
    </div></td>
    <td class="px-4 py-2 text-right">
            
                    @if($user->hasRole('member'))
                    <flux:button variant="primary" size="xs" color="red" icon="x-mark" wire:click="unsetAsMember({{ $user->id }})">Nein</flux:button>
                    @else
                    <flux:button variant="primary" size="xs" color="green" icon="check" wire:click="setAsMember({{ $user->id }})">Ja</flux:button>
                    @endif
                    
    </td>
    </tr>
    @endforeach
    </tbody>
    </table>
</div>
</div>
    </x-users.layout>

</section>