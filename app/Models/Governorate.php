<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    /**
     * Informational only — never used to gate or filter content.
     */
    public function students(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
