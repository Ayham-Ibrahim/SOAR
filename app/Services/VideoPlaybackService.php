<?php

namespace App\Services;

use App\Exceptions\VideoPipelineException;
use App\Models\Lesson;
use App\Models\PlaybackSession;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Hands a student a short-lived, personal HLS link. There is never a
 * permanent URL: the media Worker checks the token on every playlist and
 * segment, and the key endpoint (which also checks the playback session)
 * is what makes the segments playable.
 */
class VideoPlaybackService
{
    public const DEVICE_LIMIT = 'VIDEO_DEVICE_LIMIT';

    public function __construct(
        private readonly CourseAccess $courseAccess,
        private readonly VideoToken $tokens,
    ) {
    }

    /**
     * @return array{hls_url: string, expires_at: string, watermark_text: string}
     */
    public function start(User $user, Lesson $lesson, string $deviceId, ?string $ip, ?string $userAgent, ?int $videoId = null): array
    {
        $video = $this->lessonVideo($lesson, $videoId);

        if (! $this->canWatch($user, $lesson, $video)) {
            throw new VideoPipelineException('ليس لديك صلاحية مشاهدة هذا الدرس.', 403);
        }

        if (! $video->isReady()) {
            throw VideoPipelineException::conflict('الفيديو قيد المعالجة ولم يجهز للمشاهدة بعد.');
        }

        if (! $user->is_admin) {
            $this->enforceDeviceLimit($user, $deviceId);
        }

        $expiresAt = Carbon::now()->addMinutes((int) config('video.token_ttl'));

        $session = PlaybackSession::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'device_id' => $deviceId,
            'ip' => $ip ?? '',
            'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
            'expires_at' => $expiresAt,
        ]);

        $token = $this->tokens->issue($user->id, $video->id, $session->id, $expiresAt->getTimestamp());

        return [
            'hls_url' => sprintf('%s/p/%s/hls/%d/master.m3u8', config('video.media_base'), $token, $video->id),
            'expires_at' => $expiresAt->toIso8601String(),
            'watermark_text' => $this->watermark($user),
        ];
    }

    /**
     * The lesson's HLS video: the one asked for, or the first by order.
     */
    private function lessonVideo(Lesson $lesson, ?int $videoId): Video
    {
        $video = $lesson->videos()
            ->where('source', Video::SOURCE_HLS)
            ->when($videoId, fn ($query) => $query->whereKey($videoId))
            ->orderBy('order')
            ->orderBy('id')
            ->first();

        if (! $video) {
            throw VideoPipelineException::conflict('لا يوجد فيديو جاهز لهذا الدرس.');
        }

        return $video;
    }

    /**
     * Admins always; a free video for any signed-in student; otherwise a
     * live subscription to one of the courses the lesson belongs to.
     */
    private function canWatch(User $user, Lesson $lesson, Video $video): bool
    {
        if ($user->is_admin || $video->is_free) {
            return true;
        }

        return $lesson->courses->contains(fn ($course) => $this->courseAccess->hasAccess($user, $course));
    }

    private function enforceDeviceLimit(User $user, string $deviceId): void
    {
        $otherDevices = PlaybackSession::query()
            ->where('user_id', $user->id)
            ->active()
            ->where('device_id', '!=', $deviceId);

        if ((clone $otherDevices)->distinct()->count('device_id') < (int) config('video.max_devices')) {
            return;
        }

        if (config('video.revoke_other_devices')) {
            $otherDevices->update(['revoked_at' => now()]);

            return;
        }

        throw new VideoPipelineException(
            'تتم مشاهدة الفيديو حالياً من جهاز آخر. يرجى المحاولة لاحقاً.',
            429,
            self::DEVICE_LIMIT,
        );
    }

    private function watermark(User $user): string
    {
        $digits = preg_replace('/\D/', '', (string) $user->phone);

        return trim($user->name.' · '.substr($digits, -4), ' ·');
    }
}
