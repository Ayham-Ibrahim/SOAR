<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Orderable
{
    protected static function bootOrderable(): void
    {
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('order');
        });
    }
}
