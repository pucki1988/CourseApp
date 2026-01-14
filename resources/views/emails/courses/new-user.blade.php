<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neuer User</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .slot { padding: 10px 0; }
        .slot:last-child { border-bottom: none; }
        .price { font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>
<p>Hallo {{ $user->name ?? 'Teilnehmer' }},</p>
<p>Herzlich Willkommen!</p>
<p>
Du hast dich erfolgreich registriert und kannst nun deine ersten Kurse buchen.
</p>
@if($user->member_requested)
<p>
<strong>Hinweis</strong><br/>
Deine Vereinsmitgliedschaft muss erst bestätigt werden. Das kann etwas Zeit in Anspruch nehmen. 
Erst nach der Bestätigung der Vereinsmitgliedschaft erhälst du bei der Kursbuchung den Bonus für Mitglieder.
</p>
@endif


<p>Viele Grüße</p>
</body>
</html>