import { json, publicError, readJson, RequestError, verifySameOrigin } from '../../../../src/server/http';
import { enforceRateLimit } from '../../../../src/server/rate-limit';
import {
  authenticateCredentials,
  createSession,
  sessionCookie,
} from '../../../../src/server/sessions';
import { validateLogin } from '../../../../src/server/validation';

export async function POST(request: Request) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    const credentials = validateLogin(await readJson(request));
    const allowed = await enforceRateLimit(request, 'admin_login', 5, 15 * 60_000, credentials.email);
    if (!allowed) throw new RequestError('Çok fazla giriş denemesi. Lütfen daha sonra tekrar deneyin.', 429);

    const admin = await authenticateCredentials(credentials.email, credentials.password);
    if (!admin) throw new RequestError('E-posta veya şifre doğrulanamadı.', 401);

    const session = await createSession(admin.id);
    return json(
      { admin: { email: admin.email } },
      200,
      { 'Set-Cookie': sessionCookie(session.token, session.expiresAt) },
    );
  } catch (error) {
    return publicError(error, 'Giriş işlemi tamamlanamadı.');
  }
}
