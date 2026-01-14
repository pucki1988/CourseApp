<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mitgliedschaft bestätigt</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .slot { padding: 10px 0; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
<p>Hallo {{ $user->name ?? 'Teilnehmer' }},</p>

<p>wir haben deine Vereinsmitgliedschaft bestätigt.</p>
<p>Du profitierst nun vom Bonus für Mitglieder auf die Kursgebühren.</p>
<p>Viele Grüße</p>
</body>
</html>