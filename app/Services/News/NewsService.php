<?php

namespace App\Services\News;

use App\Events\NewsItemSaved;
use App\Mail\NewsPublishedMail;
use App\Mail\NewsTestMail;
use App\Models\News\NewsArea;
use App\Models\News\NewsItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class NewsService
{
    public function paginatePublished(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return NewsItem::query()
            ->with(['area', 'creator'])
            ->where('published_at', '<=', now())
            ->where('show_in_blog', true)
            ->whereHas('area', function ($areaQuery) {
                $areaQuery->where('is_active', true);
            })
            ->when(!empty($filters['area']), function ($builder) use ($filters) {
                $builder->whereHas('area', function ($areaQuery) use ($filters) {
                    $areaQuery->where('key', $filters['area']);
                });
            })
            ->when(!empty($filters['tag']), function ($builder) use ($filters) {
                $builder->whereJsonContains('tags', $filters['tag']);
            })
            ->orderByDesc('is_important')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(array $data, ?int $createdBy = null): NewsItem
    {
        $newsItem = NewsItem::query()->create([
            'news_area_id' => (int) $data['news_area_id'],
            'created_by' => $createdBy,
            'title' => $data['title'],
            'message' => $data['message'],
            'is_important' => (bool) ($data['is_important'] ?? false),
            'show_in_blog' => (bool) ($data['show_in_blog'] ?? true),
            'send_mail' => (bool) ($data['send_mail'] ?? true),
            'published_at' => $data['published_at'],
            'tags' => $this->normalizeTags($data['tags'] ?? null),
        ]);

        event(new NewsItemSaved($newsItem));

        return $newsItem;
    }

    public function update(NewsItem $newsItem, array $data): NewsItem
    {
        $newsItem->update([
            'news_area_id' => (int) $data['news_area_id'],
            'title' => $data['title'],
            'message' => $data['message'],
            'is_important' => (bool) ($data['is_important'] ?? false),
            'show_in_blog' => (bool) ($data['show_in_blog'] ?? true),
            'send_mail' => (bool) ($data['send_mail'] ?? true),
            'published_at' => $data['published_at'],
            'tags' => $this->normalizeTags($data['tags'] ?? null),
        ]);

        event(new NewsItemSaved($newsItem->fresh()));

        return $newsItem;
    }

    public function delete(NewsItem $newsItem): void
    {
        $newsItem->delete();
    }

    public function deliver(NewsItem $newsItem): void
    {
        $newsItem->loadMissing(['area']);

        if ($newsItem->sent_at !== null) {
            return;
        }

        if (!$newsItem->send_mail) {
            // Keine Mail versenden, aber sent_at setzen
            $newsItem->forceFill(['sent_at' => now()])->save();
            return;
        }

        $this->recipientQuery($newsItem)
            ->orderBy('id')
            ->chunk(100, function ($users) use ($newsItem) {
                foreach ($users as $user) {
                    Mail::to($user->email)->send(new NewsPublishedMail($newsItem, $user));
                }
            });

        $newsItem->forceFill(['sent_at' => now()])->save();
    }

    public function sendTestMail(array $data, string $email): void
    {
        $area = NewsArea::query()->find((int) $data['news_area_id']);

        Mail::to($email)->send(new NewsTestMail(
            title: $data['title'],
            newsMessage: $data['message'],
            isImportant: (bool) ($data['is_important'] ?? false),
            publishedAt: (string) $data['published_at'],
            newsTags: $this->normalizeTags($data['tags'] ?? null),
            areaName: $area?->name,
        ));
    }

    private function recipientQuery(NewsItem $newsItem): Builder
    {
        return User::query()
            ->whereNotNull('email')
            ->when(!$newsItem->is_important, function (Builder $query) {
                $query->where('receives_news', true);
            });
    }

    private function normalizeTags(?string $tags): array
    {
        if ($tags === null || trim($tags) === '') {
            return [];
        }

        $items = preg_split('/[,\n]+/', $tags) ?: [];

        return array_values(array_unique(array_filter(array_map(fn ($tag) => trim((string) $tag), $items))));
    }
}
