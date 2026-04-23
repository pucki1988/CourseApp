<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppleWalletDevice extends Model
{
    protected $fillable = [
        'device_library_identifier',
        'push_token',
        'pass_type_identifier',
        'serial_number',
        'auth_token',
    ];
}
