'use client';

import { ProductSize, UNIT_PRICE } from '../store/orders';

type Props = {
  size: ProductSize;
  quantity: number;
  onSizeChange: (size: ProductSize) => void;
  onQuantityChange: (quantity: number) => void;
  onOpenSizeGuide: () => void;
  onPreorder: () => void;
};

const sizes: ProductSize[] = ['S', 'M', 'L', 'XL'];

export function ProductInfo({
  size,
  quantity,
  onSizeChange,
  onQuantityChange,
  onOpenSizeGuide,
  onPreorder,
}: Props) {
  return (
    <section className="product-panel" aria-labelledby="product-title">
      <div className="product-copy">
        <p className="eyebrow">GUB MERCH / DROP 001</p>
        <h1 id="product-title">Siyah Oversize<br />T-Shirt</h1>
        <div className="price-line">
          <strong>{UNIT_PRICE} TL</strong>
          <span>Ön Sipariş</span>
        </div>

        <p className="description">
          Siyah oversize kesim.<br />
          Göğüste kırmızı çift başlı kartal detayı.<br />
          Sağ kolda Göktürk Ulusal Birliği arması.<br />
          Sipariş üzerine üretilir.
        </p>
      </div>

      <div className="purchase-controls">
        <div className="section-heading">
          <span>Beden</span>
          <button type="button" className="text-button" onClick={onOpenSizeGuide}>
            Beden Tablosu
          </button>
        </div>
        <div className="size-selector" aria-label="Beden seçimi">
          {sizes.map((item) => (
            <button
              type="button"
              key={item}
              className={size === item ? 'selected' : ''}
              aria-pressed={size === item}
              onClick={() => onSizeChange(item)}
            >
              {item}
            </button>
          ))}
        </div>
        <p className="flat-width-note">Göğüs ölçüsü düz ürün genişliğidir.</p>

        <div className="quantity-row">
          <span>Adet</span>
          <div className="quantity-selector">
            <button
              type="button"
              aria-label="Adedi azalt"
              onClick={() => onQuantityChange(Math.max(1, quantity - 1))}
              disabled={quantity === 1}
            >
              −
            </button>
            <output aria-live="polite">{quantity}</output>
            <button
              type="button"
              aria-label="Adedi artır"
              onClick={() => onQuantityChange(quantity + 1)}
            >
              +
            </button>
          </div>
        </div>

        <button className="primary-cta desktop-cta" type="button" onClick={onPreorder}>
          <span>Ön Sipariş Ver</span>
          <span>{UNIT_PRICE * quantity} TL</span>
        </button>
        <p className="production-note">Ürün sipariş üzerine üretilmektedir.</p>
      </div>

      <div className="delivery-info">
        <p className="section-label">TESLİMAT SEÇENEKLERİ</p>
        <ul>
          <li>Genel Merkezden Teslim</li>
          <li>İzmir Elden Teslim</li>
          <li>Adrese Kargo</li>
        </ul>
        <p>Kargo ücreti alıcıya aittir.</p>
      </div>
    </section>
  );
}
