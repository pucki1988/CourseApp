<h2>Kurs abgesagt</h2>
<p>
    Der Termin am <strong>{{ $slot->date->format('d.m.Y') }} | {{ $slot->start_time }}</strong> deines Kurses
    <strong>{{ $booking->course->title }}</strong> wurde vom Kursverantwortlichen leider abgesagt.
</p>
<p>
    Eine Erstattung  des Betrages wurde ausgelöst. Die Rückzahlung auf das verwendete Zahlungsmittel kann einige Tage dauern.
</p>
<p>
    Deine Buchung #{{ $booking->id }} wird entsprechend angepasst. 
</p>
