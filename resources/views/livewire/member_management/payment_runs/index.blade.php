<?php

use Livewire\Volt\Component;
use App\Models\Member\PaymentRun;
use App\Models\Member\MembershipPayment;
use App\Services\Member\PaymentRunService;
use Flux\Flux;
use Illuminate\Support\Carbon;

new class extends Component {

    public $paymentRuns;
    public $pendingSummary;
    
    public ?int $selectedRunId = null;
    public ?string $executionDate = null;
    public ?string $notes = null;
    public ?string $bankReference = null;
    
    public ?int $viewRunId = null;
    public $viewRunPayments = [];
    
    public function mount(): void
    {
        $this->loadData();
        $this->executionDate = now()->addDays(7)->format('Y-m-d');
    }

    private function loadData(): void
    {
        $this->paymentRuns = PaymentRun::with(['creator', 'payments'])
            ->orderByDesc('created_at')
            ->get();
            
        $service = app(PaymentRunService::class);
        $this->pendingSummary = $service->getPendingPaymentsSummary();
    }

    public function openCreateModal(): void
    {
        $this->executionDate = now()->addDays(7)->format('Y-m-d');
        $this->notes = '';
        $this->bankReference = 'Hauptkonto';
        Flux::modal('create-run-modal')->show();
    }

    public function createRun(): void
    {
        $this->validate([
            'executionDate' => 'required|date',
        ], [
            'executionDate.required' => 'Ausführungsdatum ist erforderlich',
        ]);

        try {
            $service = app(PaymentRunService::class);
            $run = $service->createPaymentRun(
                Carbon::parse($this->executionDate),
                $this->notes
            );
            
            // Automatisch alle offenen Zahlungen bis zum Ausführungsdatum hinzufügen
            $count = $service->addPendingPayments($run, Carbon::parse($this->executionDate));
            
            $this->loadData();
            Flux::modal('create-run-modal')->close();
            
            session()->flash('success', "Einzugslauf erstellt mit {$count} Zahlungen");
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Erstellen: ' . $e->getMessage());
        }
    }

    public function submitRun(int $runId): void
    {
        try {
            $service = app(PaymentRunService::class);
            $run = PaymentRun::findOrFail($runId);
            
            $service->submitPaymentRun($run, $this->bankReference ?? 'Hauptkonto');
            
            $this->loadData();
            session()->flash('success', 'Einzugslauf eingereicht und Journal-Eintrag erstellt');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function completeRun(int $runId): void
    {
        try {
            $service = app(PaymentRunService::class);
            $run = PaymentRun::findOrFail($runId);
            
            $service->completePaymentRun($run);
            
            $this->loadData();
            session()->flash('success', 'Einzugslauf abgeschlossen - alle Zahlungen als bezahlt markiert');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function cancelRun(int $runId): void
    {
        try {
            $service = app(PaymentRunService::class);
            $run = PaymentRun::findOrFail($runId);
            
            $service->cancelPaymentRun($run);
            
            $this->loadData();
            session()->flash('success', 'Einzugslauf storniert');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function openViewPayments(int $runId): void
    {
        $run = PaymentRun::with(['payments.membership.type', 'payments.membership.payer', 'payments.bankAccount'])
            ->findOrFail($runId);
            
        $this->viewRunId = $runId;
        $this->viewRunPayments = $run->payments->sortBy('due_date');
        
        Flux::modal('view-payments-modal')->show();
    }

    public function closeViewPayments(): void
    {
        $this->viewRunId = null;
        $this->viewRunPayments = [];
        Flux::modal('view-payments-modal')->close();
    }
};
?>

<section class="w-full">
    @include('partials.members-heading')

    <x-members.layout heading="Einzugsläufe">

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded text-center">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded text-center">
                {{ session('error') }}
            </div>
        @endif

        <!-- Statistik Box -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="border rounded-lg p-4 bg-white shadow-sm">
                <div class="text-sm text-gray-500">Offene Zahlungen</div>
                <div class="text-2xl font-bold">{{ $pendingSummary['count'] ?? 0 }}</div>
            </div>
            <div class="border rounded-lg p-4 bg-white shadow-sm">
                <div class="text-sm text-gray-500">Gesamtbetrag offen</div>
                <div class="text-2xl font-bold">{{ number_format($pendingSummary['total_amount'] ?? 0, 2, ',', '.') }} €</div>
            </div>
            <div class="border rounded-lg p-4 bg-white shadow-sm">
                <div class="text-sm text-gray-500">Älteste offene Zahlung</div>
                <div class="text-lg font-semibold">
                    {{ $pendingSummary['oldest_date'] ? \Carbon\Carbon::parse($pendingSummary['oldest_date'])->format('d.m.Y') : '-' }}
                </div>
            </div>
        </div>

        <!-- Header mit Button -->
        <div class="flex justify-between items-center mb-4">
            <flux:heading size="lg">Alle Einzugsläufe</flux:heading>
            <flux:button icon="plus" wire:click="openCreateModal">Neuer Einzugslauf</flux:button>
        </div>

        <!-- Payment Runs Liste -->
        <div class="border rounded-lg bg-white shadow-sm">
            @if($paymentRuns->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    Noch keine Einzugsläufe erstellt
                </div>
            @else
                <div class="divide-y">
                    @foreach($paymentRuns as $run)
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-semibold text-lg">{{ $run->reference }}</span>
                                        
                                        @php
                                            $statusColors = [
                                                'draft' => 'zinc',
                                                'submitted' => 'blue',
                                                'completed' => 'green',
                                                'cancelled' => 'red',
                                            ];
                                            $statusLabels = [
                                                'draft' => 'Entwurf',
                                                'submitted' => 'Eingereicht',
                                                'completed' => 'Abgeschlossen',
                                                'cancelled' => 'Storniert',
                                            ];
                                        @endphp
                                        
                                        <flux:badge size="sm" :color="$statusColors[$run->status] ?? 'zinc'">
                                            {{ $statusLabels[$run->status] ?? $run->status }}
                                        </flux:badge>
                                    </div>
                                    
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div>Ausführungsdatum: <strong>{{ $run->execution_date->format('d.m.Y') }}</strong></div>
                                        <div>Zahlungen: <strong>{{ $run->payment_count }}</strong> · Betrag: <strong>{{ number_format($run->total_amount, 2, ',', '.') }} €</strong></div>
                                        @if($run->notes)
                                            <div>Notiz: {{ $run->notes }}</div>
                                        @endif
                                        @if($run->submitted_at)
                                            <div>Eingereicht: {{ $run->submitted_at->format('d.m.Y H:i') }}</div>
                                        @endif
                                        @if($run->completed_at)
                                            <div>Abgeschlossen: {{ $run->completed_at->format('d.m.Y H:i') }}</div>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="flex gap-2">
                                    <flux:button size="sm" icon="eye" wire:click="openViewPayments({{ $run->id }})">
                                        Zahlungen
                                    </flux:button>
                                    
                                    @if($run->status === 'draft')
                                        <flux:button size="sm" variant="primary" wire:click="submitRun({{ $run->id }})">
                                            Einreichen
                                        </flux:button>
                                    @endif
                                    
                                    @if($run->status === 'submitted')
                                        <flux:button size="sm" variant="primary" wire:click="completeRun({{ $run->id }})">
                                            Abschließen
                                        </flux:button>
                                    @endif
                                    
                                    @if(in_array($run->status, ['draft', 'submitted']))
                                        <flux:button size="sm" variant="danger" wire:click="cancelRun({{ $run->id }})">
                                            Stornieren
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </x-members.layout>

    <!-- Modal: Neuer Einzugslauf -->
    <flux:modal name="create-run-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Neuer Einzugslauf</flux:heading>
                <flux:text class="mt-2">Erstellt automatisch einen Einzugslauf mit allen offenen Zahlungen bis zum gewählten Datum.</flux:text>
            </div>

            <form wire:submit.prevent="createRun" class="space-y-4">
                <flux:input
                    label="Ausführungsdatum"
                    type="date"
                    wire:model="executionDate"
                />

                <flux:input
                    label="Bankkonto-Referenz"
                    wire:model="bankReference"
                    placeholder="z.B. Sparkasse-Hauptkonto"
                />

                <flux:input
                    label="Notizen (optional)"
                    wire:model="notes"
                    placeholder="z.B. Monatlicher Einzug März 2026"
                />

                <div class="bg-blue-50 border border-blue-200 rounded p-3 text-sm">
                    <strong>Hinweis:</strong> Es werden automatisch alle offenen Zahlungen mit Bankverbindung bis zum gewählten Datum hinzugefügt 
                    (aktuell {{ $pendingSummary['count'] ?? 0 }} Zahlungen verfügbar).
                </div>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="$dispatch('close-modal', {name: 'create-run-modal'})">
                        Abbrechen
                    </flux:button>
                    <flux:button type="submit" variant="primary">Erstellen</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal: Zahlungen anzeigen -->
    <flux:modal name="view-payments-modal" :dismissible="false" class="max-w-4xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Zahlungen im Einzugslauf</flux:heading>
            </div>

            @if(empty($viewRunPayments) || count($viewRunPayments) === 0)
                <div class="text-sm text-gray-500">Keine Zahlungen vorhanden</div>
            @else
                <div class="space-y-2 max-h-96 overflow-auto border rounded-md p-3">
                    @foreach($viewRunPayments as $payment)
                        @php
                            $iban = $payment->bankAccount?->iban;
                            $maskedIban = $iban ? substr($iban, 0, 4) . ' **** **** ' . substr($iban, -4) : '-';
                        @endphp
                        <div class="flex items-center justify-between border-b pb-2 last:border-b-0 last:pb-0">
                            <div class="text-sm flex-1">
                                <div class="font-semibold">
                                    {{ $payment->membership->payer->first_name ?? '' }} {{ $payment->membership->payer->last_name ?? '' }}
                                </div>
                                <div class="text-gray-500">
                                    {{ $payment->membership->type->name ?? '-' }} · 
                                    Fällig: {{ $payment->due_date?->format('d.m.Y') ?? '-' }} · 
                                    {{ number_format($payment->amount, 2, ',', '.') }} €
                                </div>
                                <div class="text-gray-400 text-xs">
                                    {{ $maskedIban }}
                                </div>
                            </div>
                            <div>
                                @if($payment->status === 'paid')
                                    <flux:badge size="sm" color="green">Bezahlt</flux:badge>
                                @elseif($payment->status === 'pending')
                                    <flux:badge size="sm" color="yellow">Offen</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red">Storniert</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2 pt-4">
                <flux:spacer />
                <flux:button type="button" variant="ghost" wire:click="closeViewPayments">
                    Schließen
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
