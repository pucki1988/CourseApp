<?php

use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $name = '';
    public string $code = '';
    public string $type = 'percentage';
    public string $value = '';
    public string $buy = '2';
    public string $get = '1';
    public string $getDiscountPercentage = '100';
    public string $maxFreeItems = '';
    public string $startsAt = '';
    public string $expiresAt = '';
    public string $usageLimit = '';
    public string $usageLimitPerUser = '';

    public function createCoupon(): void
    {
        Gate::authorize('courses.manage');
        $this->code = strtoupper(trim($this->code));
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'regex:/^[A-Z0-9_-]+$/', Rule::unique((new Discount)->getTable(), 'code')],
            'type' => ['required', Rule::in(['percentage', 'fixed', 'buy_x_get_y'])],
            'value' => [$this->type === 'buy_x_get_y' ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'gt:0', $this->type === 'percentage' ? 'max:100' : 'max:99999999.99'],
            'buy' => [$this->type === 'buy_x_get_y' ? 'required' : 'nullable', 'integer', 'min:1', 'max:100000'],
            'get' => [$this->type === 'buy_x_get_y' ? 'required' : 'nullable', 'integer', 'min:1', 'max:100000'],
            'getDiscountPercentage' => ['exclude_unless:type,buy_x_get_y', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
            'maxFreeItems' => ['exclude_unless:type,buy_x_get_y', 'nullable', 'integer', 'min:1', 'max:100000'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => array_filter(['nullable', 'date', $this->startsAt !== '' ? 'after:startsAt' : null]),
            'usageLimit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'usageLimitPerUser' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
        ]);

        Discount::query()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $data['type'] === 'buy_x_get_y' ? 0 : $data['value'],
            'conditions' => $data['type'] === 'buy_x_get_y' ? array_filter([
                'buy' => (int) $data['buy'],
                'get' => (int) $data['get'],
                'get_discount_percentage' => (float) $data['getDiscountPercentage'],
                'max_free_items' => filled($data['maxFreeItems'] ?? null) ? (int) $data['maxFreeItems'] : null,
            ], fn ($value) => $value !== null) : null,
            'currency' => 'EUR',
            'starts_at' => $data['startsAt'] ?: null,
            'expires_at' => $data['expiresAt'] ?: null,
            'usage_limit' => $data['usageLimit'] ?: null,
            'usage_limit_per_user' => $data['usageLimitPerUser'] ?: null,
        ]);

        $this->reset('name', 'code', 'type', 'value', 'buy', 'get', 'getDiscountPercentage', 'maxFreeItems', 'startsAt', 'expiresAt', 'usageLimit', 'usageLimitPerUser');
        $this->resetPage();
        session()->flash('coupon_message', 'Coupon wurde erstellt.');
    }

    public function toggleCoupon(int $id): void
    {
        Gate::authorize('courses.manage');
        $coupon = Discount::query()
            ->whereNotNull('code')
            ->whereIn('type', [DiscountType::Percentage->value, DiscountType::Fixed->value, DiscountType::BuyXGetY->value])
            ->findOrFail($id);
        $coupon->update(['is_active' => ! $coupon->is_active]);
    }

    public function with(): array
    {
        return ['coupons' => Discount::query()
            ->whereIn('type', [DiscountType::Percentage->value, DiscountType::Fixed->value, DiscountType::BuyXGetY->value])
            ->whereNotNull('code')->latest('id')->paginate(10)];
    }
};
?>

<section class="w-full">
    @include('partials.courses-heading')
    <x-courses.layout :heading="__('Rabattcoupons')" :subheading="__('Coupons für Kursbuchungen verwalten')">
        @if (session('coupon_message'))
            <flux:text>{{ session('coupon_message') }}</flux:text>
        @endif
        <form wire:submit="createCoupon" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <flux:input wire:model="name" label="Name" required />
            <flux:input wire:model="code" label="Coupon-Code" description="Buchstaben, Zahlen, Bindestrich und Unterstrich" required />
            <flux:select wire:model.live="type" label="Rabattart">
                <flux:select.option value="percentage">Prozent</flux:select.option>
                <flux:select.option value="fixed">Festbetrag in Euro</flux:select.option>
                <flux:select.option value="buy_x_get_y">Kauf X Bekomme Y</flux:select.option>
            </flux:select>
            @if ($type === 'buy_x_get_y')
                <flux:input wire:model="buy" label="Zu zahlende Termine (X)" type="number" min="1" required />
                <flux:input wire:model="get" label="Rabattierte Termine pro Gruppe (Y)" type="number" min="1" required />
                <flux:input wire:model="getDiscountPercentage" label="Maximaler Rabatt für Termine (%)" type="number" min="0.01" max="100" step="0.01" required />
                <flux:input wire:model="maxFreeItems" label="Maximal rabattierte Termine pro Buchung" description="Leer lassen für unbegrenzt" type="number" min="1" step="1" />
                <flux:text>Der Rabatt wird anhand des Durchschnittspreises aller gebuchten Termine berechnet. Bei einer Ganzkursbuchung zählt der Kurs als eine Einheit.</flux:text>
            @elseif ($type === 'fixed')
                <flux:input wire:model="value" label="Rabattwert in €" type="number" min="0.01" step="0.01" required />
            @elseif ($type === 'percentage' )
                <flux:input wire:model="value" label="Rabattwert in %" type="number" min="0.01" step="0.01" required />
            @endif
            <flux:input wire:model="startsAt" label="Gültig ab" type="datetime-local" />
            <flux:input wire:model="expiresAt" label="Gültig bis" type="datetime-local" />
            <flux:input wire:model="usageLimit" label="Maximale Einlösungen insgesamt" type="number" min="1" />
            <flux:input wire:model="usageLimitPerUser" label="Maximale Einlösungen pro Benutzer" type="number" min="1" />
            <div><flux:button type="submit" variant="primary" class="my-2" >Coupon erstellen</flux:button></div>
        </form>

        <div class="space-y-3">
            @forelse ($coupons as $coupon)
                <div wire:key="coupon-{{ $coupon->id }}" class="border rounded-lg p-4">
                    <div class="font-semibold">{{ $coupon->name }} ({{ $coupon->code }})</div>
                    <flux:text>
                        @if ($coupon->type === DiscountType::BuyXGetY)
                            <flux:badge size="sm">{{ $coupon->conditions['buy'] }} kaufen</flux:badge> <flux:badge size="sm">{{ $coupon->conditions['get'] }} mit {{ $coupon->conditions['get_discount_percentage'] ?? 100 }} % Rabatt erhalten</flux:badge> 
                            <flux:badge size="sm">{{ $coupon->conditions['max_free_items']  ?? 'Unbegrenzt' }} Rabattierte Termine pro Buchung</flux:badge>
                        @else
                            {{ $coupon->value }} {{ $coupon->type === DiscountType::Percentage ? '%' : 'EUR' }}
                        @endif
                        <flux:text>
                        Einlösungen<flux:badge size="sm">{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</flux:badge>
                        </flux:text>
                        <flux:text>
                        Gültig
                        <flux:badge size="sm" color="{{ $coupon->is_active ? 'green' : 'red' }}">{{ $coupon->is_active ? 'Aktiv' : 'Inaktiv' }}</flux:badge>
                        <flux:badge size="sm">{{ $coupon->starts_at?->format('d.m.Y H:i') ?? 'sofort' }}</flux:badge> bis <flux:badge size="sm">{{ $coupon->expires_at?->format('d.m.Y H:i') ?? 'unbegrenzt' }}</flux:badge>
                        </flux:text>    
                    </flux:text>    
                    <flux:button class="my-2"  wire:click="toggleCoupon({{ $coupon->id }})" size="sm">{{ $coupon->is_active ? 'Deaktivieren' : 'Aktivieren' }}</flux:button>
                </div>
            @empty
                <flux:text>Keine Coupons vorhanden.</flux:text>
            @endforelse
        </div>
        <div class="mt-4">{{ $coupons->links() }}</div>
    </x-courses.layout>
</section>
