<h2>Kurs abgesagt</h2>

<p>
    Der Termin <strong>{{ $slot->date->format('d.m.Y') }}</strong>
    von <strong>{{ $slot->start_time }}</strong>
    wurde leider abgesagt.
</p>

<p>
    Deine Buchung #{{ $booking->id }} wird entsprechend angepasst.
</p>
