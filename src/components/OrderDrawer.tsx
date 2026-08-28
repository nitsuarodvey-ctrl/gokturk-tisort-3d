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

      <CardPaymentForm order={order} />

      <section className="payment-block" aria-labelledby="payment-title">
        <p className="eyebrow">ALTERNATİF ÖDEME</p>
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

function CardPaymentForm({ order }: { order: Order }) {
  const defaultAddress = [order.address, order.district].filter(Boolean).join(', ');

  return (
    <section className="payment-block card-payment-block" aria-labelledby="card-payment-title">
      <p className="eyebrow">GÜVENLİ ÖDEME</p>
      <h3 id="card-payment-title">Kredi / banka kartı</h3>
      <p className="card-security-note">
        Kuveyt Türk 3D Secure doğrulaması kullanılır. Kart bilgileriniz kaydedilmez.
      </p>
      <form className="card-payment-form" action="/api/payments" method="post">
        <input type="hidden" name="orderId" value={order.id} />
        <label>
          <span>Kart Üzerindeki İsim</span>
          <input name="cardHolderName" autoComplete="cc-name" minLength={2} maxLength={45} required />
        </label>
        <label>
          <span>Kart Numarası</span>
          <input
            name="cardNumber"
            inputMode="numeric"
            autoComplete="cc-number"
            minLength={13}
            maxLength={23}
            placeholder="____ ____ ____ ____"
            required
          />
        </label>
        <div className="card-grid-three">
          <label>
            <span>Ay</span>
            <input name="expiryMonth" inputMode="numeric" autoComplete="cc-exp-month" maxLength={2} placeholder="AA" required />
          </label>
          <label>
            <span>Yıl</span>
            <input name="expiryYear" inputMode="numeric" autoComplete="cc-exp-year" maxLength={4} placeholder="YY" required />
          </label>
          <label>
            <span>CVV</span>
            <input name="cvv" type="password" inputMode="numeric" autoComplete="cc-csc" maxLength={3} required />
          </label>
        </div>
        <label>
          <span>E-posta</span>
          <input name="email" type="email" autoComplete="email" maxLength={254} required />
        </label>
        <div className="form-pair">
          <label>
            <span>Fatura Şehri</span>
            <input name="billingCity" defaultValue={order.city} autoComplete="address-level1" maxLength={80} required />
          </label>
          <label>
            <span>İl Plaka Kodu</span>
            <input name="billingState" inputMode="numeric" maxLength={3} placeholder="35" required />
          </label>
        </div>
        <div className="form-pair">
          <label>
            <span>Posta Kodu</span>
            <input name="billingPostalCode" inputMode="numeric" autoComplete="postal-code" maxLength={10} required />
          </label>
          <span className="secure-amount">{order.total} TL</span>
        </div>
        <label>
          <span>Fatura Adresi</span>
          <textarea name="billingAddress" defaultValue={defaultAddress} autoComplete="street-address" maxLength={250} rows={3} required />
        </label>
        <button className="primary-cta" type="submit">
          <span>3D Secure ile Öde</span>
          <span>{order.total} TL</span>
        </button>
      </form>
    </section>
  );
}
