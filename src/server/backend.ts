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

function isIpv4(value: string) {
  const parts = value.split('.');
  return parts.length === 4 && parts.every((part) => {
    if (!/^\d{1,3}$/u.test(part)) return false;
    const number = Number(part);
    return number >= 0 && number <= 255 && String(number) === part;
  });
}

function clientIpv4(request: Request) {
  const candidates = [
    request.headers.get('cf-pseudo-ipv4'),
    request.headers.get('cf-connecting-ip'),
    request.headers.get('x-forwarded-for')?.split(',')[0]?.trim(),
    request.headers.get('x-real-ip'),
  ];
  const ipv4 = candidates.find((candidate): candidate is string => Boolean(candidate && isIpv4(candidate)));
  if (ipv4) return ipv4;
  return process.env.NODE_ENV === 'production' ? null : '127.0.0.1';
}

export async function backendRawRequest(
  path: string,
  request: Request,
  options: {
    method?: string;
    body?: unknown;
    sessionToken?: string;
  } = {},
) {
  const { baseUrl, apiKey } = configuration();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Internal-Key': apiKey,
    'X-Client-Key': await sha256(clientAddress(request)),
  };
  const ipv4 = clientIpv4(request);
  if (ipv4) headers['X-Client-IP'] = ipv4;
  if (options.body !== undefined) headers['Content-Type'] = 'application/json';
  if (options.sessionToken) headers.Authorization = `Bearer ${options.sessionToken}`;

  const response = await fetch(`${baseUrl}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    cache: 'no-store',
    signal: AbortSignal.timeout(30_000),
  });
  if (!response.ok) {
    const payload = await response.clone().json().catch(() => ({})) as {
      message?: string;
      error?: string;
    };
    throw new BackendError(payload.message || payload.error || 'İstek tamamlanamadı.', response.status);
  }

  return response;
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
  const response = await backendRawRequest(path, request, options);

  return await response.json() as T;
}
