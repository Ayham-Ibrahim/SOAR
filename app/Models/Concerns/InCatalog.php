<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * What students may see and buy: a curriculum node is in the catalog only if
 * it is active AND everything above it is — المرحلة (Category) → الصف
 * (SubCategory) → المادة (Subject) → الدورة (Course) → الامتحان (Exam).
 * Disabling a stage takes its whole branch out without touching the
 * is_active flags below it, so re-enabling it brings the branch back as it was.
 *
 * Each model using this trait names its parent relation in a static
 * $catalogParent (none for the root); the scope walks up through nested
 * whereHas, one EXISTS per level.
 */
trait InCatalog
{
    public function scopeInCatalog(Builder $query): Builder
    {
        $query->where($query->qualifyColumn('is_active'), true);

        $parent = static::$catalogParent ?? null;

        return $parent
            ? $query->whereHas($parent, fn (Builder $parentQuery) => $parentQuery->inCatalog())
            : $query;
    }

    public function isInCatalog(): bool
    {
        return static::query()->whereKey($this->getKey())->inCatalog()->exists();
    }
}
