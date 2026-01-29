<?php

namespace Database\Seeders;

use App\Models\Course\SportType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SportTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sportTypes = [
            ['name' => 'Yoga', 'description' => 'Yoga und Entspannung'],
            ['name' => 'Fitness', 'description' => 'Fitnesstraining und Kraftaufbau'],
            ['name' => 'Pilates', 'description' => 'Pilates-Kurse'],
            ['name' => 'Zumba', 'description' => 'Zumba und Tanz'],
            ['name' => 'Kampfsport', 'description' => 'Karate, Kickboxen, etc.'],
            ['name' => 'Tanzen', 'description' => 'Verschiedene Tanzstile'],
            ['name' => 'Schwimmen', 'description' => 'Schwimmkurse und Wasseraerobic'],
            ['name' => 'Laufen', 'description' => 'Jogging und Lauftraining'],
            ['name' => 'Radfahren', 'description' => 'Radfahren und Cycling'],
            ['name' => 'Tennis', 'description' => 'Tennisunterricht'],
        ];

        foreach ($sportTypes as $sportType) {
            SportType::firstOrCreate(
                ['name' => $sportType['name']],
                $sportType
            );
        }
    }
}
