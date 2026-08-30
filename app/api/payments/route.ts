import { BackendError, backendRawRequest } from '../../../src/server/backend';
import { RequestError, verifySameOrigin } from '../../../src/server/http';
import { validatePaymentStart } from '../../../src/server/validation';

function escapeHtml(value: string) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function failurePage(message: string) {
  return `<!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ödeme başlatılamadı</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#090a0b;color:#eee9df;font:16px Arial,sans-serif}.box{width:min(440px,calc(100% - 40px));border-top:2px solid #981b24;padding-top:28px}p{color:#aaa59c;line-height:1.6}a{display:inline-block;margin-top:18px;padding:14px 18px;background:#981b24;color:white;text-decoration:none;font-weight:700}</style><main class="box"><h1>Ödeme başlatılamadı</h1><p>${escapeHtml(message)}</p><a href="/">Mağazaya dön</a></main></html>`;
}

export async function POST(request: Request) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    const contentType = request.headers.get('content-type')?.toLowerCase() ?? '';
    if (!contentType.startsWith('application/x-www-form-urlencoded') && !contentType.startsWith('multipart/form-data')) {
      throw new RequestError('Form içeriği bekleniyor.', 415);
    }
    const contentLength = Number(request.headers.get('content-length') ?? 0);
    if (Number.isFinite(contentLength) && contentLength > 16_384) {
      throw new RequestError('İstek gövdesi çok büyük.', 413);
    }

    const form = await request.formData();
    const payload = validatePaymentStart(Object.fromEntries(form.entries()));
    const bankResponse = await backendRawRequest('/payments', request, {
      method: 'POST',
      body: payload,
    });

    return new Response(await bankResponse.text(), {
      status: 200,
      headers: {
        'Content-Type': 'text/html; charset=UTF-8',
        'Cache-Control': 'no-store',
        'Content-Security-Policy': "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data: https:; form-action https://sanalpos.kuveytturk.com.tr https://boatest.kuveytturk.com.tr; base-uri 'none'; frame-ancestors 'none'",
        'Cross-Origin-Opener-Policy': 'same-origin-allow-popups',
        'Permissions-Policy': 'camera=(), microphone=(), geolocation=(), payment=()',
        'Referrer-Policy': 'no-referrer',
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'DENY',
      },
    });
  } catch (error) {
    const message = error instanceof RequestError || error instanceof BackendError
      ? error.message
      : 'Ödeme servisine ulaşılamadı. Lütfen tekrar deneyin.';
    const status = error instanceof RequestError
      ? error.status
      : error instanceof BackendError && error.status >= 400 && error.status < 500
        ? error.status
        : 502;

    return new Response(failurePage(message), {
      status,
      headers: {
        'Content-Type': 'text/html; charset=UTF-8',
        'Cache-Control': 'no-store',
        'Content-Security-Policy': "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'",
        'Cross-Origin-Opener-Policy': 'same-origin',
        'Permissions-Policy': 'camera=(), microphone=(), geolocation=(), payment=()',
        'Referrer-Policy': 'no-referrer',
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'DENY',
      },
    });
  }
}
