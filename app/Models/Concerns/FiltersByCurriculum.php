<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Unified hierarchy filter shared by every curriculum listing page in the
 * dashboard: المرحلة الدراسية (category_id) → الصف (sub_category_id) →
 * المادة (subject_id) → المعلم (teacher_id) → الدورة (course_id) → الوحدة
 * (unit_id) → الدرس (lesson_id).
 *
 * Each model using this trait declares a static $curriculumFilters map of
 * the keys it supports. A value is either the name of a column on the
 * model's own table (resolved with a plain, indexed where — no join), or
 * "relation.path:column" for a filter that lives on a related table
 * (resolved with a single whereHas, dot-relation nesting handled natively
 * by Eloquent).
 *
 * Every filter is applied through when(), not if(): only the keys actually
 * present in $filters touch the query, so a request with none of them stays
 * a plain, unconstrained select instead of paying for a chain of no-op
 * conditionals.
 */
trait FiltersByCurriculum
{
    public function scopeCurriculumFilter(Builder $query, array $filters): Builder
    {
        foreach (static::$curriculumFilters as $key => $target) {
            $query->when($filters[$key] ?? null, function (Builder $query, $value) use ($target) {
                if (! str_contains($target, ':')) {
                    return $query->where($target, $value);
                }

                [$relation, $column] = explode(':', $target);

                return $query->whereHas($relation, fn (Builder $query) => $query->where($column, $value));
            });
        }

        return $query;
    }
}
