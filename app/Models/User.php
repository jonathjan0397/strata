<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'two_factor_enabled'      => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Convenience: check if this user is any kind of admin. */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'staff']);
    }

    /** Convenience: check if this user is a client. */
    public function isClient(): bool
    {
        return $this->hasRole('client');
    }
}
