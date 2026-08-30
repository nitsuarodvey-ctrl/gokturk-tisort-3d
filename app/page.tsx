/* eslint-disable @next/next/no-html-link-for-pages */
import Image from 'next/image';
import { UNIT_PRICE } from '../src/store/orders';

const productHref = '/urun/gub-oversize-tisort';

function Brand() {
  return (
    <a className="shop-brand" href="/" aria-label="Göktürk Ulusal Birliği mağaza ana sayfası">
      <Image src="/ufaklogo.png" alt="" width={48} height={40} priority />
      <span>
        <strong>GÖKTÜRK ULUSAL BİRLİĞİ</strong>
        <small>RESMÎ MERCH MAĞAZASI</small>
      </span>
    </a>
  );
}

export default function ShopHome() {
  return (
    <main className="shop-shell">
      <header className="shop-header">
        <Brand />
        <nav className="shop-nav" aria-label="Ana menü">
          <a href="#urun">Ürün</a>
          <a href="#siparis">Sipariş</a>
          <a href="/bilgi">Bilgi</a>
        </nav>
        <a className="shop-header-action" href={productHref}>Ürünü incele <span>↗</span></a>
      </header>

      <section className="shop-hero" aria-labelledby="shop-title">
        <div className="shop-hero-copy">
          <p className="shop-kicker"><span /> RESMÎ KOLEKSİYON / DROP 001</p>
          <h1 id="shop-title">Birliğin<br /><em>işaretini</em><br />taşı.</h1>
          <p className="shop-lead">
            Göktürk Ulusal Birliği için tasarlanan ilk resmî parça.
            Sınırlı üretim siyah oversize tişört şimdi ön siparişte.
          </p>
          <div className="shop-actions">
            <a className="shop-button shop-button-primary" href={productHref}>3D incele ve sipariş ver</a>
            <a className="shop-text-link" href="/bilgi">Sipariş bilgileri <span>→</span></a>
          </div>

          <dl className="shop-hero-facts">
            <div><dt>Fiyat</dt><dd>{UNIT_PRICE} TL</dd></div>
            <div><dt>Kesim</dt><dd>Oversize</dd></div>
            <div><dt>Üretim</dt><dd>Sipariş üzerine</dd></div>
          </dl>
        </div>

        <div className="shop-hero-product" aria-label="Siyah oversize tişört görsel sunumu">
          <span className="shop-hero-word" aria-hidden="true">GUB</span>
          <span className="shop-edition-label">001 / İLK SERİ</span>
          <Image
            className="shop-product-photo shop-product-photo-hero"
            src="/gub-tshirt-front.png"
            alt="Göktürk Ulusal Birliği siyah oversize tişört"
            width={1254}
            height={1254}
            priority
          />
          <span className="shop-visual-price"><small>ÖN SİPARİŞ</small>{UNIT_PRICE} TL</span>
          <span className="shop-stock-badge"><i /> ÖN SİPARİŞ AÇIK</span>
        </div>
      </section>

      <section className="shop-trust-row" aria-label="Mağaza avantajları">
        <div><span>01</span><strong>3D Secure ödeme</strong><small>Kart bilgileriniz kaydedilmez</small></div>
        <div><span>02</span><strong>3 teslimat seçeneği</strong><small>Merkez, İzmir veya kargo</small></div>
        <div><span>03</span><strong>Sınırlı üretim</strong><small>Sipariş üzerine hazırlanır</small></div>
      </section>

      <section className="shop-product-section" id="urun" aria-labelledby="product-heading">
        <header className="shop-section-heading">
          <p>01 / TEK ÜRÜN</p>
          <h2 id="product-heading">İlk koleksiyon.<br />Tek ve net.</h2>
          <span>Yeni ürünler eklenene kadar mağazanın odağında yalnızca bu parça var.</span>
        </header>

        <article className="shop-product-card">
          <a className="shop-product-visual" href={productHref} aria-label="Siyah oversize tişörtü 3D incele">
            <span className="shop-grid" />
            <span className="shop-visual-index">DROP<br /><b>001</b></span>
            <Image
              className="shop-product-photo shop-product-photo-card"
              src="/gub-tshirt-front.png"
              alt="Göktürk Ulusal Birliği siyah oversize tişört"
              width={1254}
              height={1254}
            />
            <span className="shop-view-pill">360° CANLI ÜRÜN GÖRÜNÜMÜ ↗</span>
          </a>

          <div className="shop-product-info">
            <p className="shop-kicker"><span /> GUB MERCH / DROP 001</p>
            <h3>Siyah Oversize<br />T-Shirt</h3>
            <p className="shop-product-description">
              Göğüste kırmızı çift başlı kartal, sağ kolda Göktürk Ulusal Birliği arması.
              Güçlü, sade ve gündelik kullanıma uygun bir birlik parçası.
            </p>
            <ul className="shop-specs" aria-label="Ürün özellikleri">
              <li><span>01</span> Oversize kalıp</li>
              <li><span>02</span> Siyah kumaş</li>
              <li><span>03</span> S — XL beden</li>
              <li><span>04</span> Sipariş üzerine üretim</li>
            </ul>
            <div className="shop-product-buy">
              <div><small>ÖN SİPARİŞ FİYATI</small><strong>{UNIT_PRICE} TL</strong></div>
              <a className="shop-button shop-button-primary" href={productHref}>Beden seç ve sipariş ver</a>
            </div>
          </div>
        </article>
      </section>

      <section className="shop-order-section" id="siparis" aria-labelledby="order-heading">
        <div className="shop-order-title">
          <p>NASIL ÇALIŞIR?</p>
          <h2 id="order-heading">Üç adımda<br />sipariş.</h2>
          <a className="shop-text-link" href="/bilgi">Tüm sipariş ve teslimat bilgileri <span>→</span></a>
        </div>
        <ol className="shop-steps">
          <li><span>01</span><h3>Ürünü incele</h3><p>3D görünümde ön, arka ve kol baskılarını ayrıntılı olarak gör.</p></li>
          <li><span>02</span><h3>Seçimini yap</h3><p>Bedenini, adedi ve sana uygun teslimat yöntemini belirle.</p></li>
          <li><span>03</span><h3>Siparişi tamamla</h3><p>Bilgilerini gönder; kartla 3D Secure veya havale ile ödemeni tamamla.</p></li>
        </ol>
      </section>

      <section className="shop-association" aria-labelledby="association-heading">
        <div className="shop-association-mark">
          <Image src="/büyüklogo.png" alt="Göktürk Ulusal Birliği arması" width={280} height={280} />
        </div>
        <div>
          <p>GÖKTÜRK ULUSAL BİRLİĞİ</p>
          <h2 id="association-heading">Bir fikrin etrafında,<br />aynı işaret altında.</h2>
          <p className="shop-association-copy">
            GUB Merch, derneğin kimliğini gündelik hayata taşıyan resmî ürünler için kuruldu.
            Her parça küçük seriler hâlinde ve sipariş odaklı hazırlanır.
          </p>
          <a className="shop-text-link" href="/bilgi">Mağaza hakkında <span>→</span></a>
        </div>
      </section>

      <footer className="shop-footer">
        <Brand />
        <div className="shop-footer-links">
          <a href={productHref}>Ürün</a>
          <a href="/bilgi">Bilgi</a>
        </div>
        <p>© 2026 GÖKTÜRK ULUSAL BİRLİĞİ</p>
      </footer>

      <a className="shop-mobile-buybar" href={productHref}>
        <span><small>DROP 001</small>Siyah Oversize Tişört</span>
        <strong>{UNIT_PRICE} TL <i>→</i></strong>
      </a>
    </main>
  );
}
