<?php

namespace App\Models;

use App\Models\Concerns\HasDevices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    public function isPhoneVerified(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'parent_id', 'student_id');
    }

    public function accountRequests(): HasMany
    {
        return $this->hasMany(ParentAccountRequest::class, 'created_parent_id');
    }
}
