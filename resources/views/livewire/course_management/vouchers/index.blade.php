<?php

use BeyondCode\Vouchers\Models\Voucher;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {

    use WithPagination;

    public string $search = '';
    public string $statusFilter = ''; // '' = alle, open, redeemed, expired
    public int $perPage = 10;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function getVouchersProperty()
    {
        return Voucher::query()
            ->with(['users.users'])
            ->when($this->search !== '', function ($query) {
                $term = '%' . $this->search . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('code', 'like', $term)
                        ->orWhere('data->customer_email', 'like', $term)
                        ->orWhere('data->order_id', 'like', $term);
                });
            })
            ->when($this->statusFilter === 'redeemed', fn ($q) => $q->whereHas('users'))
            ->when($this->statusFilter === 'expired', fn ($q) => $q->whereDoesntHave('users')
                ->whereNotNull('expires_at')->where('expires_at', '<=', now()))
            ->when($this->statusFilter === 'open', fn ($q) => $q->whereDoesntHave('users')
                ->where(fn ($e) => $e->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->latest('id')
            ->paginate($this->perPage);
    }

};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Gutscheine')" :subheading="__('Übersicht der Gutscheine')">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <flux:field>
                <flux:label>Suche</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Code, E-Mail oder Bestellnummer…"
                    icon="magnifying-glass"
                />
            </flux:field>

            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="">Alle</flux:select.option>
                    <flux:select.option value="open">Offen</flux:select.option>
                    <flux:select.option value="redeemed">Eingelöst</flux:select.option>
                    <flux:select.option value="expired">Abgelaufen / Storniert</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Pro Seite</flux:label>
                <flux:select wire:model.live="perPage">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
            </flux:field>
        </div>

        @php($vouchers = $this->vouchers)
        <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @forelse($vouchers as $voucher)
                @php($redeemedBy = $voucher->users->first())
                @php($status = $redeemedBy ? 'redeemed' : ($voucher->isExpired() ? 'expired' : 'open'))
                <div class="border rounded-lg p-3 bg-white shadow-sm text-sm">
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Code</span>
                        <span class="font-mono">{{ $voucher->code }}</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Betrag</span>
                        <span>€ {{ number_format(((int) $voucher->data->get('amount', 0)) / 100, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Bestellung</span>
                        <span>{{ $voucher->data->get('order_id', '-') }}</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">E-Mail</span>
                        <span>{{ $voucher->data->get('customer_email', '-') }}</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Erstellt</span>
                        <span>{{ $voucher->created_at?->format('d.m.Y') }}</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Gültig bis</span>
                        <span>{{ $voucher->expires_at?->format('d.m.Y') ?? '-' }}</span>
                    </div>
                    @if($redeemedBy)
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Eingelöst von</span>
                            <span>{{ $redeemedBy->users->first()?->name ?? 'Account #' . $redeemedBy->id }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Eingelöst am</span>
                            <span>{{ \Illuminate\Support\Carbon::parse($redeemedBy->pivot->redeemed_at)->format('d.m.Y H:i') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Status</span>
                        <span>
                            <flux:badge size="sm" color="{{ $status === 'redeemed' ? 'green' : ($status === 'open' ? 'blue' : 'red') }}">
                                {{ $status === 'redeemed' ? 'Eingelöst' : ($status === 'open' ? 'Offen' : 'Abgelaufen / Storniert') }}
                            </flux:badge>
                        </span>
                    </div>
                </div>
            @empty
                <flux:text>Keine Gutscheine gefunden</flux:text>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $vouchers->links() }}
        </div>

    </x-courses.layout>
</section>
