<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Passwort bestätigen')"
            :description="__('Das ist eine Sicherheitsfunktion der Anwendung. Bestätige dein Passwort bevor es weitergeht.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Passwort')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Passwort')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('Bestätigen') }}
            </flux:button>
        </form>
    </div>
</x-layouts.auth>
