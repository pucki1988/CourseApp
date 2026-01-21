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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date');
            $table->date('entry_date');
            $table->string('external_id')->unique();
            $table->string('city');
            $table->string('zip_code');
            $table->string('street');
            $table->enum('gender',['female','male','diverse']); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
