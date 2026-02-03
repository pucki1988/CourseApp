<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kurs bestätigt</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .slot { padding: 10px 0; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
<p>Hallo {{ $user->name ?? 'Teilnehmer' }},</p>

<p>gute Nachrichten — der Termin am <strong>{{ $slot->date->format('d.m.Y') }} | {{ $slot->start_time->format('H:i') }}</strong>
für den Kurs <strong>{{ $slot->course->title }}</strong> findet statt.</p>

@if($slot->course->equipmentItems && $slot->course->equipmentItems->isNotEmpty())
    <p>Bitte denke daran, folgendes Equipment mitzubringen:</p>
    <ul>
        @foreach($slot->course->equipmentItems as $equipment)
            <li>{{ $equipment->name }}@if($equipment->description) — {{ $equipment->description }} @endif</li>
        @endforeach
    </ul>
@endif

<p><strong>Information</strong><br/>
Im Sportheim stehen dir außerdem <strong>Umkleide- und Duschmöglichkeiten</strong> zur Verfügung.
</p>

<p>Wir freuen uns auf dich!</p>

<p>Viele Grüße</p>
</body>
</html>
