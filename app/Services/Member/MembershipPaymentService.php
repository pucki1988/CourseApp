<?php

namespace App\Services\Member;

use App\Models\Member\Membership;
use App\Models\Member\MembershipPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MembershipPaymentService
{
    /**
     * Generate recurring payments for all active memberships
     * 
     * @param Carbon|null $referenceDate Date to check against (default: today)
     * @param int $monthsAhead How many months ahead to generate (default: 1)
     * @param bool $dryRun If true, don't actually create payments, just return what would be created
     * @return Collection Generated payments
     */
     /**
     * Generate recurring payments for all active memberships
     */
    public function generateRecurringPayments(
        ?Carbon $referenceDate = null, 
        int $monthsAhead = 1,
        bool $dryRun = false
    ): Collection
    {
        $referenceDate = $referenceDate ?? Carbon::today();
        $generatedPayments = collect();

        $memberships = Membership::with(['type', 'payer', 'payer.bankAccounts'])
            ->whereHas('type', function ($query) {
                $query->where('billing_mode', 'recurring');
            })
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->get();

        foreach ($memberships as $membership) {
            // 1. RÜCKWIRKENDE ZAHLUNGEN: Von Start bis heute
            $backfilled = $this->backfillMissingPayments($membership, $referenceDate, $dryRun);
            $generatedPayments = $generatedPayments->merge($backfilled);

            // 2. ZUKÜNFTIGE ZAHLUNGEN: Von heute bis +monthsAhead
            for ($i = 0; $i <= $monthsAhead; $i++) {
                $checkDate = $referenceDate->copy()->addMonths($i);
                
                if ($this->isDueForPayment($membership, $checkDate)) {
                    $payment = $this->createPaymentForMembership($membership, $checkDate, $dryRun);
                    if ($payment) {
                        // Bei dry-run: Prüfe ob wir diese Periode schon haben
                        if ($dryRun) {
                            $isDuplicate = $generatedPayments->contains(function ($existingPayment) use ($payment) {
                                return $existingPayment->membership_id === $payment->membership_id
                                    && $existingPayment->period_start->eq($payment->period_start)
                                    && $existingPayment->period_end->eq($payment->period_end);
                            });
                            
                            if (!$isDuplicate) {
                                $generatedPayments->push($payment);
                            }
                        } else {
                            $generatedPayments->push($payment);
                        }
                    }
                }
            }
        }

        return $generatedPayments;
    }

    /**
     * Backfill missing payments from start date to today
     */
    protected function backfillMissingPayments(
        Membership $membership,
        Carbon $upToDate,
        bool $dryRun = false
    ): Collection
    {
        $generatedPayments = collect();
        $startDate = Carbon::parse($membership->started_at);
        $billingInterval = $membership->billing_interval;

        // Nicht rückwirkend erstellen wenn noch nicht gestartet
        if ($startDate->isAfter($upToDate)) {
            return $generatedPayments;
        }

        // Je nach Billing Interval: Alle fehlenden Zahlungen berechnen
        switch ($billingInterval) {
            case 'monthly':
                $generatedPayments = $this->backfillMonthly($membership, $startDate, $upToDate, $dryRun);
                break;
            case 'quarterly':
                $generatedPayments = $this->backfillQuarterly($membership, $startDate, $upToDate, $dryRun);
                break;
            case 'semi_annual':
                $generatedPayments = $this->backfillSemiAnnual($membership, $startDate, $upToDate, $dryRun);
                break;
            case 'annual':
                $generatedPayments = $this->backfillAnnual($membership, $startDate, $upToDate, $dryRun);
                break;
        }

        return $generatedPayments;
    }

    /**
     * Backfill monthly payments
     */
    protected function backfillMonthly(
        Membership $membership,
        Carbon $startDate,
        Carbon $upToDate,
        bool $dryRun
    ): Collection
    {
        $generatedPayments = collect();
        $currentDate = $startDate->copy()->startOfMonth();

        while ($currentDate <= $upToDate) {
            $periodStart = $currentDate->copy()->startOfMonth();
            $periodEnd = $currentDate->copy()->endOfMonth();

            if (!$this->paymentExistsForPeriod($membership, $periodStart, $periodEnd)) {
                $payment = $this->createPaymentForMembership($membership, $currentDate, $dryRun);
                if ($payment) {
                    $generatedPayments->push($payment);
                }
            }

            $currentDate->addMonth();
        }

        return $generatedPayments;
    }

    /**
     * Backfill quarterly payments
     */
    protected function backfillQuarterly(
        Membership $membership,
        Carbon $startDate,
        Carbon $upToDate,
        bool $dryRun
    ): Collection
    {
        $generatedPayments = collect();
        $currentDate = $startDate->copy()->startOfMonth();
        $monthsSinceStart = 0;

        while ($currentDate <= $upToDate) {
            // Nur alle 3 Monate
            if ($monthsSinceStart % 3 === 0) {
                $period = $this->calculateQuarterlyPeriod($startDate, $currentDate);

                if (!$this->paymentExistsForPeriod($membership, $period['start'], $period['end'])) {
                    $payment = $this->createPaymentForMembership($membership, $currentDate, $dryRun);
                    if ($payment) {
                        $generatedPayments->push($payment);
                    }
                }

                $currentDate->addMonths(3);
                $monthsSinceStart += 3;
            } else {
                $currentDate->addMonth();
                $monthsSinceStart++;
            }
        }

        return $generatedPayments;
    }

    /**
     * Backfill semi-annual payments
     */
    protected function backfillSemiAnnual(
        Membership $membership,
        Carbon $startDate,
        Carbon $upToDate,
        bool $dryRun
    ): Collection
    {
        $generatedPayments = collect();
        $currentDate = $startDate->copy()->startOfMonth();
        $monthsSinceStart = 0;

        while ($currentDate <= $upToDate) {
            // Nur alle 6 Monate
            if ($monthsSinceStart % 6 === 0) {
                $period = $this->calculateSemiAnnualPeriod($startDate, $currentDate);

                if (!$this->paymentExistsForPeriod($membership, $period['start'], $period['end'])) {
                    $payment = $this->createPaymentForMembership($membership, $currentDate, $dryRun);
                    if ($payment) {
                        $generatedPayments->push($payment);
                    }
                }

                $currentDate->addMonths(6);
                $monthsSinceStart += 6;
            } else {
                $currentDate->addMonth();
                $monthsSinceStart++;
            }
        }

        return $generatedPayments;
    }

    /**
     * Backfill annual payments - nur für VOLLSTÄNDIG ABGESCHLOSSENE Kalenderjahre
     */
    protected function backfillAnnual(
        Membership $membership,
        Carbon $startDate,
        Carbon $upToDate,
        bool $dryRun
    ): Collection
    {
        $generatedPayments = collect();
        
        // Beginne mit dem Start-Jahr
        $year = $startDate->year;
        $currentYear = $upToDate->year;
        
        // Gehe durch alle VERGANGENEN Kalenderjahre (nicht das aktuelle)
        while ($year < $currentYear) {
            $checkDate = Carbon::create($year, 1, 1);
            $period = $this->calculateAnnualPeriod($startDate, $checkDate);

            if (!$this->paymentExistsForPeriod($membership, $period['start'], $period['end'])) {
                $payment = $this->createPaymentForMembership($membership, $checkDate, $dryRun);
                if ($payment) {
                    $generatedPayments->push($payment);
                }
            }

            $year++;
        }

        return $generatedPayments;
    }


    /**
     * Check if a membership is due for a payment in the given month
     */
    protected function isDueForPayment(Membership $membership, Carbon $checkDate): bool
    {
        $startDate = Carbon::parse($membership->started_at);
        $billingInterval = $membership->billing_interval;

        return match($billingInterval) {
            'monthly' => $this->isMonthlyDue($membership, $checkDate),
            'quarterly' => $this->isQuarterlyDue($membership, $checkDate),
            'semi_annual' => $this->isSemiAnnualDue($membership, $checkDate),
            'annual' => $this->isAnnualDue($membership, $checkDate),
            default => false,
        };
    }

    /**
     * Check if monthly payment is due
     */
    protected function isMonthlyDue(Membership $membership, Carbon $checkDate): bool
    {
        $periodStart = $checkDate->copy()->startOfMonth();
        $periodEnd = $checkDate->copy()->endOfMonth();
        
        return !$this->paymentExistsForPeriod($membership, $periodStart, $periodEnd);
    }

    /**
     * Check if quarterly payment is due
     */
    protected function isQuarterlyDue(Membership $membership, Carbon $checkDate): bool
    {
        $startDate = Carbon::parse($membership->started_at);
        $monthsSinceStart = $startDate->diffInMonths($checkDate);
        
        if ($monthsSinceStart < 0 || $monthsSinceStart % 3 !== 0) {
            return false;
        }
        
        $period = $this->calculateQuarterlyPeriod($startDate, $checkDate);
        return !$this->paymentExistsForPeriod($membership, $period['start'], $period['end']);
    }

    /**
     * Check if semi-annual payment is due
     */
    protected function isSemiAnnualDue(Membership $membership, Carbon $checkDate): bool
    {
        $startDate = Carbon::parse($membership->started_at);
        $monthsSinceStart = $startDate->diffInMonths($checkDate);
        
        if ($monthsSinceStart < 0 || $monthsSinceStart % 6 !== 0) {
            return false;
        }
        
        $period = $this->calculateSemiAnnualPeriod($startDate, $checkDate);
        return !$this->paymentExistsForPeriod($membership, $period['start'], $period['end']);
    }

    /**
     * Check if annual payment is due - immer für das Kalenderjahr
     */
    protected function isAnnualDue(Membership $membership, Carbon $checkDate): bool
    {
        $startDate = Carbon::parse($membership->started_at);
        
        // Nicht vor Start-Jahr
        if ($checkDate->year < $startDate->year) {
            return false;
        }
        
        // Prüfe ob für das Jahr des checkDate eine Zahlung fehlt
        $period = $this->calculateAnnualPeriod($startDate, $checkDate);
        return !$this->paymentExistsForPeriod($membership, $period['start'], $period['end']);
    }

    /**
     * Check if payment already exists for the given period
     */
    protected function paymentExistsForPeriod(Membership $membership, Carbon $periodStart, Carbon $periodEnd): bool
    {
        return MembershipPayment::where('membership_id', $membership->id)
            ->whereDate('period_start', $periodStart->format('Y-m-d'))
            ->whereDate('period_end', $periodEnd->format('Y-m-d'))
            ->exists();
    }

    /**
     * Create a payment for a membership
     */
    protected function createPaymentForMembership(
        Membership $membership, 
        Carbon $dueDate,
        bool $dryRun = false
    ): ?MembershipPayment
    {
        $period = $this->calculatePaymentPeriod($membership, $dueDate);
        
        if (!$period) {
            return null;
        }

        if ($this->paymentExistsForPeriod($membership, $period['start'], $period['end'])) {
            return null;
        }

        // base_amount ist bereits der korrekte Betrag für die Abrechnung
        $amount = $membership->type->base_amount ?? 0;
        
        // Bei jährlicher Abrechnung: Prüfe ob anteilige Berechnung nötig ist
        if ($membership->billing_interval === 'annual') {
            $periodStartMonth = $period['start']->month;
            $periodEndMonth = $period['end']->month;
            
            // Wenn Periode nicht von Januar bis Dezember läuft → anteilig
            if ($periodStartMonth !== 1 || $periodEndMonth !== 12) {
                // Berechne verbleibende Monate (inkl. Startmonat)
                $remainingMonths = $periodEndMonth - $periodStartMonth + 1;
                $amount = round(($amount / 12) * $remainingMonths, 2);
            }
        }

        $bankAccountId = $membership->payer?->bankAccounts()
            ->where('is_default', true)
            ->where('status', 'active')
            ->first()?->id;

        $paymentData = [
            'membership_id' => $membership->id,
            'due_date' => $period['start']->copy()->day(15),
            'period_start' => $period['start'],
            'period_end' => $period['end'],
            'amount' => $amount,
            'status' => 'pending',
            'bank_account_id' => $bankAccountId,
        ];

        if ($dryRun) {
            $payment = new MembershipPayment($paymentData);
            $payment->setRelation('membership', $membership);
            return $payment;
        }

        return MembershipPayment::create($paymentData);
    }

    /**
     * Get interval multiplier for price calculation
     */
    protected function getIntervalMultiplier(string $billingInterval): int
    {
        return match($billingInterval) {
            'monthly' => 1,
            'quarterly' => 3,
            'semi_annual' => 6,
            'annual' => 12,
            default => 1,
        };
    }

    /**
     * Calculate payment period
     */
    protected function calculatePaymentPeriod(Membership $membership, Carbon $dueDate): ?array
    {
        $startDate = Carbon::parse($membership->started_at);
        $billingInterval = $membership->billing_interval;

        return match($billingInterval) {
            'monthly' => [
                'start' => $dueDate->copy()->startOfMonth(),
                'end' => $dueDate->copy()->endOfMonth(),
            ],
            'quarterly' => $this->calculateQuarterlyPeriod($startDate, $dueDate),
            'semi_annual' => $this->calculateSemiAnnualPeriod($startDate, $dueDate),
            'annual' => $this->calculateAnnualPeriod($startDate, $dueDate),
            default => null,
        };
    }

    /**
     * Calculate quarterly period
     */
    protected function calculateQuarterlyPeriod(Carbon $startDate, Carbon $dueDate): array
    {
        $monthsSinceStart = $startDate->diffInMonths($dueDate);
        $quarterIndex = intdiv($monthsSinceStart, 3);
        
        $periodStart = $startDate->copy()->addMonths($quarterIndex * 3)->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonths(3)->subDay();

        return [
            'start' => $periodStart,
            'end' => $periodEnd,
        ];
    }

    /**
     * Calculate semi-annual period
     */
    protected function calculateSemiAnnualPeriod(Carbon $startDate, Carbon $dueDate): array
    {
        $monthsSinceStart = $startDate->diffInMonths($dueDate);
        $semiIndex = intdiv($monthsSinceStart, 6);
        
        $periodStart = $startDate->copy()->addMonths($semiIndex * 6)->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonths(6)->subDay();

        return [
            'start' => $periodStart,
            'end' => $periodEnd,
        ];
    }

    /**
     * Calculate annual period - immer Kalenderjahr (01.01 - 31.12)
     */
    protected function calculateAnnualPeriod(Carbon $startDate, Carbon $dueDate): array
    {
        // Prüfe, ob es das erste Jahr der Mitgliedschaft ist
        $isFirstYear = $startDate->year === $dueDate->year;
        
        if ($isFirstYear && $startDate->month > 1) {
            // Anteilige Periode: Von Startdatum bis Jahresende
            $periodStart = $startDate->copy()->startOfDay();
            $periodEnd = Carbon::create($dueDate->year, 12, 31)->endOfDay();
        } else {
            // Normale Jahresperiode: 01.01 - 31.12
            $periodStart = Carbon::create($dueDate->year, 1, 1)->startOfDay();
            $periodEnd = Carbon::create($dueDate->year, 12, 31)->endOfDay();
        }

        return [
            'start' => $periodStart,
            'end' => $periodEnd,
        ];
    }

    /**
     * Generate missing payments for a specific membership
     */
    public function generatePaymentsForMembership(Membership $membership, int $monthsAhead = 1): Collection
    {
        $generatedPayments = collect();
        $referenceDate = Carbon::today();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $checkDate = $referenceDate->copy()->addMonths($i);
            
            if ($this->isDueForPayment($membership, $checkDate)) {
                $payment = $this->createPaymentForMembership($membership, $checkDate);
                if ($payment) {
                    $generatedPayments->push($payment);
                }
            }
        }

        return $generatedPayments;
    }
}
