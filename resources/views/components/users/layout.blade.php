<div class="flex items-start max-md:flex-col">

    



    <div class="fixed bottom-0 left-0 z-50 w-full md:me-10
        border-t bg-white md:pt-0
        md:static md:z-auto md:w-[220px] md:border-0 md:bg-transparent
        pb-safe">
        <flux:navlist class="flex flex-row gap-2 overflow-x-auto md:flex-col md:gap-1 items-center">
            
            @can('users.view')
            <flux:navlist.item :href="route('user_management.users.index')" class="h-14" :current="request()->routeIs('user_management.users.index')" wire:navigate>
            
            <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.users class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __(key: 'Frontend User') }}</span>
                </span>
            </flux:navlist.item>
            @endcan
            @can('users.view')
            <flux:navlist.item :href="route('user_management.users.backend_user')" class="h-14" :current="request()->routeIs('user_management.users.backend_user')" wire:navigate>
            
            <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.users class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __(key: 'Backend User') }}</span>
                </span>
            </flux:navlist.item>
            @endcan
            @can('users.view')
            <flux:navlist.item :href="route('user_management.cards.index')" class="h-14" :current="request()->routeIs('user_management.cards.index')" wire:navigate>
            
            <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.users class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __(key: 'Karten') }}</span>
                </span>
            </flux:navlist.item>
            @endcan
            @canany(['users.view','users.view.requested_membership'])
            <flux:navlist.item :href="route('user_management.users.member_request')" class="h-14" :current="request()->routeIs('user_management.users.member_request')" wire:navigate>
                <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.question-mark-circle class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __('Anfragen') }}</span>
                </span>
            </flux:navlist.item>
            @endcanany
            
            
        
            </flux:navlist>
    </div>

    <flux:separator class="hidden" />
    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading size="xl">{{ $heading ?? '' }}</flux:heading>

        <div class="mt-5 w-full max-w-xxl">
            {{ $slot }}
        </div>
    </div>
</div>