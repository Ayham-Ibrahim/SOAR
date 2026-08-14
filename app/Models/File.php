<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    use FiltersByCurriculum, HasFactory;

    /** @see FiltersByCurriculum */
    protected static array $curriculumFilters = [
        'category_id' => 'lesson.unit.subject.subCategory:category_id',
        'sub_category_id' => 'lesson.unit.subject:sub_category_id',
        'subject_id' => 'lesson.unit:subject_id',
        'teacher_id' => 'lesson.courses:teacher_id',
        'course_id' => 'lesson.courses:id',
        'unit_id' => 'lesson:unit_id',
        'lesson_id' => 'lesson_id',
    ];

    protected $fillable = [
        'lesson_id',
        'title',
        'path',
        'type',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
