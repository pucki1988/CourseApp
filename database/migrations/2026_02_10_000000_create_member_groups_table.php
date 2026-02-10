<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('member_group_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('member_group_id')->constrained('member_groups')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'member_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_group_member');
        Schema::dropIfExists('member_groups');
    }
};
