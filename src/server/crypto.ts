const encoder = new TextEncoder();
const PASSWORD_ITERATIONS = 310_000;
const PASSWORD_BYTES = 32;

function toBase64Url(bytes: Uint8Array) {
  let binary = '';
  bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
  return btoa(binary).replaceAll('+', '-').replaceAll('/', '_').replace(/=+$/u, '');
}

function fromBase64Url(value: string) {
  const normalized = value.replaceAll('-', '+').replaceAll('_', '/');
  const padded = normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '=');
  const binary = atob(padded);
  return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

function timingSafeEqual(left: Uint8Array, right: Uint8Array) {
  if (left.length !== right.length) return false;
  let mismatch = 0;
  for (let index = 0; index < left.length; index += 1) {
    mismatch |= left[index] ^ right[index];
  }
  return mismatch === 0;
}

async function derivePassword(password: string, salt: Uint8Array, iterations: number) {
  const key = await crypto.subtle.importKey(
    'raw',
    encoder.encode(password),
    'PBKDF2',
    false,
    ['deriveBits'],
  );
  const bits = await crypto.subtle.deriveBits(
    { name: 'PBKDF2', hash: 'SHA-256', salt: salt as BufferSource, iterations },
    key,
    PASSWORD_BYTES * 8,
  );
  return new Uint8Array(bits);
}

export async function hashPassword(password: string) {
  if (password.length < 14 || password.length > 128) {
    throw new Error('Admin şifresi 14–128 karakter olmalıdır.');
  }
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const hash = await derivePassword(password, salt, PASSWORD_ITERATIONS);
  return `pbkdf2_sha256$${PASSWORD_ITERATIONS}$${toBase64Url(salt)}$${toBase64Url(hash)}`;
}

export async function verifyPassword(password: string, storedHash: string) {
  if (!password || password.length > 128) return false;
  const [algorithm, rawIterations, rawSalt, rawHash] = storedHash.split('$');
  const iterations = Number(rawIterations);
  if (
    algorithm !== 'pbkdf2_sha256'
    || !Number.isInteger(iterations)
    || iterations < 210_000
    || iterations > 1_000_000
    || !rawSalt
    || !rawHash
  ) return false;

  try {
    const expected = fromBase64Url(rawHash);
    const actual = await derivePassword(password, fromBase64Url(rawSalt), iterations);
    return timingSafeEqual(actual, expected);
  } catch {
    return false;
  }
}

export function randomToken(bytes = 32) {
  return toBase64Url(crypto.getRandomValues(new Uint8Array(bytes)));
}

export async function sha256(value: string) {
  const digest = await crypto.subtle.digest('SHA-256', encoder.encode(value));
  return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
}
