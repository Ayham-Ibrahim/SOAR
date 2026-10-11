// Playback token check — must match app/Services/VideoToken.php byte for byte.
//   token = base64url(json) "." base64url(HMAC-SHA256(base64url(json), secret))
const enc = new TextEncoder();

export function b64urlToBytes(s) {
  s = s.replace(/-/g, '+').replace(/_/g, '/');
  while (s.length % 4) s += '=';
  return Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
}

// The token's claims if the signature is valid, else null. Expiry and the
// video id are checked by the caller.
export async function verify(token, secret) {
  const [payload, sig, extra] = token.split('.');
  if (!payload || !sig || extra !== undefined) return null;
  try {
    const key = await crypto.subtle.importKey('raw', enc.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['verify']);
    const ok = await crypto.subtle.verify('HMAC', key, b64urlToBytes(sig), enc.encode(payload));
    if (!ok) return null;
    return JSON.parse(new TextDecoder().decode(b64urlToBytes(payload)));
  } catch {
    return null;
  }
}
