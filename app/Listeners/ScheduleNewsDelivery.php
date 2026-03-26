<?php

namespace App\Listeners;

use App\Events\NewsItemSaved;
use App\Jobs\SendNewsItem;

class ScheduleNewsDelivery
{
    public function handle(NewsItemSaved $event): void
    {
        $newsItem = $event->newsItem;

        if ($newsItem->sent_at !== null) {
            return;
        }

        $job = SendNewsItem::dispatch($newsItem->id);

        if ($newsItem->published_at && $newsItem->published_at->isFuture()) {
            $job->delay($newsItem->published_at);
        }
    }
}
