<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Passwort zurücksetzen')" :description="__('Gib dein neues Passwort unten ein.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('E-Mail Adresse')"
                type="email"
                required
                autocomplete="email"
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
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('Passwort zurücksetzen') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts.auth>
