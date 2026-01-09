<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Absage eines Termins</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .slot { padding: 10px 0; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
<p>Hallo {{ $booking->user->name ?? 'Teilnehmer' }},</p>
<p>
    der Termin am <strong>{{ $slot->date->format('d.m.Y') }} | {{ $slot->start_time }}</strong> deines Kurses
    <strong>{{ $booking->course->title }}</strong> wurde abgesagt.
</p>

<p>Grund: {{ $reason }}</p>
<p>
    Eine Erstattung  des Betrages wurde ausgelöst. Die Rückzahlung auf das verwendete Zahlungsmittel kann einige Tage dauern.
</p>
<p>
    Deine Buchung #{{ $booking->id }} wird entsprechend angepasst. 
</p>
<p>Viele Grüße</p>
</body>
</html>