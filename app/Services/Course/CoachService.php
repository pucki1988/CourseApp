<?php

namespace App\Services\Course;

use App\Models\Course\Coach;
use App\Models\Course\CoachMonthlyBilling;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class CoachService
{
    public function hasMonthlyBilling(Coach $coach, int $year, int $month): bool
    {
        return CoachMonthlyBilling::where('coach_id', $coach->id)
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }

    public function getMonthlyBilling(Coach $coach, int $year, int $month): ?CoachMonthlyBilling
    {
        return CoachMonthlyBilling::where('coach_id', $coach->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function deleteMonthlyBilling(Coach $coach, int $year, int $month): bool
    {
        $billing = $this->getMonthlyBilling($coach, $year, $month);

        if (!$billing) {
            return false;
        }

        $billing->delete();

        return true;
    }

    public function listCoaches(array $filters = [])
    {
        $query = Coach::query();

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        return $query->get();
    }

    /**
     * Coach anlegen
     */
    public function store(array $data): Coach
    {
        return DB::transaction(function () use ($data) {

            /** @var Coach $coach */
            $coach = Coach::create([
                'user_id'        => $data['user_id'] ?? null,
                'name'          => $data['name'] ?? null,
                'active'         => $data['active'] ?? true,
            ]);

            return $coach;
        });
    }

    /**
     * Coach aktualisieren
     */
    public function update(Coach $coach, array $data): Coach
    {
        return DB::transaction(function () use ($coach, $data) {

            $coach->update([
                'user_id'        => $data['user_id'] ?? null,
                'name'          => $data['name'] ?? null,
                'active'         => $data['active'] ?? $coach->active,
            ]);

            return $coach;
        });
    }

    
    public function delete(Coach $coach)
    {
        $coach->delete();
        return true;
    }

    /**
     * Calculate monthly billing for a coach
     * 
     * @param Coach $coach
     * @param int $year
     * @param int $month
     * @return array
     */
    public function calculateMonthlyBilling(Coach $coach, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all course slots for this coach in the specified month
        $slots = \App\Models\Course\CourseSlot::whereHas('course', function ($query) use ($coach) {
            $query->where('coach_id', $coach->id);
        })
        ->whereBetween('date', [$startDate, $endDate])
        ->whereIn('status', ['active'])
        ->with(['course', 'bookingSlots' => function ($query) {
            $query->where('status', 'booked')
                  ->whereNotNull('checked_in_at');
        }])
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

        $billingItems = [];
        $totalCompensation = 0;

        foreach ($slots as $slot) {
            $participantCount = $slot->bookingSlots->count();
            $compensation = $coach->calculateCompensation($participantCount) ?? 0;

            $billingItems[] = [
                'course_slot_id' => $slot->id,
                'date' => $slot->date,
                'course_title' => $slot->course->title,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'participant_count' => $participantCount,
                'compensation' => $compensation,
            ];

            $totalCompensation += $compensation;
        }

        return [
            'coach' => $coach,
            'year' => $year,
            'month' => $month,
            'month_name' => $startDate->copy()->locale('de')->translatedFormat('F Y'),
            'billing_items' => $billingItems,
            'total_compensation' => $totalCompensation,
            'total_slots' => count($billingItems),
        ];
    }

    /**
     * Persist billing data for audit trail and coach self-service.
     */
    public function persistMonthlyBilling(array $billingData, array $meta = []): CoachMonthlyBilling
    {
        $coach = $billingData['coach'];
        $startDate = Carbon::create($billingData['year'], $billingData['month'], 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return DB::transaction(function () use ($coach, $billingData, $startDate, $endDate, $meta) {
            $existingBilling = CoachMonthlyBilling::where('coach_id', $coach->id)
                ->where('year', $billingData['year'])
                ->where('month', $billingData['month'])
                ->first();

            if ($existingBilling) {
                throw new \InvalidArgumentException('Monatsabrechnung existiert bereits und kann nicht erneut erstellt oder aktualisiert werden.');
            }

            $billing = CoachMonthlyBilling::create([
                'coach_id' => $coach->id,
                'year' => $billingData['year'],
                'month' => $billingData['month'],
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'total_slots' => $billingData['total_slots'],
                'total_compensation' => $billingData['total_compensation'],
                'status' => $meta['status'] ?? 'generated',
                'mail_recipient' => $meta['mail_recipient'] ?? null,
                'mail_sent_at' => $meta['mail_sent_at'] ?? null,
                'notes' => $meta['notes'] ?? null,
            ]);

            $billing->items()->delete();

            foreach ($billingData['billing_items'] as $item) {
                $billing->items()->create([
                    'course_slot_id' => $item['course_slot_id'] ?? null,
                    'date' => $item['date'],
                    'course_title' => $item['course_title'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'participant_count' => $item['participant_count'],
                    'compensation' => $item['compensation'],
                ]);
            }

            return $billing;
        });
    }


}