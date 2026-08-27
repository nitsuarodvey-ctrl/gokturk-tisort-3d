export function json(data: unknown, status = 200, headers: HeadersInit = {}) {
  return Response.json(data, {
    status,
    headers: {
      'Cache-Control': 'no-store',
      'X-Content-Type-Options': 'nosniff',
      ...headers,
    },
  });
}

export function verifySameOrigin(request: Request) {
  const origin = request.headers.get('origin');
  if (!origin) return false;
  try {
    return new URL(origin).origin === new URL(request.url).origin;
  } catch {
    return false;
  }
}

export async function readJson(request: Request) {
  const contentType = request.headers.get('content-type') ?? '';
  if (!contentType.toLowerCase().startsWith('application/json')) {
    throw new RequestError('JSON içerik bekleniyor.', 415);
  }
  const contentLength = Number(request.headers.get('content-length') ?? 0);
  if (Number.isFinite(contentLength) && contentLength > 16_384) {
    throw new RequestError('İstek gövdesi çok büyük.', 413);
  }
  try {
    const raw = await request.text();
    if (raw.length > 16_384) throw new RequestError('İstek gövdesi çok büyük.', 413);
    return JSON.parse(raw) as unknown;
  } catch (error) {
    if (error instanceof RequestError) throw error;
    throw new RequestError('Geçersiz JSON.', 400);
  }
}

export class RequestError extends Error {
  constructor(message: string, public status = 400) {
    super(message);
  }
}

export function publicError(error: unknown, fallback: string) {
  if (error instanceof RequestError) return json({ error: error.message }, error.status);
  console.error(fallback, error);
  return json({ error: fallback }, 500);
}
