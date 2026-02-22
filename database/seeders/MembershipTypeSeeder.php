<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member\MembershipType;

class MembershipTypeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Kinder bis 13 Jahre',
                'slug' => 'child',
                'base_amount' => 12.00,
                'billing_mode' => 'recurring',
                'billing_interval' => 'annual',
                'conditions' => ['min_age' => 0, 'max_age' => 13],
                'active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Jugendliche 14-17 Jahre',
                'slug' => 'youth',
                'base_amount' => 25.00,
                'billing_mode' => 'recurring',
                'billing_interval' => 'annual',
                'conditions' => ['min_age' => 14, 'max_age' => 17],
                'active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Erwachsene 18-64 Jahre',
                'slug' => 'adult',
                'base_amount' => 50.00,
                'billing_mode' => 'recurring',
                'billing_interval' => 'annual',
                'conditions' => ['min_age' => 18, 'max_age' => 64],
                'active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Senioren ab 65 Jahre',
                'slug' => 'senior',
                'base_amount' => 12.00,
                'billing_mode' => 'recurring',
                'billing_interval' => 'annual',
                'conditions' => ['min_age' => 65],
                'active' => true,
                'sort_order' => 40,
            ],
            [
                'name' => 'Familie',
                'slug' => 'family',
                'base_amount' => 70.00,
                'billing_mode' => 'recurring',
                'billing_interval' => 'annual',
                'conditions' => ['min_members' => 3, 'min_adults' => 2, 'min_children' => 1],
                'active' => true,
                'sort_order' => 50,
            ],
        ];

        foreach ($types as $type) {
            MembershipType::updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}
