<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neue Kursbuchung</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .slot { padding: 10px 0; border-bottom: 1px solid #dee2e6; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
<p>Hallo {{ $booking->user->name ?? 'Teilnehmer' }},</p>
<p>vielen Dank für deine Buchung.</p>
<h4>Details:</h4>
<p>
Kurs: <strong>{{ $booking->course->title }}</strong><br/>
Buchung: <strong>#{{ $booking->id }}</strong><br/>
Ort: <strong>{{ $booking->course->location }}</strong>
</p>
<h4>Gebuchte Termine</h4>
<div>
@foreach($booking->bookingSlots as $index =>$bs)
<div class="slot">
{{ $index }} || 
{{ \Carbon\Carbon::parse($bs->slot->date)->format('d.m.Y') }} | 
{{ $bs->slot->start_time->format('H:i') }} Uhr
</div>
@endforeach
</div>
<p class="price">Gesamtpreis: € {{ number_format($booking->total_price, 2, ',', '.') }}</p>
<p><strong>Hinweis:</strong> Die Buchung gilt erst nach Abschluss des Zahlungsvorgangs als vollständig.</p>
<p>Wir freuen uns auf deine Teilnahme.</p>
<p>Viele Grüße</p>
</div>
</body>
</html>