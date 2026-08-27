import { json, publicError, readJson, RequestError, verifySameOrigin } from '../../../../src/server/http';
import {
  loginAdmin,
  sessionCookie,
} from '../../../../src/server/sessions';
import { validateLogin } from '../../../../src/server/validation';

export async function POST(request: Request) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    const credentials = validateLogin(await readJson(request));
    const session = await loginAdmin(request, credentials.email, credentials.password);
    return json(
      { admin: session.admin },
      200,
      { 'Set-Cookie': sessionCookie(session.sessionToken, session.expiresIn) },
    );
  } catch (error) {
    return publicError(error, 'Giriş işlemi tamamlanamadı.');
  }
}
