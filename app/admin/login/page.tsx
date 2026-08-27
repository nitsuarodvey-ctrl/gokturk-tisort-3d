'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

export default function AdminLoginPage() {
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    void fetch('/api/admin/session', { credentials: 'same-origin', cache: 'no-store' })
      .then((response) => {
        if (response.ok) router.replace('/admin');
      });
  }, [router]);

  const login = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitting(true);
    setError('');

    try {
      const form = new FormData(event.currentTarget);
      const response = await fetch('/api/admin/login', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: String(form.get('email') ?? '').trim(),
          password: String(form.get('password') ?? ''),
        }),
      });
      const payload = await response.json().catch(() => ({})) as { error?: string };
      if (!response.ok) throw new Error(payload.error || 'E-posta veya şifre doğrulanamadı.');
      router.replace('/admin');
    } catch (loginError) {
      setError(
        loginError instanceof Error
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
        <p>Sipariş yönetimi yalnızca yetkilendirilmiş admin kullanıcılarına açıktır.</p>

        <form onSubmit={login}>
          <label>
            <span>E-posta</span>
            <input name="email" type="email" autoComplete="email" required />
          </label>
          <label>
            <span>Şifre</span>
            <input name="password" type="password" autoComplete="current-password" maxLength={128} required />
          </label>
          {error && <p className="form-error" role="alert">{error}</p>}
          <button className="primary-cta" type="submit" disabled={submitting}>
            <span>{submitting ? 'Doğrulanıyor…' : 'Giriş Yap'}</span>
          </button>
        </form>
      </section>
    </main>
  );
}
