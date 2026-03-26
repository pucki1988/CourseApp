<?php

namespace App\Services\News;

use App\Models\News\NewsArea;
use DomainException;

class NewsAreaService
{
    public function create(array $data): NewsArea
    {
        return NewsArea::query()->create($this->mapPayload($data));
    }

    public function update(NewsArea $newsArea, array $data): NewsArea
    {
        $newsArea->update($this->mapPayload($data));

        return $newsArea;
    }

    public function delete(NewsArea $newsArea): void
    {
        $newsArea->loadCount('newsItems');

        if ($newsArea->news_items_count > 0) {
            throw new DomainException('Bereich kann nicht gelöscht werden, da News zugeordnet sind.');
        }

        $newsArea->delete();
    }

    private function mapPayload(array $data): array
    {
        return [
            'key' => strtolower(trim((string) $data['key'])),
            'name' => trim((string) $data['name']),
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) $data['sort_order'],
        ];
    }
}
