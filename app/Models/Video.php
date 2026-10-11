<?php

namespace App\Models;

use App\Models\Concerns\FiltersByCurriculum;
use App\Models\Concerns\Orderable;
use App\Services\VideoStorage;
use App\Support\YouTubeVideo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A video is a YouTube link (source = youtube, youtube_video_id set), a
 * legacy file in our own storage (source = upload, url set — switched off,
 * see config('video.uploads_enabled')), or an encrypted HLS stream on R2
 * (source = hls) that is only playable through a playback token — it never
 * has a permanent URL.
 */
class Video extends Model
{
    use FiltersByCurriculum, HasFactory, Orderable;

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_YOUTUBE = 'youtube';

    public const SOURCE_HLS = 'hls';

    // processing_status of an HLS video.
    public const UPLOADING = 'uploading';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

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
        'uploaded_by',
        'processing_status',
        'original_filename',
        'original_key',
        'size',
        'content_type',
        'upload_id',
        'part_size',
        'total_parts',
        'height',
        'thumbnail_key',
        'encryption_key',
        'hls_ready_at',
        'error',
    ];

    /** Storage internals and the content key never leave the API. */
    protected $hidden = [
        'original_key',
        'upload_id',
        'encryption_key',
        'thumbnail_key',
    ];

    protected $appends = ['youtube_url'];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_downloadable' => 'boolean',
            'size' => 'integer',
            'part_size' => 'integer',
            'total_parts' => 'integer',
            'encryption_key' => 'encrypted',
            'hls_ready_at' => 'datetime',
        ];
    }

    /** The player link, built from the stored id. Null for uploaded videos. */
    protected function youtubeUrl(): Attribute
    {
        return Attribute::get(fn () => $this->youtube_video_id
            ? YouTubeVideo::watchUrl($this->youtube_video_id)
            : null);
    }

    /**
     * Falls back to YouTube's own thumbnail, or to a short-lived signed link
     * to the frame extracted from an HLS video, when none was uploaded.
     */
    protected function thumbnail(): Attribute
    {
        return Attribute::get(function (?string $value) {
            if ($value) {
                return $value;
            }

            if ($this->youtube_video_id) {
                return YouTubeVideo::thumbnailUrl($this->youtube_video_id);
            }

            return $this->thumbnailUrl();
        });
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_key
            ? app(VideoStorage::class)->temporaryUrl($this->thumbnail_key, config('video.thumbnail_url_ttl'))
            : null;
    }

    public function isHls(): bool
    {
        return $this->source === self::SOURCE_HLS;
    }

    public function isReady(): bool
    {
        return $this->isHls() && $this->processing_status === self::READY;
    }

    public function hlsPrefix(): string
    {
        return "hls/{$this->id}/";
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function playbackSessions(): HasMany
    {
        return $this->hasMany(PlaybackSession::class);
    }
}
