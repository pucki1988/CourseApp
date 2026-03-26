<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Test-News</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; color: #18181b; }
        .test-hint { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-important { background: #fee2e2; color: #991b1b; }
        .tags { margin-top: 16px; }
        .tag { display: inline-block; background: #3f3f46 ; color: #f4f4f5; border-radius: 999px; padding: 4px 10px; font-size: 12px; }
    </style>
</head>
<body>
<div class="test-hint">Dies ist eine Test-Mail. Diese Nachricht wurde nicht an die User-Empfänger verteilt.</div>


@if($isImportant)
<p>
<span class="badge badge-important">Wichtige News</span>
</p>
@endif

<p><strong>{{ $title }}</strong></p>

<p>Hallo Test Testerin,</p>

<div>{!! nl2br(e($newsMessage)) !!}</div>

<p>
Viele Grüße
</p>

@if(!empty($newsTags))
    <div class="tags">
        <span class="tag">{{ \Illuminate\Support\Carbon::parse($publishedAt)->format('d.m.Y') }}</span>
        @foreach($newsTags as $tag)
            <span class="tag">{{ $tag }}</span>
        @endforeach
    </div>
@endif




</body>
</html>
