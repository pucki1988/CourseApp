<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'is_important' => (bool) $this->is_important,
            'published_at' => optional($this->published_at)?->toIso8601String(),
            'tags' => $this->tags ?? [],
            'area' => [
                'id' => $this->area?->id,
                'key' => $this->area?->key,
                'name' => $this->area?->name,
            ],
            'created_by' => $this->creator?->id,
            'created_by_name' => $this->creator?->name,
        ];
    }
}
