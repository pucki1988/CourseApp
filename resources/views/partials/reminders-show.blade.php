<flux:modal name="reminders" class="md:w-96" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Reminder</flux:heading>
        </div>
        @foreach (($this->showSlot->reminders) ?? [] as $index => $reminder)
        <flux:text><flux:icon.clock /><strong>{{ $reminder->type =='info'?'Info':'Prüfung Mindestteilnehmer'  }}:</strong><br/>{{ $reminder->minutes_before  }} Minuten vor dem Termin</flux:text>
        @endforeach

    </div>
</flux:modal>

