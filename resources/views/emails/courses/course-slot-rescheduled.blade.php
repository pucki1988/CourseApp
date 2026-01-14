<h2>Termin verschoben</h2>
<p>
    Der Termin deines Kurses <strong>{{ $slot->course->title }}</strong>  wurde vom Kursverantwortlichen verschoben.
    <p><strong>Alter Termin</strong><br/>
    {{ $oldData["date"]->format('d.m.Y') }} | {{ $oldData["start_time"]->format('H:i') }}
    </p>
    <p><strong>Neuer Termin</strong><br/>
    {{ $slot->date->format('d.m.Y') }} | {{ $slot->start_time->format('H:i') }}
    </p>

</p>
<p>
    Wenn du am neuen Termin verhindert bist, kannst du bis zu 24 Stunden vor dem neuen Termin deine Buchung kostenfrei stornieren.
</p>

<p>Viele Grüße</p>
