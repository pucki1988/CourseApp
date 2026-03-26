<?php

use App\Models\News\NewsArea;
use App\Models\News\NewsItem;
use App\Services\News\NewsService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $area = '';
    public int $perPage = 15;
    public ?int $editingNewsId = null;
    public ?int $news_area_id = null;
    public string $title = '';
    public string $message = '';
    public bool $is_important = false;
    public string $published_at = '';
    public string $tags = '';
    public string $test_email = '';

    public function mount(): void
    {
        $this->published_at = now()->format('Y-m-d\TH:i');
    }

    public function updatedArea(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function getAreasProperty()
    {
        return NewsArea::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getNewsProperty()
    {
        return NewsItem::query()
            ->with(['area', 'creator'])
            ->when($this->area !== '', function ($query) {
                $query->whereHas('area', fn ($areaQuery) => $areaQuery->where('key', $this->area));
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function delete(int $newsId, NewsService $newsService): void
    {
        $newsService->delete(NewsItem::query()->findOrFail($newsId));

        session()->flash('status', 'News wurde gelöscht.');
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->editingNewsId = null;
        $this->news_area_id = null;
        $this->title = '';
        $this->message = '';
        $this->is_important = false;
        $this->published_at = now()->format('Y-m-d\TH:i');
        $this->tags = '';
        $this->test_email = '';

        Flux::modal('news-form')->show();
    }

    public function openEditModal(int $newsId): void
    {
        $this->resetValidation();

        $news = NewsItem::query()->findOrFail($newsId);

        $this->editingNewsId = $news->id;
        $this->news_area_id = $news->news_area_id;
        $this->title = $news->title;
        $this->message = $news->message;
        $this->is_important = (bool) $news->is_important;
        $this->published_at = optional($news->published_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->tags = implode(', ', $news->tags ?? []);
        $this->test_email = '';

        Flux::modal('news-form')->show();
    }

    public function save(NewsService $newsService): void
    {
        $data = $this->validate([
            'news_area_id' => ['required', 'exists:news_areas,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'is_important' => ['boolean'],
            'published_at' => ['required', 'date'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->editingNewsId) {
            $newsService->update(NewsItem::query()->findOrFail($this->editingNewsId), $data);

            session()->flash('status', 'News wurde aktualisiert.');
        } else {
            $newsService->create($data, Auth::id());

            session()->flash('status', 'News wurde erstellt.');
        }

        Flux::modal('news-form')->close();
    }

    public function sendTest(NewsService $newsService): void
    {
        $data = $this->validate([
            'news_area_id' => ['required', 'exists:news_areas,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'is_important' => ['boolean'],
            'published_at' => ['required', 'date'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $newsService->sendTestMail($data, $data['test_email']);

        Flux::toast('Test-Mail wurde gesendet.');
    }
};
?>

<section class="w-full">
    @include('partials.news-heading')
    <x-news.layout :heading="__('News')">
        
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
            
            <div class="text-end">
                <flux:button wire:click="openCreateModal">
                    Neue Nachricht
                </flux:button>
            </div>

            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="grid gap-3 md:grid-cols-4 md:items-end">
                    <flux:field>
                        <flux:label>Bereich</flux:label>
                        <flux:select wire:model.live="area">
                            <flux:select.option value="">Alle Bereiche</flux:select.option>
                            @foreach ($this->areas as $newsArea)
                                <flux:select.option :value="$newsArea->key">{{ $newsArea->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Pro Seite</flux:label>
                        <flux:select wire:model.live="perPage">
                            <flux:select.option value="15">15</flux:select.option>
                            <flux:select.option value="30">30</flux:select.option>
                            <flux:select.option value="60">60</flux:select.option>
                        </flux:select>
                    </flux:field>

                    <div class="text-sm text-gray-500 md:col-span-2 md:text-end">
                        {{ $this->news->total() }}
                        {{ $this->news->total() === 1 ? 'Eintrag' : 'Einträge' }}
                    </div>
                </div>
            </div>

            @php($newsItems = $this->news)
            <div class="flex flex-col gap-6 mt-6">
                @forelse ($newsItems as $item)
                    <article class="rounded-xl border bg-white p-6 shadow-sm flex flex-col gap-3">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-semibold text-zinc-900">{{ $item->title }}</h2>
                                    @if ($item->is_important)
                                        <flux:badge size="sm" color="red">Wichtig</flux:badge>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500">
                                    <flux:badge size="sm" color="blue">{{ $item->area?->name ?? '-' }}</flux:badge>
                                    <flux:badge size="sm" color="green">{{ $item->published_at?->format('d.m.Y H:i') }}</flux:badge>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button size="xs" wire:click="openEditModal({{ $item->id }})">
                                    Bearbeiten
                                </flux:button>
                                <flux:button
                                    size="xs"
                                    variant="danger"
                                    x-on:click="if (confirm('News wirklich löschen?')) { $wire.delete({{ $item->id }}) }"
                                >
                                    Löschen
                                </flux:button>
                            </div>
                        </div>

                        

                        @if (!empty($item->tags))
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach ($item->tags as $tag)
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-xl border bg-white p-6 text-sm text-zinc-600 shadow-sm">
                        Keine News vorhanden.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $newsItems->links() }}
            </div>

            <flux:modal name="news-form" flyout>
                <div class="space-y-4">
                    <flux:heading size="lg">{{ $editingNewsId ? 'News bearbeiten' : 'News erstellen' }}</flux:heading>

                    <form wire:submit="save" class="space-y-4">
                        @include('livewire.news_management.news.partials.form-fields')

                        <div class="flex items-center gap-2">
                            <flux:button type="submit">{{ $editingNewsId ? 'Aktualisieren' : 'Speichern' }}</flux:button>
                            <flux:button type="button" variant="filled" wire:click="sendTest">Test senden</flux:button>
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                            </flux:modal.close>
                        </div>
                    </form>
                </div>
            </flux:modal>
        
    </x-news.layout>
</section>
