<?php

use Livewire\Volt\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use App\Models\Course\Coach;
use App\Models\Course\CoachMonthlyBilling;

new class extends Component {
    public $billings;
    public $coaches;
    public $coachProfile;
    public ?int $activeBillingId = null;
    public string $billingMonth = '';
    public ?string $runCoachId = '';
    public ?string $filterCoachId = '';
    public ?string $filterMonth = '';
    public bool $runDryRun = false;
    public bool $runForce = false;
    public ?string $runOutput = null;
    public string $runState = 'idle';

    public function mount(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $this->coachProfile = $user?->coach;
        $this->billingMonth = now()->subMonth()->format('Y-m');
        $this->coaches = collect();
        $this->filterMonth = '';
        $this->filterCoachId = '';

        if ($user?->can('courses.manage')) {
            $this->coaches = Coach::query()->orderBy('name')->get();
        }

        $this->loadBillings();
    }

    public function loadBillings(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $this->coachProfile = $user?->coach;

        if (!$this->coachProfile && !$user?->can('courses.manage')) {
            $this->billings = collect();
            return;
        }

        $query=CoachMonthlyBilling::with(['items', 'coach'])
            ->orderByDesc('year')
            ->orderByDesc('month');


        if($this->coachProfile) {
            $query->where('coach_id', $this->coachProfile->id);
        } elseif (!empty($this->filterCoachId)) {
            $query->where('coach_id', (int) $this->filterCoachId);
        }

        if (!empty($this->filterMonth)) {
            [$year, $month] = array_map('intval', explode('-', $this->filterMonth));
            $query->where('year', $year)
                ->where('month', $month);
        }
            

        $this->billings = $query->get();
    }

    public function applyFilters(): void
    {
        $this->validate([
            'filterCoachId' => ['nullable', 'integer', 'exists:coaches,id'],
            'filterMonth' => ['nullable', 'date_format:Y-m'],
        ]);

        $this->loadBillings();
    }

    public function resetFilters(): void
    {
        $this->filterCoachId = '';
        $this->filterMonth = '';
        $this->loadBillings();
    }

    public function runBilling(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_unless($user?->can('courses.manage'), 403);

        $this->validate([
            'billingMonth' => ['required', 'date_format:Y-m'],
            'runCoachId' => ['nullable', 'integer', 'exists:coaches,id'],
        ]);

        try {
            $args = [
                '--month' => $this->billingMonth,
            ];

            if (!empty($this->runCoachId)) {
                $args['--coach'] = (int) $this->runCoachId;
            }

            if ($this->runDryRun) {
                $args['--dry-run'] = true;
            }

            if ($this->runForce) {
                $args['--force'] = true;
            }

            Artisan::call('coaches:generate-billing', $args);

            $this->runOutput = trim(Artisan::output());
            $this->runState = 'success';
            $this->loadBillings();
        } catch (\Throwable $e) {
            $this->runState = 'error';
            $this->runOutput = $e->getMessage();
        }
    }

    public function deleteBilling(int $billingId): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_unless($user?->can('courses.manage'), 403);

        $billing = CoachMonthlyBilling::findOrFail($billingId);
        $billing->delete();

        $this->runState = 'success';
        $this->runOutput = 'Abrechnung wurde gelöscht.';
        $this->loadBillings();
    }

    public function rerunBillingForce(int $billingId): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_unless($user?->can('courses.manage'), 403);

        $billing = CoachMonthlyBilling::findOrFail($billingId);
        $month = sprintf('%04d-%02d', $billing->year, $billing->month);

        try {
            Artisan::call('coaches:generate-billing', [
                '--month' => $month,
                '--coach' => $billing->coach_id,
                '--force' => true,
            ]);

            $this->runOutput = trim(Artisan::output());
            $this->runState = 'success';
            $this->loadBillings();
        } catch (\Throwable $e) {
            $this->runState = 'error';
            $this->runOutput = $e->getMessage();
        }
    }

    public function toggleDetails(int $billingId): void
    {
        $this->activeBillingId = $this->activeBillingId === $billingId ? null : $billingId;
    }

    public function formatPeriod($billing): string
    {
        return Carbon::create($billing->year, $billing->month, 1)
            ->locale('de')
            ->translatedFormat('F Y');
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'emailed' => 'Per E-Mail versendet',
            'dry_run' => 'Testlauf',
            'skipped_no_email' => 'Keine E-Mail-Adresse',
            'email_failed' => 'E-Mail fehlgeschlagen',
            default => 'Erstellt',
        };
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'emailed' => 'green',
            'dry_run' => 'zinc',
            'skipped_no_email' => 'yellow',
            'email_failed' => 'red',
            default => 'blue',
        };
    }
};
?>

<section class="w-full">
    @include('partials.courses-heading')

    <x-courses.layout :heading="__('Meine Abrechnungen')" :subheading="__('Monatliche Trainerauszahlungen')">
        @can('courses.manage')
            <div class="border rounded-lg p-4 bg-white shadow-sm mb-4">
                <form wire:submit="runBilling" class="flex flex-col md:flex-row md:items-end gap-3">
                    <flux:input type="month" label="Abrechnungsmonat" wire:model="billingMonth" />
                    <flux:field>
                        <flux:label>Trainer</flux:label>
                        <flux:select wire:model="runCoachId">
                            <flux:select.option value="">Alle</flux:select.option>
                            @foreach($coaches as $coach)
                                <flux:select.option value="{{ $coach->id }}">{{ $coach->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    <flux:field variant="inline">
                        <flux:checkbox wire:model="runDryRun" />
                        <flux:label>Testlauf</flux:label>
                    </flux:field>
                    
                    <flux:button type="submit" variant="primary" icon="play">Abrechnung starten</flux:button>
                </form>

                @if($runState === 'success')
                    <flux:callout variant="success" icon="check-circle" class="mt-3" heading="Abrechnung wurde gestartet" />
                @elseif($runState === 'error')
                    <flux:callout variant="danger" icon="exclamation-circle" class="mt-3" heading="Abrechnung konnte nicht gestartet werden" />
                @endif

                @if($runOutput)
                    <div class="mt-3 text-xs text-gray-600 whitespace-pre-line">{{ $runOutput }}</div>
                @endif
            </div>
        @endcan

        @if(!$coachProfile && !Auth::user()->can('courses.manage'))
            <div class="border rounded-lg p-4 bg-white shadow-sm text-sm text-gray-600">
                Für deinen Benutzer ist kein Trainerprofil verknüpft. Bitte wende dich an die Verwaltung.
            </div>
        @else
            <div class="border rounded-lg p-4 bg-white shadow-sm mb-4">
                <form wire:submit="applyFilters" class="flex flex-col md:flex-row md:items-end gap-3">
                    <flux:input type="month" label="Monat filtern" wire:model="filterMonth" />

                    @can('courses.manage')
                        <flux:field>
                            <flux:label>Trainer filtern</flux:label>
                            <flux:select wire:model="filterCoachId">
                                <flux:select.option value="">Alle</flux:select.option>
                                @foreach($coaches as $coach)
                                    <flux:select.option value="{{ $coach->id }}">{{ $coach->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @endcan

                    <flux:button type="submit" variant="primary" icon="funnel">Filter anwenden</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="resetFilters">Zurücksetzen</flux:button>
                </form>
            </div>

            @if($billings->isEmpty())
                <div class="border rounded-lg p-4 bg-white shadow-sm text-sm text-gray-600">
                    Es sind noch keine Monatsabrechnungen vorhanden.
                </div>
            @else
                <div class="space-y-4">
                @foreach($billings as $billing)
                    <div class="border rounded-lg p-4 bg-white shadow-sm">
                        <div class="flex justify-between items-start gap-3">
                            <div>
                                <flux:heading size="lg">{{ $this->formatPeriod($billing) }}</flux:heading>
                                <div class="mt-2 text-sm text-gray-600 space-y-1">
                                    <div>Trainer: <strong>{{ $billing->coach->name }}</strong></div>
                                    <div>Termine: <strong>{{ $billing->total_slots }}</strong></div>

                                    <div>Gesamtauszahlung: <strong>{{ number_format($billing->total_compensation, 2, ',', '.') }} €</strong></div>
                                    @if($billing->mail_sent_at)
                                        <div>Versendet am: {{ $billing->mail_sent_at->format('d.m.Y H:i') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <flux:badge size="sm" :color="$this->statusColor($billing->status)">
                                    {{ $this->statusLabel($billing->status) }}
                                </flux:badge>
                                <flux:button size="sm" wire:click="toggleDetails({{ $billing->id }})">
                                    {{ $activeBillingId === $billing->id ? 'Details ausblenden' : 'Details anzeigen' }}
                                </flux:button>
                                @can('courses.manage')
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        wire:click="rerunBillingForce({{ $billing->id }})"
                                        onclick="return confirm('Abrechnung für diesen Monat und Trainer per Force neu berechnen?')"
                                    >
                                    @if($billing->status === 'dry_run')
                                        Produktiv berechnen
                                    @else
                                        Neu berechnen
                                    @endif
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="deleteBilling({{ $billing->id }})"
                                        onclick="return confirm('Abrechnung wirklich löschen?')"
                                    >
                                        Löschen
                                    </flux:button>
                                @endcan
                            </div>
                        </div>

                        @if($activeBillingId === $billing->id)
                            <div class="mt-4 border-t pt-4 space-y-2">
                                @forelse($billing->items as $item)
                                    <div class="flex justify-between items-center text-sm border rounded-md p-2">
                                        <div>
                                            <div class="font-medium">{{ $item->course_title }}</div>
                                            <div class="text-gray-500">
                                                {{ $item->date->format('d.m.Y') }} · {{ $item->start_time?->format('H:i') }} - {{ $item->end_time?->format('H:i') }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div>Teilnehmer: {{ $item->participant_count }}</div>
                                            <div class="font-semibold">{{ number_format($item->compensation, 2, ',', '.') }} €</div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-sm text-gray-500">Keine Terminpositionen vorhanden.</div>
                                @endforelse

                                @if($billing->notes)
                                    <div class="text-xs text-gray-500 pt-2">Hinweis: {{ $billing->notes }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
                </div>
            @endif
        @endif
    </x-courses.layout>
</section>
