<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_area_id')->constrained('news_areas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->timestamp('published_at');
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['news_area_id', 'published_at']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
