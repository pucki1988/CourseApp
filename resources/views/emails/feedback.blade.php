<h2>Neues Feedback</h2>

@if(!empty($feedbackName))
    <p><strong>Name:</strong> {{ $feedbackName }}</p>
@endif

@if(!empty($feedbackEmail))
    <p><strong>Absender:</strong> {{ $feedbackEmail }}</p>
@endif

<p><strong>Nachricht:</strong></p>
<p>{{ $feedbackMessage }}</p>


