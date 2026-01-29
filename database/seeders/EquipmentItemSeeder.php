<?php

namespace Database\Seeders;

use App\Models\Course\EquipmentItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EquipmentItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $equipmentItems = [
            ['name' => 'Yoga-Matte', 'description' => 'Standard Yoga-Matte'],
            ['name' => 'Hanteln', 'description' => 'Verschiedene Gewichte'],
            ['name' => 'Kettlebell', 'description' => 'Kugelhantel zum Training'],
            ['name' => 'Widerstandsband', 'description' => 'Elastische Bänder für Übungen'],
            ['name' => 'Fitnessmatte', 'description' => 'Dicke Matte für Bodenübungen'],
            ['name' => 'Schläger', 'description' => 'Tennis- oder Badminton-Schläger'],
            ['name' => 'Ball', 'description' => 'Verschiedene Ballarten'],
            ['name' => 'Springseil', 'description' => 'Springseil zum Ausdauertraining'],
            ['name' => 'Step-Plattform', 'description' => 'Stufe für Step-Aerobic'],
            ['name' => 'Handschuhe', 'description' => 'Trainingshandschuhe'],
            ['name' => 'Schutzausrüstung', 'description' => 'Helm, Schützer, etc.'],
            ['name' => 'Schwimmbrett', 'description' => 'Auftriebshilfe beim Schwimmen'],
            ['name' => 'Flossen', 'description' => 'Schwimmflossen'],
            ['name' => 'Schnorchel', 'description' => 'Schnorchel für Wassersportarten'],
        ];

        foreach ($equipmentItems as $equipment) {
            EquipmentItem::firstOrCreate(
                ['name' => $equipment['name']],
                $equipment
            );
        }
    }
}
