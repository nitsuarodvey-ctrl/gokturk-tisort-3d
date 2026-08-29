import Image from 'next/image';
import Link from 'next/link';
import { UNIT_PRICE } from '../src/store/orders';

const featuredProduct = {
  href: '/urun/gub-oversize-tisort',
  name: 'Siyah Oversize T-Shirt',
  edition: 'Drop 001',
  price: UNIT_PRICE,
};

export default function ShopHome() {
  return (
    <main className="merch-home">
      <header className="merch-header">
        <Link className="merch-brand" href="/" aria-label="GUB Merch ana sayfa">
          <Image src="/ufaklogo.png" alt="" width={44} height={36} priority />
          <span>
            <strong>GUB</strong>
            <small>MERCH</small>
          </span>
        </Link>

        <nav className="merch-nav" aria-label="Ana menü">
          <a href="#urunler">Ürünler</a>
          <a href="#manifesto">Hakkımızda</a>
        </nav>

        <span className="merch-edition">RESMÎ MAĞAZA / 2026</span>
      </header>

      <section className="merch-hero" aria-labelledby="merch-hero-title">
        <div className="hero-copy">
          <p className="eyebrow">GÖKTÜRK ULUSAL BİRLİĞİ / MERCH</p>
          <h1 id="merch-hero-title">
            Birliğin ruhunu
            <span>üzerinde taşı.</span>
          </h1>
          <p className="hero-intro">
            Göktürk Ulusal Birliği için hazırlanan sınırlı üretim parçalar.
            İlk koleksiyon siyah oversize tişört ile başlıyor.
          </p>
          <div className="hero-actions">
            <a className="hero-primary" href="#urunler">Koleksiyonu gör</a>
            <Link className="hero-secondary" href={featuredProduct.href}>3D incele <span>↗</span></Link>
          </div>
        </div>

        <div className="hero-emblem" aria-hidden="true">
          <span className="hero-orbit hero-orbit-outer" />
          <span className="hero-orbit hero-orbit-inner" />
          <Image src="/büyüklogo.png" alt="" width={760} height={760} priority />
          <span className="hero-drop-number">001</span>
        </div>

        <div className="hero-index" aria-hidden="true">
          <span>01</span>
          <i />
          <span>02</span>
        </div>
      </section>

      <section className="collection-section" id="urunler" aria-labelledby="collection-title">
        <div className="collection-heading">
          <div>
            <p className="eyebrow">KOLEKSİYON / DROP 001</p>
            <h2 id="collection-title">Şimdi satışta</h2>
          </div>
          <p>İlk parça. Sınırlı üretim.<br />Yeni ürünler yakında.</p>
        </div>

        <div className="product-grid">
          <Link className="featured-product-card" href={featuredProduct.href}>
            <div className="product-art" aria-hidden="true">
              <span className="product-art-grid" />
              <div className="shirt-art">
                <span className="shirt-body">
                  <Image src="/ufaklogo.png" alt="" width={114} height={92} />
                </span>
                <span className="shirt-sleeve shirt-sleeve-left" />
                <span className="shirt-sleeve shirt-sleeve-right" />
                <span className="shirt-neck" />
              </div>
              <span className="product-view-badge">360° Görünüm</span>
              <span className="product-number">01</span>
            </div>

            <div className="product-card-copy">
              <div>
                <p>{featuredProduct.edition}</p>
                <h3>{featuredProduct.name}</h3>
              </div>
              <div className="product-card-price">
                <strong>{featuredProduct.price} TL</strong>
                <span>Ürünü incele ↗</span>
              </div>
            </div>
          </Link>

          <div className="coming-product-card" aria-label="Yeni ürün yakında">
            <span>02</span>
            <p>Yeni parça<br />yakında</p>
          </div>
        </div>
      </section>

      <section className="merch-manifesto" id="manifesto">
        <p className="eyebrow">BİRLİKTEN DOĞAN TASARIM</p>
        <blockquote>
          Sadece bir ürün değil.<br />Aynı fikri taşıyanların işareti.
        </blockquote>
        <p>
          Her parça derneğin kimliğini gündelik hayata taşımak için tasarlanır.
          Küçük seriler hâlinde, sipariş üzerine üretilir.
        </p>
      </section>

      <footer className="merch-footer">
        <div className="merch-brand footer-brand">
          <Image src="/ufaklogo.png" alt="" width={40} height={32} />
          <span><strong>GUB</strong><small>MERCH</small></span>
        </div>
        <p>Göktürk Ulusal Birliği resmî ürün mağazası.</p>
        <span>© 2026</span>
      </footer>
    </main>
  );
}
