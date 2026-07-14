<?php

namespace App\Services\Admin;

use App\Models\Governorate;
use Illuminate\Database\Eloquent\Collection;

class GovernorateService
{
    public function list(): Collection
    {
        return Governorate::orderBy('name')->get();
    }
}
