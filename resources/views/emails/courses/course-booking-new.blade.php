@php
    $totalPrice = $booking->course->price * count($booking->bookingSlots);
@endphp

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neue Kursbuchung</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px; }
        .card { background-color: #fff; border-radius: 6px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #343a40; }
        .slot { padding: 10px 0; border-bottom: 1px solid #dee2e6; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Neue Buchung #{{ $booking->id}} für den Kurs: {{ $booking->course->title }}</h2>
        <p>Hallo {{ $booking->user->name ?? 'Teilnehmer' }},</p>
        <p>Vielen Dank für deine Buchung! Hier sind die Details:</p>

        <h4>Gebuchte Termine:</h4>
        <div>
            @foreach($booking->bookingSlots as $bs)
                <div class="slot">
                    {{ \Carbon\Carbon::parse($bs->slot->date)->format('d.m.Y') }} 
                    {{ $bs->slot->start_time->format('H:i') }} Uhr
                </div>
            @endforeach
        </div>

        <p class="price">Gesamtpreis: € {{ number_format($booking->total_price, 2, ',', '.') }}</p>

        <p>Wir freuen uns auf deine Teilnahme!</p>

        <p>Viele Grüße,<br>
        Dein Kurs-Team</p>
    </div>
</body>
</html>