<?php

use App\Models\Admin;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| The public website has no user accounts at all. The only authenticated
| area is the admin panel for the website owner, which uses its own "admin"
| guard and `admins` table. Admin accounts are created with:
|
|     php artisan pdf:admin
|
*/

return [

    'defaults' => [
        'guard' => 'admin',
        'passwords' => 'admins',
    ],

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
