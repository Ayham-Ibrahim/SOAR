<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Video file uploads (legacy)
    |--------------------------------------------------------------------------
    |
    | The old upload-a-file-through-the-API path. Kept switched off — set
    | VIDEO_UPLOADS_ENABLED=true to accept video files on /admin/videos
    | again. New uploads go straight to R2 instead (see below).
    |
    */

    'uploads_enabled' => (bool) env('VIDEO_UPLOADS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Direct-to-R2 uploads and encrypted HLS playback
    |--------------------------------------------------------------------------
    |
    | The dashboard uploads straight to R2 with pre-signed multipart part
    | URLs (the API never sees the file body), ProcessVideoJob turns the
    | original into AES-128 HLS, and students play it through the media
    | Worker with short-lived per-student tokens.
    |
    */

    'max_size' => (int) env('VIDEO_MAX_SIZE', 2147483648), // bytes (2 GB)

    'part_size' => (int) env('VIDEO_PART_SIZE', 26214400), // bytes (25 MB)

    'part_url_ttl' => (int) env('VIDEO_PART_URL_TTL', 60), // minutes

    'content_types' => ['video/mp4', 'video/quicktime', 'video/webm'],

    // Shared with the Cloudflare Worker (wrangler secret VIDEO_TOKEN_SECRET).
    'token_secret' => env('VIDEO_TOKEN_SECRET'),

    'token_ttl' => (int) env('VIDEO_TOKEN_TTL', 120), // minutes

    'max_devices' => (int) env('VIDEO_MAX_DEVICES', 1),

    // true: a new device takes over (the other devices' sessions are revoked)
    // instead of being refused with 429.
    'revoke_other_devices' => (bool) env('VIDEO_REVOKE_OTHER_DEVICES', false),

    'media_base' => rtrim(env('VIDEO_MEDIA_BASE', 'https://media.soaar.tech'), '/'),

    'keep_originals' => (bool) env('VIDEO_KEEP_ORIGINALS', true),

    'thumbnail_url_ttl' => 24 * 60, // minutes

    // A dedicated connection so the 2-hour retry_after of transcoding jobs
    // doesn't apply to every other job.
    'queue_connection' => env('VIDEO_QUEUE_CONNECTION', 'video'),

    'queue' => 'video',

    'ffmpeg' => env('FFMPEG_BIN', 'ffmpeg'),

    'ffprobe' => env('FFPROBE_BIN', 'ffprobe'),

    // Rendition ladder: [height, max bitrate in kbit/s]. Renditions taller
    // than the source are skipped; the first one is always kept.
    'renditions' => [
        [360, 800],
        [720, 2800],
        [1080, 5000],
    ],

];
