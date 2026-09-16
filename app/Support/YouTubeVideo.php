<?php

namespace App\Support;

/**
 * Reads the video id out of whatever YouTube link the admin pasted, so we
 * store one canonical id instead of the many URL shapes YouTube hands out
 * (watch, youtu.be, embed, shorts, live, with or without extra params).
 */
class YouTubeVideo
{
    /** YouTube ids are always 11 characters of this alphabet. */
    private const ID_PATTERN = '~^[A-Za-z0-9_-]{11}$~';

    public static function idFrom(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // A bare id, e.g. pasted from the YouTube Studio.
        if (preg_match(self::ID_PATTERN, $url)) {
            return $url;
        }

        $parts = parse_url(str_starts_with($url, 'http') ? $url : "https://{$url}");

        if (! isset($parts['host'])) {
            return null;
        }

        $host = preg_replace('~^(www|m|music)\.~', '', strtolower($parts['host']));
        $segments = explode('/', trim($parts['path'] ?? '', '/'));

        if ($host === 'youtu.be') {
            return self::validate($segments[0] ?? '');
        }

        if (! in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);

        if (! empty($query['v'])) {
            return self::validate((string) $query['v']);
        }

        return in_array($segments[0] ?? '', ['embed', 'shorts', 'live', 'v'], true)
            ? self::validate($segments[1] ?? '')
            : null;
    }

    public static function watchUrl(string $id): string
    {
        return "https://www.youtube.com/watch?v={$id}";
    }

    /** YouTube's own thumbnail, used when the admin didn't upload one. */
    public static function thumbnailUrl(string $id): string
    {
        return "https://img.youtube.com/vi/{$id}/hqdefault.jpg";
    }

    private static function validate(string $id): ?string
    {
        return preg_match(self::ID_PATTERN, $id) ? $id : null;
    }
}
