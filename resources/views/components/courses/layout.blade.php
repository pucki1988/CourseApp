<div class="flex items-start max-md:flex-col">
    <div class="fixed bottom-0 left-0 z-50 w-full md:me-10
        border-t bg-white md:pt-0
        md:static md:z-auto md:w-[220px] md:border-0 md:bg-transparent
        pb-safe">
    <flux:navlist class="flex flex-row gap-2 overflow-x-auto md:flex-col md:gap-1 items-center">
        @can('courses.manage')
            <flux:navlist.item
                :href="route('course_management.home.index')"
                :current="request()->routeIs('course_management.home.index')"
                wire:navigate
                class="h-14"
                
            >
            <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.home class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __('Home') }}</span>
                </span>
            </flux:navlist.item>
        @endcan

        @can(['courses.manage'])
            <flux:navlist.item
                :href="route('course_management.courses.index')"
                :current="request()->routeIs('course_management.courses.index')"
                wire:navigate
                class="h-14"
            >
                <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2 s">
                <flux:icon.flag class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __('Kurse') }}</span>
                </span>
            </flux:navlist.item>
        @endcan
        @canany(['coursebookings.manage'])
            <flux:navlist.item
                :href="route('course_management.bookings.index')"
                :current="request()->routeIs('course_management.bookings.index')"
                wire:navigate
                class="h-14"
            >
                <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.list-bullet class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __(key: 'Buchungen') }}</span>
                </span>
            </flux:navlist.item>
        @endcanany
        @can('courses.manage')
            <flux:navlist.item
                :href="route('course_management.coaches.index')"
                :current="request()->routeIs('course_management.coaches.index')"
                wire:navigate
                class="h-14"
            >
                <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.users class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __('Trainer') }}</span>
                </span>
            </flux:navlist.item>
        @endcan
        @can('courses.manage')
            <flux:navlist.item
                :href="route('course_management.settings.sport-types')"
                :current="request()->routeIs('course_management.settings.sport-types')"
                wire:navigate
                class="h-14"
            >
                <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.tag class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __('Sportarten') }}</span>
                </span>
            </flux:navlist.item>
            @endcan
        @can(['courses.manage'])
            <flux:navlist.item
                :href="route('course_management.settings.equipment-items')"
                :current="request()->routeIs('course_management.settings.equipment-items')"
                wire:navigate
                class="h-14"
            >
                <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.wrench-screwdriver class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __('Ausrüstung') }}</span>
                </span>
            </flux:navlist.item>
            @endcan

            @can('courses.manage')
            <flux:navlist.item
                :href="route('course_management.settlement.index')"
                :current="request()->routeIs('course_management.settlement.index')"
                wire:navigate
                class="h-14"
            >
                <span class="flex flex-col items-center justify-center md:items-start md:justify-start gap-1 md:flex-row md:gap-2">
                <flux:icon.document-currency-euro class="h-5 w-5 md:hidden" />
                <span class="md:inline">{{ __('Abrechnung') }}</span>
                </span>
            </flux:navlist.item>
            @endcan
        
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