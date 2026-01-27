<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bezahlung der Buchung</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .slot { padding: 10px 0; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
<p>Hallo {{ $booking->user->name ?? 'Teilnehmer' }},</p>
<p>wir haben die Zahlung für deine Buchung #{{ $booking->id }} erhalten.</p>
<p>Wir freuen uns auf deine Teilnahme.</p>
<p>Viele Grüße</p>
</body>
</html>