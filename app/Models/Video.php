<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use App\Models\Concerns\Orderable;
use App\Support\YouTubeVideo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A video is either a YouTube link (source = youtube, youtube_video_id set)
 * or a file in our own storage (source = upload, url set). Uploads are
 * currently switched off — see config('video.uploads_enabled').
 */
class Video extends Model
{
    use FiltersByCurriculum, HasFactory, Orderable;

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_YOUTUBE = 'youtube';

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
        'source',
        'url',
        'youtube_video_id',
        'thumbnail',
        'duration_seconds',
        'order',
        'is_free',
        'is_downloadable',
        'status',
    ];

    protected $appends = ['youtube_url'];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_downloadable' => 'boolean',
        ];
    }

    /** The player link, built from the stored id. Null for uploaded videos. */
    protected function youtubeUrl(): Attribute
    {
        return Attribute::get(fn () => $this->youtube_video_id
            ? YouTubeVideo::watchUrl($this->youtube_video_id)
            : null);
    }

    /** Falls back to YouTube's own thumbnail when none was uploaded. */
    protected function thumbnail(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value ?: ($this->youtube_video_id
            ? YouTubeVideo::thumbnailUrl($this->youtube_video_id)
            : null));
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
