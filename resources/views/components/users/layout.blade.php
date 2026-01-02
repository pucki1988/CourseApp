<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            
            @role(['admin', 'manager'])
            <flux:navlist.item :href="route('user_management.users.index')" :current="request()->routeIs('user_management.users.index')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
            <flux:navlist.item :href="route('user_management.users.member_request')" :current="request()->routeIs('user_management.users.member_request')" wire:navigate>{{ __('Anfragen') }}</flux:navlist.item>
            
            @endrole
            
            
        
            </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />
    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading size="xl">{{ $heading ?? '' }}</flux:heading>

        <div class="mt-5 w-full max-w-xxl">
            {{ $slot }}
        </div>
    </div>
</div>