<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Account erstellen')" :description="__('Gib deine Daten ein und registriere dich')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Vor- und Nachname')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Vor- und Nachname')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('E-Mail Adresse')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Passwort')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Passwort')"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Passwort bestätigen')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Passwort nochmal eingeben')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Account erstellen') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Hast du schon einen Account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Anmelden') }}</flux:link>
        </div>
    </div>
</x-layouts.auth>
