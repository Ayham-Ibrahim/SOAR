<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use FiltersByCurriculum, HasFactory, SoftDeletes;

    /** @see FiltersByCurriculum */
    protected static array $curriculumFilters = [
        'category_id' => 'subject.subCategory:category_id',
        'sub_category_id' => 'subject:sub_category_id',
        'subject_id' => 'subject_id',
        'teacher_id' => 'teacher_id',
        'course_id' => 'id',
        'unit_id' => 'lessons:unit_id',
        'lesson_id' => 'lessons:id',
    ];

    protected $fillable = [
        'subject_id',
        'teacher_id',
        'title',
        'description',
        'price',
        'discount_price',
        'subscription_days',
        'free_videos_count',
        'allow_download',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'allow_download' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'course_lesson');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'offer_course');
    }
}
