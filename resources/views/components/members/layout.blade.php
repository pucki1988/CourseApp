<div class="flex items-start max-md:flex-col">
    <div class="fixed bottom-0 left-0 z-50 w-full md:me-10
        border-t bg-white md:pt-0
        md:static md:z-auto md:w-[220px] md:border-0 md:bg-transparent
        pb-safe">
        <flux:navlist class="flex flex-row gap-2 overflow-x-auto md:flex-col md:gap-1 items-center">
            
            @can('members.manage')
            <flux:navlist.item :href="route('member_management.members.index')" class="h-14" :current="request()->routeIs('member_management.members.index')" wire:navigate>
            
            <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.users class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __(key: 'Mitglieder') }}</span>
                </span>
            </flux:navlist.item>            
            @endcan

            @canany(['members.manage','members.view','members.create'])
            <flux:navlist.item :href="route('member_management.departments.index')" class="h-14" :current="request()->routeIs('member_management.departments.index')" wire:navigate>
            
            <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.users class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __(key: 'Sparten') }}</span>
                </span>
            </flux:navlist.item>
            @endcanany

            @canany(['members.manage','members.view','members.create'])
            <flux:navlist.item :href="route('member_management.groups.index')" class="h-14" :current="request()->routeIs('member_management.groups.index')" wire:navigate>
            
            <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.users class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __(key: 'Gruppen') }}</span>
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