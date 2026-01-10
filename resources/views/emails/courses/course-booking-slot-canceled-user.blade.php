<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neue Kursbuchung</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .slot { padding: 10px 0; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
<p>Hallo {{ $booking->user->name ?? 'Teilnehmer' }},</p>
<p>der Termin deines Kurs wurde storniert</p>
<p>
    Termin:  <strong>{{ $bookingSlot->slot->date->format('d.m.Y') }} | {{ $bookingSlot->slot->start_time->format('H:i') }}</strong><br/>
    Kurs: <strong>{{ $booking->course->title }}</strong>
</p>
<p>
    Eine Erstattung des Betrages wurde ausgelöst. Die Rückzahlung auf das verwendete Zahlungsmittel kann einige Tage dauern.
</p>
<p>
    Deine Buchung #{{ $booking->id }} wird entsprechend angepasst.
</p>
<p>Viele Grüße</p>

</body>
</html>