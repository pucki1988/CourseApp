<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->boolean('show_in_blog')->default(true)->after('sent_at');
            $table->boolean('send_mail')->default(true)->after('show_in_blog');
        });
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropColumn(['show_in_blog', 'send_mail']);
        });
    }
};
