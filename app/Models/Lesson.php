<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use App\Models\Concerns\Orderable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lesson belongs to a unit and may be attached to multiple courses.
 * The course/lesson relationship is now many-to-many via course_lesson.
 */
class Lesson extends Model
{
    use FiltersByCurriculum, HasFactory, Orderable;

    /** @see FiltersByCurriculum */
    protected static array $curriculumFilters = [
        'category_id' => 'unit.subject.subCategory:category_id',
        'sub_category_id' => 'unit.subject:sub_category_id',
        'subject_id' => 'unit:subject_id',
        'teacher_id' => 'courses:teacher_id',
        'course_id' => 'courses:courses.id',
        'unit_id' => 'unit_id',
        'lesson_id' => 'id',
    ];

    protected $fillable = [
        'unit_id',
        'title',
        'order',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_lesson');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }
}
