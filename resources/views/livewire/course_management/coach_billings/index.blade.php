<?php

use Livewire\Volt\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Course\CoachMonthlyBilling;

new class extends Component {
    public $billings;
    public $coachProfile;
    public ?int $activeBillingId = null;

    public function mount(): void
    {
        $this->coachProfile = Auth::user()?->coach;

        if (!$this->coachProfile && !Auth::user()->can('courses.manage')) {
            $this->billings = collect();
            return;
        }

        $query=CoachMonthlyBilling::with('items')
            ->orderByDesc('year')
            ->orderByDesc('month');


        if($this->coachProfile) {
            $query->where('coach_id', $this->coachProfile->id);
        }
            

        $this->billings = $query->get();
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
        @if(!$coachProfile && !Auth::user()->can('courses.manage'))
            <div class="border rounded-lg p-4 bg-white shadow-sm text-sm text-gray-600">
                Für deinen Benutzer ist kein Trainerprofil verknüpft. Bitte wende dich an die Verwaltung.
            </div>
        @elseif($billings->isEmpty())
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
    </x-courses.layout>
</section>
