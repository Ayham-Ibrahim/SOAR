<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use App\Models\Concerns\Orderable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use FiltersByCurriculum, HasFactory, Orderable;

    /** @see FiltersByCurriculum */
    protected static array $curriculumFilters = [
        'category_id' => 'subject.subCategory:category_id',
        'sub_category_id' => 'subject:sub_category_id',
        'subject_id' => 'subject_id',
        'teacher_id' => 'lessons.courses:teacher_id',
        'course_id' => 'lessons.courses:id',
        'unit_id' => 'id',
        'lesson_id' => 'lessons:id',
    ];

    protected $fillable = [
        'subject_id',
        'title',
        'order',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
