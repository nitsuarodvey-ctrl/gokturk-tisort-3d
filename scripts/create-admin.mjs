import { connect } from '@planetscale/database';
import { randomUUID, webcrypto } from 'node:crypto';

const encoder = new TextEncoder();
const iterations = 310_000;

function base64url(bytes) {
  return Buffer.from(bytes).toString('base64url');
}

async function hashPassword(password) {
  const salt = webcrypto.getRandomValues(new Uint8Array(16));
  const key = await webcrypto.subtle.importKey('raw', encoder.encode(password), 'PBKDF2', false, ['deriveBits']);
  const bits = await webcrypto.subtle.deriveBits(
    { name: 'PBKDF2', hash: 'SHA-256', salt, iterations },
    key,
    256,
  );
  return `pbkdf2_sha256$${iterations}$${base64url(salt)}$${base64url(new Uint8Array(bits))}`;
}

const host = process.env.DATABASE_HOST?.trim();
const username = process.env.DATABASE_USERNAME?.trim();
const password = process.env.DATABASE_PASSWORD;
const email = process.env.ADMIN_EMAIL?.trim().toLowerCase();
const adminPassword = process.env.ADMIN_PASSWORD;

if (!host || !username || !password || !email || !adminPassword) {
  throw new Error('DATABASE_* ile ADMIN_EMAIL ve ADMIN_PASSWORD değerleri gereklidir.');
}
if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(email)) throw new Error('ADMIN_EMAIL geçersiz.');
if (adminPassword.length < 14 || adminPassword.length > 128) {
  throw new Error('ADMIN_PASSWORD 14–128 karakter olmalıdır.');
}

const db = connect({ host, username, password });
await db.execute(
  `INSERT INTO admins (id, email, password_hash, active)
   VALUES (?, ?, ?, 1)
   ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), active = 1`,
  [randomUUID(), email, await hashPassword(adminPassword)],
);

console.log(`Admin hesabı hazırlandı: ${email}`);
