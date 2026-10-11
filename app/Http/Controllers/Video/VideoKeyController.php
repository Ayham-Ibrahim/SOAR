<?php

namespace App\Http\Controllers\Video;

use App\Http\Controllers\Controller;
use App\Models\PlaybackSession;
use App\Models\Video;
use App\Services\VideoToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * The HLS decryption key, fetched by the player itself (it can't send a
 * bearer token, so the playback token in ?t= is the credential). This is
 * where revocation bites: the Worker only checks the signature, this
 * also checks the playback session row.
 */
class VideoKeyController extends Controller
{
    public function __construct(private readonly VideoToken $tokens)
    {
    }

    public function show(Request $request, string $video): Response
    {
        $claims = $this->tokens->verify((string) $request->query('t', ''));

        if (! $claims || $claims['exp'] <= time() || (string) $claims['v'] !== $video) {
            return $this->forbidden();
        }

        $session = PlaybackSession::find($claims['sid']);

        if (! $session
            || ! $session->isActive()
            || (int) $session->user_id !== $claims['u']
            || (int) $session->video_id !== $claims['v']) {
            return $this->forbidden();
        }

        $model = Video::find($claims['v']);
        $key = $model?->encryption_key ? base64_decode($model->encryption_key, true) : false;

        if ($key === false || strlen($key) !== 16) {
            return $this->forbidden();
        }

        Log::info('Video key fetched', [
            'user_id' => $session->user_id,
            'video_id' => $session->video_id,
            'session_id' => $session->id,
            'ip' => $request->ip(),
        ]);

        return response($key, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '16',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function forbidden(): Response
    {
        return response('', 403, ['Cache-Control' => 'no-store, private']);
    }
}
