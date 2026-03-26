<?php

namespace App\Models\News;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItem extends Model
{
    protected $fillable = [
        'news_area_id',
        'created_by',
        'title',
        'message',
        'is_important',
        'published_at',
        'sent_at',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'is_important' => 'boolean',
            'published_at' => 'datetime',
            'sent_at' => 'datetime',
            'tags' => 'array',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(NewsArea::class, 'news_area_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
