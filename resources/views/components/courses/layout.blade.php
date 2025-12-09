<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item :href="route('course_management.home.index')" :current="request()->routeIs('course_management.home.index')" wire:navigate>{{ __('Home') }}</flux:navlist.item>
            <flux:navlist.item :href="route('course_management.courses.index')" :current="request()->routeIs('course_management.courses.index')" wire:navigate>{{ __('Kurse') }}</flux:navlist.item>
            <flux:navlist.item :href="route('course_management.bookings.index')" :current="request()->routeIs('course_management.bookings.index')"  wire:navigate>{{ __('Buchungen') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <div class="mt-5 w-full max-w-xxl">
            {{ $slot }}
        </div>
    </div>
</div>