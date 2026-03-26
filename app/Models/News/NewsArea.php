<?php

namespace App\Models\News;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsArea extends Model
{
    protected $fillable = [
        'key',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function newsItems(): HasMany
    {
        return $this->hasMany(NewsItem::class);
    }
}
