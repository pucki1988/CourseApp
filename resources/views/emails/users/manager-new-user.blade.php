<h2>Neuer User registriert</h2>

<p><strong>Name:</strong> {{ $user->name }}</p>
<p><strong>E-Mail:</strong> {{ $user->email }}</p>

@if ($user->member_requested)
    <p>Mitgliedschaft beantragt</p>
@endif