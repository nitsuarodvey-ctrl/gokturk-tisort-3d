import { json, publicError, RequestError, verifySameOrigin } from '../../../../src/server/http';
import { deleteSession, expiredSessionCookie } from '../../../../src/server/sessions';

export async function POST(request: Request) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    await deleteSession(request);
    return json({ ok: true }, 200, { 'Set-Cookie': expiredSessionCookie() });
  } catch (error) {
    return publicError(error, 'Çıkış işlemi tamamlanamadı.');
  }
}
