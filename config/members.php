<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'external_system_url' => env('EXTERNAL_URL'),
    'external_system_club_id' => env('EXTERNAL_CLUB_ID'),
    'external_system_user' => env('EXTERNAL_USER'),
    'external_system_password' => env('EXTERNAL_PASSWORD'),
];