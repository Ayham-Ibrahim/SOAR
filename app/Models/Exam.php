<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use FiltersByCurriculum, HasFactory;

    /** @see FiltersByCurriculum */
    protected static array $curriculumFilters = [
        'category_id' => 'course.subject.subCategory:category_id',
        'sub_category_id' => 'course.subject:sub_category_id',
        'subject_id' => 'course:subject_id',
        'teacher_id' => 'course:teacher_id',
        'course_id' => 'course_id',
        'unit_id' => 'course.lessons:unit_id',
        'lesson_id' => 'course.lessons:lessons.id',
    ];

    protected $fillable = [
        'course_id',
        'title',
        'type',
        'description',
        'attachment',
        'duration_minutes',
        'passing_score',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
