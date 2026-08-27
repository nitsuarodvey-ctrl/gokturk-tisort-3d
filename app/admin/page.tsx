'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { isSupabaseConfigured, requireSupabase } from '../../src/lib/supabase.js';
import {
  DELIVERY_STATUSES,
  DeliveryStatus,
  Order,
  ORDER_STATUSES,
  OrderStatus,
  orderRepository,
  PAYMENT_STATUSES,
  PaymentStatus,
  PRODUCTION_STATUSES,
  ProductionStatus,
} from '../../src/store/orders';

const labels: Record<string, string> = {
  waiting: 'Bekliyor',
  paid: 'Ödendi',
  rejected: 'Geçersiz',
  preorder: 'Ön Sipariş',
  confirmed: 'Onaylandı',
  cancelled: 'İptal',
  queued: 'Sırada',
  in_production: 'Üretimde',
  ready: 'Hazır',
  ready_for_pickup: 'Teslime Hazır',
  shipped: 'Kargoda',
  delivered: 'Teslim Edildi',
};

export default function AdminPage() {
  const router = useRouter();
  const [orders, setOrders] = useState<Order[]>([]);
  const [authorized, setAuthorized] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [savingId, setSavingId] = useState('');

  const loadOrders = useCallback(async () => {
    try {
      const nextOrders = await orderRepository.getAll();
      setOrders(nextOrders);
      setError('');
    } catch (loadError) {
      console.error('[GUB admin] Orders could not be loaded:', loadError);
      setError('Siparişler yüklenemedi. Yetkinizi ve Supabase bağlantısını kontrol edin.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isSupabaseConfigured) {
      router.replace('/admin/login');
      return;
    }

    let unsubscribe = () => {};
    let active = true;
    void requireSupabase().auth.getUser().then(async ({ data, error: authError }) => {
      if (!active) return;
      if (authError || data.user?.app_metadata?.role !== 'admin') {
        router.replace('/admin/login');
        return;
      }
      setAuthorized(true);
      await loadOrders();
      if (active) unsubscribe = orderRepository.subscribe(loadOrders);
    });

    return () => {
      active = false;
      unsubscribe();
    };
  }, [loadOrders, router]);

  const stats = useMemo(() => {
    const sizeTotals = { S: 0, M: 0, L: 0, XL: 0 };
    let revenue = 0;
    orders.forEach((order) => {
      sizeTotals[order.size] += order.quantity;
      if (order.paymentStatus === 'paid') revenue += order.total;
    });
    return { sizeTotals, revenue };
  }, [orders]);

  const updateOrder = async (id: string, patch: Parameters<typeof orderRepository.update>[1]) => {
    setSavingId(id);
    setError('');
    try {
      const updated = await orderRepository.update(id, patch);
      setOrders((current) => current.map((order) => (order.id === id ? updated : order)));
    } catch (updateError) {
      console.error('[GUB admin] Order update failed:', updateError);
      setError('Sipariş güncellenemedi.');
    } finally {
      setSavingId('');
    }
  };

  const deleteOrder = async (order: Order) => {
    if (!window.confirm(`${order.name} adına olan sipariş silinsin mi?`)) return;
    setSavingId(order.id);
    try {
      await orderRepository.delete(order.id);
      setOrders((current) => current.filter((item) => item.id !== order.id));
    } catch (deleteError) {
      console.error('[GUB admin] Order deletion failed:', deleteError);
      setError('Sipariş silinemedi.');
    } finally {
      setSavingId('');
    }
  };

  const signOut = async () => {
    await requireSupabase().auth.signOut();
    router.replace('/admin/login');
  };

  if (!authorized) {
    return (
      <main className="admin-login-shell">
        <p className="admin-empty">Güvenli oturum doğrulanıyor…</p>
      </main>
    );
  }

  return (
    <main className="admin-shell">
      <header className="admin-header">
        <div>
          <p className="eyebrow">GUB MERCH / YÖNETİM</p>
          <h1>Siparişler</h1>
        </div>
        <button className="text-button" type="button" onClick={signOut}>Çıkış Yap</button>
      </header>

      <section className="admin-stats" aria-label="Sipariş özeti">
        <div><span>Sipariş</span><strong>{orders.length}</strong></div>
        <div><span>Ödenen Ciro</span><strong>{stats.revenue} TL</strong></div>
        {Object.entries(stats.sizeTotals).map(([size, count]) => (
          <div key={size}><span>{size} Beden</span><strong>{count}</strong></div>
        ))}
      </section>

      {error && <p className="admin-error" role="alert">{error}</p>}

      <section className="orders-list" aria-live="polite">
        {loading ? (
          <p className="admin-empty">Siparişler yükleniyor…</p>
        ) : orders.length === 0 ? (
          <p className="admin-empty">Henüz sipariş bulunmuyor.</p>
        ) : (
          orders.map((order) => (
            <article className="admin-order" key={order.id}>
              <div className="order-identity">
                <div>
                  <span>{new Date(order.createdAt).toLocaleString('tr-TR')}</span>
                  <h2>{order.name}</h2>
                  <p>{order.phone} · {order.size} · {order.quantity} adet</p>
                </div>
                <strong>{order.total} TL</strong>
              </div>

              <p className="order-address">
                {order.deliveryType}
                {order.deliveryType === 'Adrese Kargo' && (
                  <> — {order.city} / {order.district}, {order.address}</>
                )}
              </p>

              <div className="status-grid">
                <StatusSelect
                  label="Ödeme"
                  value={order.paymentStatus}
                  options={PAYMENT_STATUSES}
                  disabled={savingId === order.id}
                  onChange={(value) => updateOrder(order.id, { paymentStatus: value as PaymentStatus })}
                />
                <StatusSelect
                  label="Sipariş"
                  value={order.orderStatus}
                  options={ORDER_STATUSES}
                  disabled={savingId === order.id}
                  onChange={(value) => updateOrder(order.id, { orderStatus: value as OrderStatus })}
                />
                <StatusSelect
                  label="Üretim"
                  value={order.productionStatus}
                  options={PRODUCTION_STATUSES}
                  disabled={savingId === order.id}
                  onChange={(value) => updateOrder(order.id, { productionStatus: value as ProductionStatus })}
                />
                <StatusSelect
                  label="Teslimat"
                  value={order.deliveryStatus}
                  options={DELIVERY_STATUSES}
                  disabled={savingId === order.id}
                  onChange={(value) => updateOrder(order.id, { deliveryStatus: value as DeliveryStatus })}
                />
              </div>

              <label className="admin-notes">
                <span>Not</span>
                <textarea
                  defaultValue={order.notes}
                  rows={2}
                  onBlur={(event) => {
                    if (event.target.value !== order.notes) {
                      void updateOrder(order.id, { notes: event.target.value });
                    }
                  }}
                />
              </label>

              <button
                className="delete-order"
                type="button"
                disabled={savingId === order.id}
                onClick={() => deleteOrder(order)}
              >
                Siparişi Sil
              </button>
            </article>
          ))
        )}
      </section>
    </main>
  );
}

function StatusSelect({
  label,
  value,
  options,
  disabled,
  onChange,
}: {
  label: string;
  value: string;
  options: readonly string[];
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  return (
    <label>
      <span>{label}</span>
      <select value={value} disabled={disabled} onChange={(event) => onChange(event.target.value)}>
        {options.map((option) => (
          <option key={option} value={option}>{labels[option] ?? option}</option>
        ))}
      </select>
    </label>
  );
}
