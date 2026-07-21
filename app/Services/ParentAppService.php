<?php

namespace App\Services;

use App\Models\ParentModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Self-service actions for an authenticated parent account (the "Parent
 * app" side), as opposed to Services\Admin\ParentService which is the
 * admin dashboard's CRUD over the parents resource.
 */
class ParentAppService
{
    public function children(ParentModel $parent): Collection
    {
        return $parent->students()->get(['users.id', 'users.name', 'users.phone', 'users.avatar']);
    }
}
