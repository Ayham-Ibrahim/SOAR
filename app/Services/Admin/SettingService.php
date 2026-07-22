<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

class SettingService
{
    public function list(): Collection
    {
        return Setting::all();
    }

    public function update(string $key, ?string $value): Setting
    {
        return Setting::set($key, $value);
    }
}
