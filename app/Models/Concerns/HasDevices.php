<?php

namespace App\Models\Concerns;

use App\Models\Device;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDevices
{
    public function devices(): MorphMany
    {
        return $this->morphMany(Device::class, 'deviceable');
    }

    public function registerDevice(string $fcmToken): Device
    {
        return $this->devices()->updateOrCreate(['fcm_token' => $fcmToken]);
    }
}
