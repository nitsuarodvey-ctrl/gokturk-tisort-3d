'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { isSupabaseConfigured, requireSupabase } from '../../../src/lib/supabase.js';

export default function AdminLoginPage() {
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!isSupabaseConfigured) return;
    void requireSupabase().auth.getUser().then(({ data }) => {
      if (data.user?.app_metadata?.role === 'admin') router.replace('/admin');
    });
  }, [router]);

  const login = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitting(true);
    setError('');

    try {
      const form = new FormData(event.currentTarget);
      const client = requireSupabase();
      const { data, error: authError } = await client.auth.signInWithPassword({
        email: String(form.get('email') ?? '').trim(),
        password: String(form.get('password') ?? ''),
      });
      if (authError) throw authError;
      if (data.user.app_metadata?.role !== 'admin') {
        await client.auth.signOut();
        throw new Error('Bu hesap admin yetkisine sahip değil.');
      }
      router.replace('/admin');
    } catch (loginError) {
      setError(
        loginError instanceof Error && loginError.message.includes('yapılandırması eksik')
          ? 'Supabase bağlantısı henüz yapılandırılmadı.'
          : loginError instanceof Error && loginError.message.includes('admin yetkisine')
            ? loginError.message
            : 'E-posta veya şifre doğrulanamadı.',
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="admin-login-shell">
      <section className="admin-login-panel" aria-labelledby="admin-login-title">
        <p className="eyebrow">GUB MERCH / YÖNETİM</p>
        <h1 id="admin-login-title">Admin Girişi</h1>
        <p>Sipariş yönetimi yalnızca yetkilendirilmiş Supabase kullanıcılarına açıktır.</p>

        <form onSubmit={login}>
          <label>
            <span>E-posta</span>
            <input name="email" type="email" autoComplete="email" required />
          </label>
          <label>
            <span>Şifre</span>
            <input name="password" type="password" autoComplete="current-password" required />
          </label>
          {error && <p className="form-error" role="alert">{error}</p>}
          <button className="primary-cta" type="submit" disabled={submitting || !isSupabaseConfigured}>
            <span>{submitting ? 'Doğrulanıyor…' : 'Giriş Yap'}</span>
          </button>
        </form>
      </section>
    </main>
  );
}
