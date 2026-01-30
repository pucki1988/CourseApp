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


}