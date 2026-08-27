'use client';

import { FormEvent, useEffect, useRef, useState } from 'react';
import {
  DELIVERY_OPTIONS,
  DeliveryType,
  Order,
  orderRepository,
  ProductSize,
  UNIT_PRICE,
} from '../store/orders';

const IBAN = 'TR55 0001 2009 8800 0001 0338 78';

type Props = {
  open: boolean;
  initialSize: ProductSize;
  initialQuantity: number;
  onClose: () => void;
};

export function OrderDrawer({ open, initialSize, initialQuantity, onClose }: Props) {
  const [size, setSize] = useState<ProductSize>(initialSize);
  const [quantity, setQuantity] = useState(initialQuantity);
  const [deliveryType, setDeliveryType] = useState<DeliveryType>(DELIVERY_OPTIONS[0]);
  const [order, setOrder] = useState<Order | null>(null);
  const [copied, setCopied] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    const previous = document.activeElement as HTMLElement | null;
    window.requestAnimationFrame(() => closeRef.current?.focus());
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handleKey);
    document.body.classList.add('drawer-open');
    return () => {
      document.removeEventListener('keydown', handleKey);
      document.body.classList.remove('drawer-open');
      previous?.focus();
    };
  }, [open, initialQuantity, initialSize, onClose]);

  if (!open) return null;

  const submitOrder = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    setSubmitting(true);
    setSubmitError('');

    try {
      const nextOrder = await orderRepository.create({
        name: String(data.get('name') ?? '').trim(),
        phone: String(data.get('phone') ?? '').trim(),
        size,
        quantity,
        deliveryType,
        city: deliveryType === 'Adrese Kargo' ? String(data.get('city') ?? '').trim() : '',
        district:
          deliveryType === 'Adrese Kargo' ? String(data.get('district') ?? '').trim() : '',
        address:
          deliveryType === 'Adrese Kargo' ? String(data.get('address') ?? '').trim() : '',
      });
      setOrder(nextOrder);
    } catch (error) {
      console.error('[GUB preorder] Order creation failed:', error);
      setSubmitError(
        error instanceof Error
          ? error.message
          : 'Sipariş kaydedilemedi. Lütfen bilgilerinizi kontrol edip tekrar deneyin.',
      );
    } finally {
      setSubmitting(false);
    }
  };

  const copyIban = async () => {
    await navigator.clipboard.writeText(IBAN.replaceAll(' ', ''));
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1800);
  };

  return (
    <div className="drawer-layer" role="presentation" onMouseDown={onClose}>
      <aside
        className="order-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="order-drawer-title"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <header className="drawer-header">
          <div>
            <p className="eyebrow">GUB MERCH / DROP 001</p>
            <h2 id="order-drawer-title">{order ? 'Sipariş Özeti' : 'Ön Sipariş'}</h2>
          </div>
          <button ref={closeRef} className="text-button close-button" onClick={onClose}>
            Kapat <span aria-hidden="true">×</span>
          </button>
        </header>

        {order ? (
          <OrderSummary order={order} copied={copied} onCopy={copyIban} />
        ) : (
          <form className="order-form" onSubmit={submitOrder}>
            <label>
              <span>Ad Soyad</span>
              <input name="name" autoComplete="name" required />
            </label>
            <label>
              <span>Telefon</span>
              <input
                name="phone"
                type="tel"
                inputMode="tel"
                autoComplete="tel"
                placeholder="05__ ___ __ __"
                required
              />
            </label>

            <div className="form-pair">
              <label>
                <span>Beden</span>
                <select value={size} onChange={(event) => setSize(event.target.value as ProductSize)}>
                  {(['S', 'M', 'L', 'XL'] as ProductSize[]).map((item) => (
                    <option key={item}>{item}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Adet</span>
                <input
                  type="number"
                  min="1"
                  inputMode="numeric"
                  value={quantity}
                  onChange={(event) => setQuantity(Math.max(1, Number(event.target.value) || 1))}
                />
              </label>
            </div>

            <fieldset className="delivery-fieldset">
              <legend>Teslimat Yöntemi</legend>
              {DELIVERY_OPTIONS.map((option) => (
                <label className="delivery-radio" key={option}>
                  <input
                    type="radio"
                    name="deliveryType"
                    checked={deliveryType === option}
                    onChange={() => setDeliveryType(option)}
                  />
                  <span>{option}</span>
                </label>
              ))}
            </fieldset>

            {deliveryType === 'Adrese Kargo' && (
              <div className="address-fields">
                <div className="form-pair">
                  <label>
                    <span>Şehir</span>
                    <input name="city" autoComplete="address-level1" required />
                  </label>
                  <label>
                    <span>İlçe</span>
                    <input name="district" autoComplete="address-level2" required />
                  </label>
                </div>
                <label>
                  <span>Açık Adres</span>
                  <textarea name="address" autoComplete="street-address" rows={3} required />
                </label>
              </div>
            )}

            <div className="drawer-total">
              <span>{UNIT_PRICE} TL × {quantity}</span>
              <strong>{UNIT_PRICE * quantity} TL</strong>
            </div>
            {submitError && <p className="form-error" role="alert">{submitError}</p>}
            <button className="primary-cta" type="submit" disabled={submitting}>
              {submitting ? 'Kaydediliyor…' : 'Siparişi Oluştur'}
            </button>
          </form>
        )}
      </aside>
    </div>
  );
}

function OrderSummary({
  order,
  copied,
  onCopy,
}: {
  order: Order;
  copied: boolean;
  onCopy: () => void;
}) {
  return (
    <div className="order-summary">
      <dl className="summary-list">
        <div><dt>Ad Soyad</dt><dd>{order.name}</dd></div>
        <div><dt>Telefon</dt><dd>{order.phone}</dd></div>
        <div><dt>Beden</dt><dd>{order.size}</dd></div>
        <div><dt>Adet</dt><dd>{order.quantity}</dd></div>
        <div><dt>Teslimat</dt><dd>{order.deliveryType}</dd></div>
        <div className="summary-total"><dt>Toplam Tutar</dt><dd>{order.total} TL</dd></div>
      </dl>

      <section className="payment-block" aria-labelledby="payment-title">
        <p className="eyebrow">ÖDEME BİLGİLERİ</p>
        <h3 id="payment-title">Havale ile ödeme</h3>
        <dl>
          <div><dt>Alıcı</dt><dd>Göktürk Güngör</dd></div>
          <div className="iban-row">
            <dt>IBAN</dt>
            <dd>{IBAN}</dd>
            <button type="button" className="copy-button" onClick={onCopy}>
              {copied ? 'Kopyalandı' : 'Kopyala'}
            </button>
          </div>
          <div><dt>Açıklama</dt><dd>Dernek Tişört Bedeli</dd></div>
        </dl>
        <p className="payment-note">
          Ödeme sonrası dekontunuzu sipariş takibi için iletebilirsiniz.
        </p>
      </section>
    </div>
  );
}
