<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();

            // optionaler Bezug zu users
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // fachliche Daten
            $table->string('name')->nullable(); // z.B. Trainer, Dozent
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            // alten FK auf users entfernen
            $table->dropForeign(['coach_id']);
            $table->string('location')->nullable(); // z.B. Trainer, Dozent
            // neuen FK auf coaches setzen
            $table->foreign('coach_id')
                ->references('id')
                ->on('coaches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaches');
        Schema::table('courses', function (Blueprint $table) {
            // FK auf coaches entfernen
            $table->dropForeign(['coach_id']);
            $table->dropColumn('location');
            // FK wieder auf users setzen
            $table->foreign('coach_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
