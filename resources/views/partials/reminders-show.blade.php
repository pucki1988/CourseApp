<flux:modal name="reminders" class="md:w-96" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Reminder</flux:heading>
        </div>
        @foreach (($this->showSlot->reminders) ?? [] as $index => $reminder)

         <div class="relative  rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 flex flex-col justify-between">

            <div class="flex">
                <div class="flex-1">
                    <flux:heading size="lg">{{ $reminder->type =='info'?'Info':'Prüfung Mindestteilnehmer'  }}
                    </flux:heading>
                    <div class="mb-2">
                    <flux:tooltip content="Ausführzeitpunkt">
                    <flux:badge size="sm">{{ $this->showSlot->startDateTime()->copy()->subMinutes($reminder->minutes_before)->format('d.m.Y H:i')}}</flux:badge>
                    </flux:tooltip>
                    <flux:badge size="sm">{{ $reminder->minutes_before  }} Minuten vorher</flux:badge>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

    </div>
</flux:modal>

