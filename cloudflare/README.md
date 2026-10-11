# Video delivery on Cloudflare

## 1. R2 bucket `soar-video`

- **Remove** the public custom domain `media.soaar.tech` (Settings → Custom Domains). It exposes every object.
- Public Development URL: **disabled**.
- CORS policy: paste [r2-cors.json](r2-cors.json) after replacing `DASHBOARD_DOMAIN`.
- Lifecycle rule: abort incomplete multipart uploads after **1 day**.

## 2. Media Worker (`media-worker/`)

```bash
cd cloudflare/media-worker
npm install
# edit wrangler.toml: ALLOWED_ORIGINS = your dashboard origin(s)
npx wrangler secret put VIDEO_TOKEN_SECRET   # same value as Laravel's VIDEO_TOKEN_SECRET
npx wrangler deploy                          # attaches the custom domain media.soaar.tech
npm test                                     # token test vector shared with tests/Unit/VideoTokenTest.php
```

## 3. API server

- `apt install ffmpeg` (needs `ffmpeg` + `ffprobe`; set `FFMPEG_BIN`/`FFPROBE_BIN` if not on PATH).
- `.env`: the `AWS_*` and `VIDEO_*` block from `.env.example`. Generate the secret once:
  `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`
- `php artisan migrate`
- Video worker: [deploy/supervisor-video-worker.conf](../deploy/supervisor-video-worker.conf).
  Free disk under `storage/app/tmp` ≈ 2× the largest upload.

## Checks after deploy

- `https://media.soaar.tech/hls/{id}/master.m3u8` (no token) → 404.
- A token for video A on `/p/{token}/hls/{B}/...` → 403.
- `hls/{id}/master.m3u8` in R2 contains `__KEY_URI__`; fetched through the Worker it has the real key URL.
