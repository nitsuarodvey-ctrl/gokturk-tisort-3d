import { sha256 } from './crypto';

export class BackendError extends Error {
  constructor(message: string, public status: number) {
    super(message);
  }
}

function configuration() {
  const rawUrl = process.env.LARAVEL_API_URL?.trim();
  const apiKey = process.env.LARAVEL_API_KEY;
  if (!rawUrl || !apiKey || apiKey.length < 32) {
    throw new BackendError('Sipariş servisi yapılandırılmamış.', 503);
  }

  const url = new URL(rawUrl);
  if (process.env.NODE_ENV === 'production' && url.protocol !== 'https:') {
    throw new BackendError('Sipariş servisi güvenli bağlantı gerektiriyor.', 503);
  }
  return { baseUrl: url.toString().replace(/\/$/u, ''), apiKey };
}

function clientAddress(request: Request) {
  return request.headers.get('cf-connecting-ip')
    ?? request.headers.get('x-forwarded-for')?.split(',')[0]?.trim()
    ?? 'unknown';
}

export async function backendRequest<T>(
  path: string,
  request: Request,
  options: {
    method?: string;
    body?: unknown;
    sessionToken?: string;
  } = {},
): Promise<T> {
  const { baseUrl, apiKey } = configuration();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Internal-Key': apiKey,
    'X-Client-Key': await sha256(clientAddress(request)),
  };
  if (options.body !== undefined) headers['Content-Type'] = 'application/json';
  if (options.sessionToken) headers.Authorization = `Bearer ${options.sessionToken}`;

  const response = await fetch(`${baseUrl}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    cache: 'no-store',
  });
  const payload = await response.json().catch(() => ({})) as T & {
    message?: string;
    error?: string;
  };
  if (!response.ok) {
    throw new BackendError(payload.message || payload.error || 'İstek tamamlanamadı.', response.status);
  }
  return payload;
}
