<?php

namespace App\Services\Course;

use App\Models\Course\Coach;
use Illuminate\Support\Facades\DB;


class CoachService
{
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
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
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
            'month_name' => $startDate->translatedFormat('F Y'),
            'billing_items' => $billingItems,
            'total_compensation' => $totalCompensation,
            'total_slots' => count($billingItems),
        ];
    }


}