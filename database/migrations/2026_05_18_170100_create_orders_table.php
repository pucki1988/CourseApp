<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->string('customer_name')->nullable();
            $table->string('customer_email');
            $table->char('currency', 3)->default('EUR');
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};