<?php

namespace App\Providers;

use App\Services\VideoStorage;
use App\Services\VideoToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(VideoStorage::class);

        $this->app->bind(VideoToken::class, fn () => new VideoToken((string) config('video.token_secret')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('video-playback', fn (Request $request) => Limit::perMinute(30)
            ->by('user:'.($request->user()?->id ?: $request->ip())));

        // Per token: a player fetches the key once per rendition switch.
        RateLimiter::for('video-key', fn (Request $request) => Limit::perMinute(120)
            ->by('token:'.sha1((string) $request->query('t', $request->ip()))));

        // The player reports its position every ~15 seconds.
        RateLimiter::for('video-progress', fn (Request $request) => Limit::perMinute(60)
            ->by('user:'.($request->user()?->id ?: $request->ip())));
    }
}
