<?php

namespace App\Services;

use App\Exceptions\VideoPipelineException;
use App\Models\Lesson;
use App\Models\PlaybackSession;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoDownload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Offline copies of HLS videos. The app downloads one rendition (playlist,
 * encrypted segments and the key) with a short-lived download token, keeps
 * the key in secure storage, and plays the copy without a network until
 * offline_until. Each online sync renews that date — capped at the end of
 * the subscription — or revokes the copy, and the app deletes it.
 *
 * A download needs the video marked downloadable and the lesson in a
 * course that allows downloads (the same two flags the app already
 * checks), plus the usual access to the video.
 */
class VideoDownloadService
{
    public const DEVICE_LIMIT = 'VIDEO_DOWNLOAD_DEVICE_LIMIT';

    public function __construct(
        private readonly VideoPlaybackService $playback,
        private readonly VideoToken $tokens,
    ) {
    }

    /**
     * @return array{download_id: string, video_id: int, height: int, available_heights: list<int>, playlist_url: string, download_expires_at: string, offline_until: string, watermark_text: string}
     */
    public function start(User $user, Lesson $lesson, string $deviceId, ?string $ip, ?string $userAgent, ?int $videoId = null, ?int $height = null): array
    {
        $video = $this->playback->lessonVideo($lesson, $videoId);

        if (! $this->playback->canWatch($user, $lesson, $video)) {
            throw new VideoPipelineException('ليس لديك صلاحية مشاهدة هذا الدرس.', 403);
        }

        if (! $video->isReady()) {
            throw VideoPipelineException::conflict('الفيديو قيد المعالجة ولم يجهز للمشاهدة بعد.');
        }

        $offlineUntil = $this->offlineUntil($user, $lesson, $video)
            ?? throw new VideoPipelineException('تحميل هذا الفيديو غير متاح.', 403);

        $heights = $video->renditionHeights();
        if ($heights === []) {
            throw VideoPipelineException::conflict('تعذر تجهيز الفيديو للتحميل.');
        }

        $height = $this->pickHeight($heights, $height);

        if (! $user->is_admin) {
            $this->enforceDeviceLimit($user, $deviceId);
        }

        $download = VideoDownload::updateOrCreate(
            ['user_id' => $user->id, 'video_id' => $video->id, 'device_id' => $deviceId],
            ['height' => $height, 'offline_until' => $offlineUntil, 'revoked_at' => null],
        );

        $expiresAt = Carbon::now()->addMinutes((int) config('video.download_token_ttl'));

        $session = PlaybackSession::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'device_id' => $deviceId,
            'purpose' => PlaybackSession::DOWNLOAD,
            'ip' => $ip ?? '',
            'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
            'expires_at' => $expiresAt,
        ]);

        $token = $this->tokens->issue($user->id, $video->id, $session->id, $expiresAt->getTimestamp());

        return [
            'download_id' => $download->id,
            'video_id' => $video->id,
            'height' => $height,
            'available_heights' => $heights,
            'playlist_url' => sprintf(
                '%s/p/%s/hls/%d/v%d/index.m3u8',
                config('video.media_base'),
                $token,
                $video->id,
                array_search($height, $heights, true),
            ),
            'download_expires_at' => $expiresAt->toIso8601String(),
            'offline_until' => $offlineUntil->toIso8601String(),
            'watermark_text' => $this->playback->watermark($user),
        ];
    }

    /**
     * Renews the downloads still allowed and revokes the rest. Ids that are
     * unknown, someone else's or from another device come back invalid.
     *
     * @param  list<string>  $downloadIds
     * @return list<array{download_id: string, valid: bool, offline_until: string|null}>
     */
    public function sync(User $user, string $deviceId, array $downloadIds): array
    {
        $downloads = VideoDownload::query()
            ->with('video.lesson.courses')
            ->where('user_id', $user->id)
            ->whereIn('id', $downloadIds)
            ->get()
            ->keyBy('id');

        return array_map(function (string $id) use ($downloads, $user, $deviceId) {
            $download = $downloads->get($id);
            $offlineUntil = null;

            if ($download && $download->device_id === $deviceId && $download->revoked_at === null) {
                $video = $download->video;
                $offlineUntil = $video?->isReady() && $video->lesson
                    ? $this->offlineUntil($user, $video->lesson, $video)
                    : null;

                $download->update($offlineUntil
                    ? ['offline_until' => $offlineUntil]
                    : ['revoked_at' => now()]);
            }

            return [
                'download_id' => $id,
                'valid' => $offlineUntil !== null,
                'offline_until' => $offlineUntil?->toIso8601String(),
            ];
        }, array_values(array_unique($downloadIds)));
    }

    /** The app deleted its copy. */
    public function remove(User $user, VideoDownload $download): void
    {
        if ((int) $download->user_id !== (int) $user->id) {
            throw new VideoPipelineException('التحميل غير موجود.', 404);
        }

        $download->update(['revoked_at' => now()]);
    }

    /**
     * Until when an offline copy may play, or null when downloading isn't
     * allowed: offline_days from now, never past the subscription's end.
     */
    private function offlineUntil(User $user, Lesson $lesson, Video $video): ?Carbon
    {
        $cap = Carbon::now()->addDays((int) config('video.offline_days'));

        if ($user->is_admin) {
            return $cap;
        }

        $courses = $lesson->courses->where('allow_download', true);

        if (! $video->is_downloadable || $courses->isEmpty()) {
            return null;
        }

        if ($video->is_free) {
            return $cap;
        }

        $subscriptionEnd = $user->subscriptions()
            ->whereIn('course_id', $courses->modelKeys())
            ->active()
            ->max('expires_at');

        return $subscriptionEnd ? Carbon::parse($subscriptionEnd)->min($cap) : null;
    }

    /**
     * The quality asked for, which must exist; otherwise the highest one at
     * or below the default, or the lowest one.
     *
     * @param  list<int>  $heights
     */
    private function pickHeight(array $heights, ?int $requested): int
    {
        if ($requested !== null) {
            if (! in_array($requested, $heights, true)) {
                throw new VideoPipelineException('الدقة المطلوبة غير متوفرة لهذا الفيديو.', 422);
            }

            return $requested;
        }

        $default = (int) config('video.download_default_height');
        $fitting = array_filter($heights, fn (int $h) => $h <= $default);

        return $fitting ? max($fitting) : min($heights);
    }

    private function enforceDeviceLimit(User $user, string $deviceId): void
    {
        $otherDevices = VideoDownload::query()
            ->where('user_id', $user->id)
            ->active()
            ->where('device_id', '!=', $deviceId)
            ->distinct()
            ->count('device_id');

        if ($otherDevices >= (int) config('video.max_download_devices')) {
            throw new VideoPipelineException(
                'لديك فيديوهات محمّلة على جهاز آخر. احذفها من ذلك الجهاز أولاً.',
                429,
                self::DEVICE_LIMIT,
            );
        }
    }
}
