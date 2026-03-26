<?php

namespace App\Events;

use App\Models\News\NewsItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewsItemSaved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public NewsItem $newsItem)
    {
    }
}
