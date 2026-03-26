<?php

namespace App\Jobs;

use App\Models\News\NewsItem;
use App\Services\News\NewsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNewsItem implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $newsItemId)
    {
    }

    public function handle(NewsService $newsService): void
    {
        $newsItem = NewsItem::query()->with('area')->find($this->newsItemId);

        if (!$newsItem || $newsItem->sent_at !== null) {
            return;
        }

        if (!$newsItem->area || !$newsItem->area->is_active) {
            return;
        }

        if ($newsItem->published_at && $newsItem->published_at->isFuture()) {
            self::dispatch($newsItem->id)->delay($newsItem->published_at);
            return;
        }

        $newsService->deliver($newsItem);
    }
}
