<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::where('name', 'coach')->delete();
    }

    public function down(): void
    {
        Role::create([
            'name' => 'coach',
            'guard_name' => 'web',
        ]);
    }
};
