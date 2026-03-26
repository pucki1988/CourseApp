<?php

use App\Models\News\NewsArea;
use App\Services\News\NewsAreaService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $newsAreaId = null;
    public ?int $deleteAreaId = null;
    public string $key = '';
    public string $name = '';
    public bool $is_active = true;
    public int $sort_order = 0;

    public function getAreasProperty()
    {
        return NewsArea::query()
            ->withCount('newsItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->newsAreaId = null;
        $this->key = '';
        $this->name = '';
        $this->is_active = true;
        $this->sort_order = 0;

        Flux::modal('area-form')->show();
    }

    public function openEdit(int $areaId): void
    {
        $this->resetValidation();

        $area = NewsArea::query()->findOrFail($areaId);

        $this->newsAreaId = $area->id;
        $this->key = $area->key;
        $this->name = $area->name;
        $this->is_active = (bool) $area->is_active;
        $this->sort_order = (int) $area->sort_order;

        Flux::modal('area-form')->show();
    }

    public function save(NewsAreaService $newsAreaService): void
    {
        $data = $this->validate([
            'key' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('news_areas', 'key')->ignore($this->newsAreaId)],
            'name' => ['required', 'string', 'max:255', Rule::unique('news_areas', 'name')->ignore($this->newsAreaId)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        if ($this->newsAreaId) {
            $newsAreaService->update(NewsArea::query()->findOrFail($this->newsAreaId), $data);
            session()->flash('status', 'Bereich wurde aktualisiert.');
        } else {
            $newsAreaService->create($data);
            session()->flash('status', 'Bereich wurde erstellt.');
        }

        Flux::modal('area-form')->close();
    }

    public function openDelete(int $areaId): void
    {
        $this->deleteAreaId = $areaId;
        Flux::modal('area-delete')->show();
    }

    public function delete(NewsAreaService $newsAreaService): void
    {
        if (!$this->deleteAreaId) {
            return;
        }

        try {
            $newsAreaService->delete(NewsArea::query()->findOrFail($this->deleteAreaId));
            session()->flash('status', 'Bereich wurde gelöscht.');
        } catch (\DomainException $e) {
            session()->flash('status_error', 'Bereich kann nicht gelöscht werden, da News zugeordnet sind.');
        }

        Flux::modal('area-delete')->close();
    }
};
?>

<section class="w-full">
    @include('partials.news-heading')
    <x-news.layout :heading="__('News Bereiche')" :subheading="__('Bereiche verwalten')">
        <div class="space-y-4">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('status_error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('status_error') }}
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button icon="plus" wire:click="openCreate">Neuer Bereich</flux:button>
            </div>

            <div class="grid auto-rows-min gap-4 xl:grid-cols-3">
                @foreach ($this->areas as $area)
                    <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm space-y-1">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Name</span>
                                <span>{{ $area->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Key</span>
                                <span>{{ $area->key }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Sortierung</span>
                                <span>{{ $area->sort_order }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Status</span>
                                <span>{{ $area->is_active ? 'Aktiv' : 'Inaktiv' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">News</span>
                                <span>{{ $area->news_items_count }}</span>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <flux:button size="xs" wire:click="openEdit({{ $area->id }})">Bearbeiten</flux:button>
                                <flux:button size="xs" variant="danger" wire:click="openDelete({{ $area->id }})">Löschen</flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-news.layout>

    <flux:modal name="area-form" :dismissible="false">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $newsAreaId ? 'Bereich bearbeiten' : 'Bereich anlegen' }}</flux:heading>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <flux:input wire:model="name" label="Name" placeholder="z. B. Course" />
            <flux:input wire:model="key" label="Key" placeholder="z. B. course" />
            <flux:input type="number" min="0" wire:model="sort_order" label="Sortierung" />
            <flux:field variant="inline">
                <flux:checkbox wire:model="is_active" />
                <flux:label>Aktiv</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="save">Speichern</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="area-delete" :dismissible="false">
        <div class="space-y-4">
            <flux:heading size="lg">Bereich löschen</flux:heading>
            <flux:text>Möchtest du diesen Bereich wirklich löschen?</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="danger" wire:click="delete">Löschen</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
