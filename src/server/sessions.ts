import { backendRequest, BackendError } from './backend';

export const SESSION_COOKIE = 'gub_admin_session';
const SESSION_MAX_AGE_SECONDS = 8 * 60 * 60;

export type AdminSession = { email: string };

function cookieValue(request: Request, name: string) {
  const cookie = request.headers.get('cookie') ?? '';
  for (const part of cookie.split(';')) {
    const [key, ...value] = part.trim().split('=');
    if (key === name) return decodeURIComponent(value.join('='));
  }
  return null;
}

export function getSessionToken(request: Request) {
  const token = cookieValue(request, SESSION_COOKIE);
  return token && token.length >= 32 && token.length <= 128 ? token : null;
}

export async function loginAdmin(request: Request, email: string, password: string) {
  return await backendRequest<{
    admin: AdminSession;
    sessionToken: string;
    expiresIn: number;
  }>('/admin/login', request, {
    method: 'POST',
    body: { email, password },
  });
}

export function sessionCookie(token: string, expiresIn = SESSION_MAX_AGE_SECONDS) {
  const maxAge = Math.min(Math.max(expiresIn, 60), SESSION_MAX_AGE_SECONDS);
  const expiresAt = new Date(Date.now() + maxAge * 1_000);
  return [
    `${SESSION_COOKIE}=${encodeURIComponent(token)}`,
    'Path=/',
    'HttpOnly',
    'Secure',
    'SameSite=Strict',
    `Max-Age=${maxAge}`,
    `Expires=${expiresAt.toUTCString()}`,
  ].join('; ');
}

export function expiredSessionCookie() {
  return `${SESSION_COOKIE}=; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=0`;
}

export async function getAdminSession(request: Request): Promise<AdminSession | null> {
  const token = getSessionToken(request);
  if (!token) return null;
  try {
    const result = await backendRequest<{ admin: AdminSession }>('/admin/session', request, {
      sessionToken: token,
    });
    return result.admin;
  } catch (error) {
    if (error instanceof BackendError && error.status === 401) return null;
    throw error;
  }
}

export async function deleteSession(request: Request) {
  const token = getSessionToken(request);
  if (!token) return;
  try {
    await backendRequest('/admin/logout', request, { method: 'POST', sessionToken: token });
  } catch (error) {
    if (!(error instanceof BackendError && error.status === 401)) throw error;
  }
}
