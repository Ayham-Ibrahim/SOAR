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
        // The token is globally unique: if another account last used this
        // device, move the token to the current owner instead of inserting.
        return Device::updateOrCreate(
            ['fcm_token' => $fcmToken],
            [
                'deviceable_id' => $this->getKey(),
                'deviceable_type' => $this->getMorphClass(),
            ],
        );
    }
}
