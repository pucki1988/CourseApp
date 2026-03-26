@if ($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid gap-4 md:grid-cols-2">
    <flux:field>
        <flux:label>Bereich</flux:label>
        <flux:select wire:model="news_area_id">
            <flux:select.option value="">Bitte wählen</flux:select.option>
            @foreach ($this->areas as $newsArea)
                <flux:select.option :value="$newsArea->id">{{ $newsArea->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>

    <flux:field>
        <flux:label>Datum</flux:label>
        <input
            type="datetime-local"
            wire:model="published_at"
            class="w-full rounded-md border-zinc-300 text-sm"
        >
    </flux:field>
</div>

<flux:input wire:model="title" label="Titel" maxlength="255" />

<flux:textarea wire:model="message" label="Nachricht" rows="7" />

<flux:checkbox wire:model="is_important" :label="__('Wichtige News an alle User senden')" />

<flux:input
    wire:model="tags"
    label="Tags (Komma-getrennt)"
    placeholder="z. B. wichtig, anmeldung, trainer"
/>

<flux:input
    wire:model="test_email"
    label="Test-E-Mail"
    placeholder="z. B. test@example.de"
    type="email"
/>
