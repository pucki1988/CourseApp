<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Passwort vergessen')" :description="__('Gib deine E-Mail Adresse ein um einen Link zum zurücksetzen deines Passworts zu erhalten')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('E-Mail Adresse')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Passwort zurücksetzen Link erhalten') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Zurück zur') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Anmeldung') }}</flux:link>
        </div>
    </div>
</x-layouts.auth>
