<h2>Termin des Kurs storniert</h2>

<p>
    Du hast den Termin  <strong>{{ $bookingSlot->slot->date->format('d.m.Y') }}</strong> | <strong>{{ $bookingSlot->slot->start_time->format('H:i') }}</strong>
    deines Kurses <strong>{{ $bookingSlot->booking->course->title }}</strong> abgesagt.
</p>
<p>
    Eine Erstattung des Betrages wurde ausgelöst. Die Rückzahlung auf das verwendete Zahlungsmittel kann einige Tage dauern.
</p>
<p>
    Deine Buchung #{{ $booking->id }} wird entsprechend angepasst.
</p>
<p>Viele Grüße</p>