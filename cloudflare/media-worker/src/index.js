// media.soaar.tech — serves HLS from the private R2 bucket, only to
// requests carrying a valid playback token in the path:
//   /p/{token}/hls/{video_id}/{file}
// Variant and segment URIs in the playlists are relative, so they inherit
// the /p/{token}/ prefix and every request is authorized. The Worker is
// stateless (no revocation check); revocation is enforced at the API's key
// endpoint, whose URL is written into each playlist here.
import { verify } from './token.js';

function withCors(res, req, env) {
  const origin = req.headers.get('Origin');
  const allowed = (env.ALLOWED_ORIGINS || '').split(',').map((s) => s.trim());
  if (origin && allowed.includes(origin)) {
    res.headers.set('Access-Control-Allow-Origin', origin);
    res.headers.set('Vary', 'Origin');
  }
  return res;
}

export default {
  async fetch(req, env) {
    if (req.method === 'OPTIONS') {
      const r = new Response(null, { status: 204 });
      r.headers.set('Access-Control-Allow-Methods', 'GET, HEAD');
      r.headers.set('Access-Control-Allow-Headers', 'Range');
      return withCors(r, req, env);
    }
    if (req.method !== 'GET' && req.method !== 'HEAD') return new Response(null, { status: 405 });

    const url = new URL(req.url);
    const m = url.pathname.match(/^\/p\/([A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+)\/hls\/(\d+)\/([A-Za-z0-9_\-\/.]+)$/);
    if (!m || m[3].includes('..')) return new Response(null, { status: 404 });
    const [, token, videoId, rest] = m;

    const claims = await verify(token, env.VIDEO_TOKEN_SECRET);
    if (!claims || String(claims.v) !== videoId || claims.exp < Date.now() / 1000) {
      return withCors(new Response(null, { status: 403 }), req, env);
    }

    const obj = await env.BUCKET.get(`hls/${videoId}/${rest}`);
    if (!obj) return withCors(new Response(null, { status: 404 }), req, env);

    const headers = new Headers({ 'Cache-Control': 'private, no-store', 'X-Content-Type-Options': 'nosniff' });

    if (rest.endsWith('.m3u8')) {
      const keyUri = `${env.KEY_BASE}/${videoId}/key?t=${token}`;
      const body = (await obj.text()).replaceAll('__KEY_URI__', keyUri);
      headers.set('Content-Type', 'application/vnd.apple.mpegurl');
      return withCors(new Response(req.method === 'HEAD' ? null : body, { headers }), req, env);
    }

    headers.set('Content-Type', 'video/mp2t');
    return withCors(new Response(req.method === 'HEAD' ? null : obj.body, { headers }), req, env);
  },
};
