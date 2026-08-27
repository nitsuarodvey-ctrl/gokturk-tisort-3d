import { json, publicError } from '../../../../src/server/http';
import { getAdminSession } from '../../../../src/server/sessions';

export async function GET(request: Request) {
  try {
    const admin = await getAdminSession(request);
    if (!admin) return json({ authenticated: false }, 401);
    return json({ authenticated: true, admin: { email: admin.email } });
  } catch (error) {
    return publicError(error, 'Oturum doğrulanamadı.');
  }
}
