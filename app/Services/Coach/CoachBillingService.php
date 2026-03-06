<?php

namespace App\Services\Coach;

use App\Models\Coach\Coach;
use App\Models\Coach\CoachMonthlyBilling;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class CoachBillingService
{
    public function listCoaches(): Collection
    {
        return Coach::query()->orderBy('name')->get();
    }

    public function listBillingsForUser(?User $user, ?string $filterCoachId = null, ?string $filterMonth = null): Collection
    {
        if (!$user) {
            return collect();
        }

        $coachProfile = $user->coach;

        if (!$coachProfile && !$user->can('courses.manage')) {
            return collect();
        }

        $query = CoachMonthlyBilling::with(['items', 'coach'])
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($coachProfile) {
            $query->where('coach_id', $coachProfile->id);
        } elseif (!empty($filterCoachId)) {
            $query->where('coach_id', (int) $filterCoachId);
        }

        if (!empty($filterMonth) && str_contains($filterMonth, '-')) {
            [$year, $month] = array_map('intval', explode('-', $filterMonth));
            $query->where('year', $year)
                ->where('month', $month);
        }

        return $query->get();
    }

    public function runBilling(string $month, ?int $coachId = null, bool $dryRun = false, bool $force = false): string
    {
        $args = [
            '--month' => $month,
        ];

        if (!empty($coachId)) {
            $args['--coach'] = $coachId;
        }

        if ($dryRun) {
            $args['--dry-run'] = true;
        }

        if ($force) {
            $args['--force'] = true;
        }

        Artisan::call('coaches:generate-billing', $args);

        return trim(Artisan::output());
    }

    public function deleteBilling(int $billingId): void
    {
        $billing = CoachMonthlyBilling::findOrFail($billingId);
        $billing->delete();
    }

    public function rerunBillingForce(int $billingId): string
    {
        $billing = CoachMonthlyBilling::findOrFail($billingId);
        $month = sprintf('%04d-%02d', $billing->year, $billing->month);

        return $this->runBilling($month, (int) $billing->coach_id, false, true);
    }
}
