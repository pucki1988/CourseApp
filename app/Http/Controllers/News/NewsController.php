<?php

namespace App\Http\Controllers\News;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Services\News\NewsService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request, NewsService $newsService)
    {
        $validated = $request->validate([
            'area' => ['nullable', 'string', 'max:50'],
            'tag' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return NewsResource::collection($newsService->paginatePublished($validated))->resolve();
    }
}
