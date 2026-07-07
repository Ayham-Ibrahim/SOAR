<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Device extends Model
{
    protected $fillable = [
        'deviceable_id',
        'deviceable_type',
        'fcm_token',
    ];

    public function deviceable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function removeByToken(Model $owner, string $fcmToken): void
    {
        static::where('fcm_token', $fcmToken)
            ->where('deviceable_id', $owner->getKey())
            ->where('deviceable_type', $owner->getMorphClass())
            ->delete();
    }
}
