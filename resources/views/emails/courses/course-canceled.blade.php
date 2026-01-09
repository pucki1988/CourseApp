
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Absage des Kurses</title>
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
    dein Kurs <strong>{{ $booking->course->title }}</strong> wurde komplett abgesagt.
</p>
<p>Grund: {{ $reason }}</p>
<h4>Stornierte Termine</h4>
<div>
@foreach($booking->bookingSlots as $index =>$bs)
<div class="slot">
<strong>{{ ($index + 1) }}</strong> || 
{{ \Carbon\Carbon::parse($bs->slot->date)->format('d.m.Y') }} | 
{{ $bs->slot->start_time->format('H:i') }} Uhr
</div>
@endforeach
</div>
<p class="price">Gesamtbetrag der Rückzahlung € {{ number_format($booking->total_price, 2, ',', '.') }}</p>
<p><strong>Hinweis:</strong> Die Rückerstattung auf das verwendete Zahlungsmittel wurde angestoßen. Die Rückzahlung kann jedoch einige Tage dauern.</p>
<p>
    Deine Buchung #{{ $booking->id }} wird entsprechend angepasst. 
</p>
<p>Viele Grüße</p>
</body>
</html>