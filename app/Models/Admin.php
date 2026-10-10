<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Website owner account for the admin panel.
 *
 * Completely separate from visitors: the public site has no accounts at all.
 * Admins are created on the command line with `php artisan pdf:admin`.
 */
class Admin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }
}
