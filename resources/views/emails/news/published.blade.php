<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $newsItem->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; color: #18181b; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-important { background: #fee2e2; color: #991b1b; }
        .tags { margin-top: 16px; }
        .tag { display: inline-block; background: #3f3f46 ; color: #f4f4f5; border-radius: 999px; padding: 4px 10px; font-size: 12px; }
    </style>
</head>
<body>
@if($newsItem->is_important)
    <p><span class="badge badge-important">Wichtige News</span></p>
@endif  
<p><strong>Hallo {{ $user->name }},</strong></p>

<div>{!! nl2br(e($newsItem->message)) !!}</div>
<p>Viele Grüße</p>
@if(!empty($newsItem->tags))
    <div class="tags">
        <span class="tag">{{ \Illuminate\Support\Carbon::parse($newsItem->published_at)->format('d.m.Y') }}</span>
        @foreach($newsItem->tags as $tag)
            <span class="tag">{{ $tag }}</span>
        @endforeach
    </div>
@endif
</body>
</html>
