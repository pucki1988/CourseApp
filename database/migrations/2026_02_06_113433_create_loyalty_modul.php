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
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable(); // user | member | mixed | card
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('loyalty_account_id')->nullable()->constrained();
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('member_id')->nullable()->constrained();
            $table->foreignId('loyalty_account_id')->nullable()->constrained();
            $table->boolean('active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_account_id')->constrained();
            $table->integer('points')->default(0);
            $table->enum('type', ['earn', 'redeem']);
            $table->enum('origin', ['sport', 'event', 'ticket','work', 'other']);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description')->nullable();
            $table->integer('balance_after')->default(0);
            $table->timestamps();
        });

       
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('loyalty_point_transactions');       
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['loyalty_account_id']);
        });
    }
};
