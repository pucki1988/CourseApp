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
        
    }

    private function loadUsers(UserService $userService){
        $this->users=$userService->usersWithFrontendAccess();
    }

   

    

};
?>

<section class="w-full">
    @include('partials.users-heading')

    <x-users.layout :heading="__('User')" :subheading="__('Deine User')">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
     @foreach ($users as $user)
        <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">
            <div>
                <flux:text weight="medium">
                    {{ $user->name }} <flux:badge>{{ $user->getRoleNames()[0]}}</flux:badge>
                </flux:text>
            </div>
    </div>
    @endforeach
</div>
</div>
    </x-users.layout>

</section>