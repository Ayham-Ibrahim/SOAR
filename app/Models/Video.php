<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use App\Models\Concerns\Orderable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use FiltersByCurriculum, HasFactory, Orderable;

    /** @see FiltersByCurriculum */
    protected static array $curriculumFilters = [
        'category_id' => 'lesson.unit.subject.subCategory:category_id',
        'sub_category_id' => 'lesson.unit.subject:sub_category_id',
        'subject_id' => 'lesson.unit:subject_id',
        'teacher_id' => 'lesson.courses:teacher_id',
        'course_id' => 'lesson.courses:courses.id',
        'unit_id' => 'lesson:unit_id',
        'lesson_id' => 'lesson_id',
    ];

    protected $fillable = [
        'lesson_id',
        'title',
        'url',
        'thumbnail',
        'duration_seconds',
        'order',
        'is_free',
        'is_downloadable',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_downloadable' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
