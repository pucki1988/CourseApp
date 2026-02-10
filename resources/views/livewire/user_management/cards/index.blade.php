<?php

use Livewire\Volt\Component;
use App\Models\Member\Card;
use App\Models\Loyalty\LoyaltyAccount;
use App\Models\Member\Member;
use Illuminate\Support\Str;

new class extends Component {

    public $cards;
    public $members;
    public string $search = '';
    public string $statusFilter = '';
    public int $createCount = 1;
    public ?int $cardToDeleteId = null;
    public ?int $cardToAssignId = null;
    public ?int $memberToAssignId = null;

    public function mount()
    {
        $this->loadCards();
        $this->loadMembers();
    }

    public function updatedSearch()
    {
        $this->loadCards();
    }

    public function updatedStatusFilter()
    {
        $this->loadCards();
    }

    public function loadMembers(): void
    {
        $this->members = Member::with('user')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->createCount = 1;
        Flux::modal('createCards')->show();
    }

    public function createCards(): void
    {
        $count = max(1, (int) $this->createCount);

        for ($i = 0; $i < $count; $i++) {
            Card::create([
                'uuid' => (string) Str::uuid(),
                'active' => false,
            ]);
        }

        $this->loadCards();
        Flux::modal('createCards')->close();
    }

    public function confirmDelete(int $cardId): void
    {
        $this->cardToDeleteId = $cardId;
        Flux::modal('deleteCard')->show();
    }

    public function deleteCard(): void
    {
        if (!$this->cardToDeleteId) {
            return;
        }

        $card = Card::with('loyaltyAccount')->findOrFail($this->cardToDeleteId);
        $account = $card->loyaltyAccount;

        $card->delete();

        if ($account && $account->cards()->count() === 0 && $account->user()->count() === 0) {
            $account->delete();
        }

        $this->cardToDeleteId = null;
        $this->loadCards();
        Flux::modal('deleteCard')->close();
    }

    public function openAssignModal(int $cardId): void
    {
        $this->cardToAssignId = $cardId;
        $this->memberToAssignId = null;
        Flux::modal('assignCard')->show();
    }

    public function assignCard(): void
    {
        if (!$this->cardToAssignId || !$this->memberToAssignId) {
            return;
        }

        $card = Card::findOrFail($this->cardToAssignId);
        $member = Member::with('user')->findOrFail($this->memberToAssignId);

        $accountId = null;
        if ($member->user && $member->user->loyalty_account_id) {
            $accountId = $member->user->loyalty_account_id;
        }elseif($member->cards()->count() > 0) {
            $accountId = $member->cards()->first()->loyaltyAccount->id;
        }elseif (!$member->user && $member->cards()->count() === 0) {
            $accountId = LoyaltyAccount::create(['type' => 'card'])->id;
        }

        $card->update([
            'member_id' => $member->id,
            'loyalty_account_id' => $accountId,
            'active' => true,
        ]);

        $this->cardToAssignId = null;
        $this->memberToAssignId = null;
        $this->loadCards();
        Flux::modal('assignCard')->close();
    }

    public function unassignCard(int $cardId): void
    {
        $card = Card::findOrFail($cardId);

        $account = $card->loyaltyAccount;
        $clearAccount = false;
        if ($account && $account->cards()->count() === 1 && $account->user()->count() === 0) {
            $clearAccount = true;
        }
        
        $card->update([
            'member_id' => null,
            'loyalty_account_id' => null,
            'active' => false,
        ]);

        if ($clearAccount) {
            $account->delete();
        }


        $this->loadCards();
    }

    public function blockCard(int $cardId): void
    {
        $card = Card::findOrFail($cardId);
        $card->revoke();

        $this->loadCards();
    }

    private function loadCards(): void
    {
        $this->cards = $this->cardsQuery()
            ->orderByDesc('created_at')
            ->get();
    }

    private function cardsQuery()
    {
        $query = Card::with(['member.user', 'loyaltyAccount']);

        if (!empty($this->statusFilter)) {
            $query->where('active', $this->statusFilter === 'active');
        }

        if (!empty($this->search)) {
            $search = trim($this->search);
            $query->where('uuid', 'like', "%{$search}%")
                ->orWhereHas('member', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
        }

        return $query;
    }

    public function exportCsv()
    {
        $cards = $this->cardsQuery()
            ->orderByDesc('created_at')
            ->get();

        return response()->streamDownload(function () use ($cards) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'uuid',
                'member_first_name',
                'member_last_name',
                'user_name',
                'active',
                'revoked_at',
                'created_at',
            ], ';');

            foreach ($cards as $card) {
                fputcsv($out, [
                    $card->uuid,
                    $card->member?->first_name,
                    $card->member?->last_name,
                    $card->member?->user?->name,
                    $card->active ? '1' : '0',
                    $card->revoked_at?->format('Y-m-d H:i:s'),
                    $card->created_at?->format('Y-m-d H:i:s'),
                ], ';');
            }

            fclose($out);
        }, 'cards.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

};
?>

<section class="w-full">
    @include('partials.users-heading')

    <x-users.layout :heading="__('Karten')" :subheading="__('Übersicht')">
        <div class="flex md:justify-end gap-2 mb-3">
            <flux:button variant="ghost" wire:click="exportCsv">CSV Export</flux:button>
            <flux:button icon="plus" wire:click="openCreateModal">Karten erstellen</flux:button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Suche nach UUID oder Mitglied…"
                icon="magnifying-glass"
            />
            <flux:select wire:model.live="statusFilter" placeholder="Status filtern">
                <flux:select.option value="">Alle</flux:select.option>
                <flux:select.option value="active">Aktiv</flux:select.option>
                <flux:select.option value="inactive">Inaktiv</flux:select.option>
            </flux:select>
            
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
            @foreach ($cards as $card)
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                    <div class="text-sm">
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">UUID</span>
                            <span>{{ $card->uuid }}</span>
                        </div>

                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Mitglied</span>
                            <span>
                                {{ $card->member?->first_name }} {{ $card->member?->last_name }}
                            </span>
                        </div>

                    

                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Status</span>
                            <span>
                                <flux:badge size="sm" color="{{ $card->active ? 'green' : 'red' }}">
                                    {{ $card->active ? 'aktiv' : 'gesperrt' }}
                                </flux:badge>
                            </span>
                        </div>

                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Gesperrt am</span>
                            <span>{{ $card->revoked_at?->format('d.m.Y H:i') ?? '-' }}</span>
                        </div>

                        <div class="flex justify-end mt-2">
                            @if(!$card->member_id)
                                <flux:button size="xs" variant="ghost" wire:click="openAssignModal({{ $card->id }})">
                                    Zuordnen
                                </flux:button>
                            @endif
                            @if($card->member_id)
                                <flux:button size="xs" variant="ghost" wire:click="unassignCard({{ $card->id }})">
                                    Zuordnung lösen
                                </flux:button>
                            @endif
                            @if($card->active)
                                <flux:button size="xs" variant="ghost" wire:click="blockCard({{ $card->id }})">
                                    Sperren
                                </flux:button>
                            @endif
                            <flux:button size="xs" variant="danger" wire:click="confirmDelete({{ $card->id }})">
                                Löschen
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </x-users.layout>

    <flux:modal name="createCards">
        <flux:heading size="lg">Karten erstellen</flux:heading>
        <flux:text class="mt-2">Wie viele Karten sollen erstellt werden?</flux:text>

        <div class="mt-4">
            <flux:input
                type="number"
                min="1"
                wire:model.live="createCount"
                label="Anzahl"
            />
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="createCards">Erstellen</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="deleteCard">
        <flux:heading size="lg">Karte löschen</flux:heading>
        <flux:text class="mt-2">Soll die Karte wirklich gelöscht werden?</flux:text>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="deleteCard">Löschen</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="assignCard">
        <flux:heading size="lg">Karte zuordnen</flux:heading>
        <flux:text class="mt-2">Bitte Mitglied auswählen.</flux:text>

        <div class="mt-4">
            <flux:select wire:model="memberToAssignId" placeholder="Mitglied auswählen">
                @foreach ($members as $member)
                    <flux:select.option :value="$member->id">
                        {{ $member->last_name }}, {{ $member->first_name }}
                        @if($member->user)
                            ({{ $member->user->name }})
                        @endif
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" color="green" wire:click="assignCard">Zuordnen</flux:button>
        </div>
    </flux:modal>
</section>
