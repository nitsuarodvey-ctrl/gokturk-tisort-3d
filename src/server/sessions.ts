import { getDatabase } from './db';
import { randomToken, sha256, verifyPassword } from './crypto';

export const SESSION_COOKIE = 'gub_admin_session';
const SESSION_MAX_AGE_SECONDS = 8 * 60 * 60;
const DUMMY_PASSWORD_HASH = 'pbkdf2_sha256$310000$PQ2zIThf1fKK6HA9_rz3rw$TvnrDY8ett4f5FykE9-fcSUfFqgkDDwavcqpPyZTLmw';

type AdminRow = {
  id: string;
  email: string;
  password_hash: string;
  active: number | string;
};

export type AdminSession = { id: string; email: string };

function cookieValue(request: Request, name: string) {
  const cookie = request.headers.get('cookie') ?? '';
  for (const part of cookie.split(';')) {
    const [key, ...value] = part.trim().split('=');
    if (key === name) return decodeURIComponent(value.join('='));
  }
  return null;
}

export async function authenticateCredentials(email: string, password: string) {
  const result = await getDatabase().execute<AdminRow>(
    `SELECT id, email, password_hash, active
     FROM admins WHERE email = ? LIMIT 1`,
    [email],
  );
  const admin = result.rows[0];
  const validPassword = await verifyPassword(password, admin?.password_hash ?? DUMMY_PASSWORD_HASH);
  if (!admin || Number(admin.active) !== 1 || !validPassword) return null;
  return validPassword
    ? { id: admin.id, email: admin.email }
    : null;
}

export async function createSession(adminId: string) {
  const token = randomToken();
  const tokenHash = await sha256(token);
  const expiresAt = new Date(Date.now() + SESSION_MAX_AGE_SECONDS * 1_000);
  await getDatabase().execute(
    `INSERT INTO admin_sessions (id, admin_id, token_hash, expires_at)
     VALUES (?, ?, ?, ?)`,
    [crypto.randomUUID(), adminId, tokenHash, expiresAt.toISOString().slice(0, 23).replace('T', ' ')],
  );
  return { token, expiresAt };
}

export function sessionCookie(token: string, expiresAt: Date) {
  return [
    `${SESSION_COOKIE}=${encodeURIComponent(token)}`,
    'Path=/',
    'HttpOnly',
    'Secure',
    'SameSite=Strict',
    `Max-Age=${SESSION_MAX_AGE_SECONDS}`,
    `Expires=${expiresAt.toUTCString()}`,
  ].join('; ');
}

export function expiredSessionCookie() {
  return `${SESSION_COOKIE}=; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=0`;
}

export async function getAdminSession(request: Request): Promise<AdminSession | null> {
  const token = cookieValue(request, SESSION_COOKIE);
  if (!token || token.length > 128) return null;
  const tokenHash = await sha256(token);
  const result = await getDatabase().execute<AdminSession>(
    `SELECT admins.id, admins.email
     FROM admin_sessions
     INNER JOIN admins ON admins.id = admin_sessions.admin_id
     WHERE admin_sessions.token_hash = ?
       AND admin_sessions.expires_at > CURRENT_TIMESTAMP(3)
       AND admins.active = 1
     LIMIT 1`,
    [tokenHash],
  );
  return result.rows[0] ?? null;
}

export async function deleteSession(request: Request) {
  const token = cookieValue(request, SESSION_COOKIE);
  if (!token || token.length > 128) return;
  await getDatabase().execute('DELETE FROM admin_sessions WHERE token_hash = ?', [await sha256(token)]);
}
