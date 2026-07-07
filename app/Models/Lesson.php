<?php

namespace App\Models;

use App\Models\Concerns\Orderable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory, Orderable;

    protected $fillable = [
        'unit_id',
        'title',
        'order',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }
}
