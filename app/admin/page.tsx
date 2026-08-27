import { headers } from 'next/headers';
import { redirect } from 'next/navigation';
import { getAdminSession } from '../../src/server/sessions';
import AdminDashboard from './AdminDashboard';

export const dynamic = 'force-dynamic';

export default async function AdminPage() {
  let authenticated = false;
  try {
    const requestHeaders = new Headers(await headers());
    const session = await getAdminSession(new Request('https://internal.local/admin', {
      headers: requestHeaders,
    }));
    authenticated = Boolean(session);
  } catch {
    authenticated = false;
  }

  if (!authenticated) redirect('/admin/login');
  return <AdminDashboard />;
}
