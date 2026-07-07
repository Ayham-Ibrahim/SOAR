<?php

namespace App\Models;

use App\Models\Concerns\Orderable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasFactory, Orderable;

    protected $fillable = [
        'lesson_id',
        'title',
        'url',
        'thumbnail',
        'duration_seconds',
        'order',
        'is_free',
        'is_downloadable',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_downloadable' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
