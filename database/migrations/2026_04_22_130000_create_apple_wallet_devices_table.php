<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_wallet_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_library_identifier');
            $table->string('push_token');
            $table->string('pass_type_identifier');
            $table->string('serial_number');
            $table->string('auth_token');
            $table->timestamps();

            $table->unique(
                ['device_library_identifier', 'pass_type_identifier', 'serial_number'],
                'apple_wallet_devices_unique_registration'
            );
            $table->index(['pass_type_identifier', 'serial_number'], 'apple_wallet_devices_pass_serial_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_wallet_devices');
    }
};
