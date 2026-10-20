<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offline copy of an HLS video on one student's device. The app plays
 * it without a network until offline_until; syncing online renews that
 * date, or revokes the copy (the app then deletes the files and key).
 */
class VideoDownload extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'video_id',
        'device_id',
        'height',
        'offline_until',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'height' => 'integer',
            'offline_until' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('offline_until', '>', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
