<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'name', 'type', 'hostname', 'port', 'username',
        'api_token_enc', 'password_enc', 'ssl', 'active',
        'max_accounts', 'current_accounts', 'metadata',
    ];

    protected $hidden = ['api_token_enc', 'password_enc'];

    protected function casts(): array
    {
        return [
            'ssl' => 'boolean',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function hasCapacity(): bool
    {
        return $this->max_accounts === null
            || $this->current_accounts < $this->max_accounts;
    }
}
