<?php

namespace App\Models;

use App\Models\Concerns\HasDevices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ParentModel extends Authenticatable
{
    use HasApiTokens, HasDevices, HasFactory, Notifiable;

    protected $table = 'parents';

    protected $fillable = [
        'name',
        'phone',
        'password',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPhoneVerified(): bool
    {
        return ! is_null($this->phone_verified_at);
    }
}
