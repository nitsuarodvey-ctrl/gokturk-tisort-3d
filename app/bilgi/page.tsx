/* eslint-disable @next/next/no-html-link-for-pages */
import type { Metadata } from 'next';
import Image from 'next/image';
import { UNIT_PRICE } from '../../src/store/orders';

export const metadata: Metadata = {
  title: 'Sipariş ve Mağaza Bilgileri — GUB Merch',
  description: 'Göktürk Ulusal Birliği resmî mağazası; sipariş, ödeme ve teslimat bilgileri.',
  openGraph: {
    title: 'Sipariş ve Mağaza Bilgileri — GUB Merch',
    description: 'Göktürk Ulusal Birliği resmî mağazası; sipariş, ödeme ve teslimat bilgileri.',
    type: 'website',
    images: [],
  },
  twitter: {
    card: 'summary',
    title: 'Sipariş ve Mağaza Bilgileri — GUB Merch',
    description: 'Göktürk Ulusal Birliği resmî mağazası; sipariş, ödeme ve teslimat bilgileri.',
    images: [],
  },
};

const productHref = '/urun/gub-oversize-tisort';

export default function InformationPage() {
  return (
    <main className="shop-shell info-page">
      <header className="shop-header">
        <a className="shop-brand" href="/" aria-label="Göktürk Ulusal Birliği mağaza ana sayfası">
          <Image src="/ufaklogo.png" alt="" width={48} height={40} priority />
          <span><strong>GÖKTÜRK ULUSAL BİRLİĞİ</strong><small>RESMÎ MERCH MAĞAZASI</small></span>
        </a>
        <nav className="shop-nav" aria-label="Ana menü">
          <a href="/">Ana sayfa</a>
          <a href={productHref}>Ürün</a>
          <a href="#teslimat">Teslimat</a>
        </nav>
        <a className="shop-header-action" href={productHref}>Sipariş ver <span>↗</span></a>
      </header>

      <section className="info-hero">
        <div>
          <p className="shop-kicker"><span /> MAĞAZA REHBERİ</p>
          <h1>Bilgi.<br /><em>Net ve açık.</em></h1>
        </div>
        <p>
          Ürün, sipariş, ödeme ve teslimat süreciyle ilgili temel bilgilerin tamamı burada.
          Sipariş vermeye hazır olduğunda ürün sayfasından bedenini seçebilirsin.
        </p>
      </section>

      <nav className="info-index" aria-label="Bilgi sayfası bölümleri">
        <a href="#magaza"><span>01</span> Mağaza</a>
        <a href="#siparis"><span>02</span> Sipariş</a>
        <a href="#odeme"><span>03</span> Ödeme</a>
        <a href="#teslimat"><span>04</span> Teslimat</a>
      </nav>

      <div className="info-content">
        <section className="info-block" id="magaza">
          <div className="info-block-title"><span>01</span><p>MAĞAZA HAKKINDA</p></div>
          <div className="info-block-copy">
            <h2>Derneğin resmî<br />ürün noktası.</h2>
            <p>
              GUB Merch, Göktürk Ulusal Birliği adına hazırlanan resmî ürünleri tek noktada
              sunar. İlk koleksiyonda {UNIT_PRICE} TL fiyatlı siyah oversize tişört yer alır.
              Ürünler küçük seriler hâlinde ve sipariş üzerine üretilir.
            </p>
          </div>
        </section>

        <section className="info-block" id="siparis">
          <div className="info-block-title"><span>02</span><p>SİPARİŞ SÜRECİ</p></div>
          <div className="info-block-copy">
            <h2>Seç, bilgilerini gir,<br />siparişini oluştur.</h2>
            <div className="info-mini-grid">
              <div><b>01</b><h3>Ürün seçimi</h3><p>3D görünümden baskıları incele; S, M, L veya XL bedenini seç.</p></div>
              <div><b>02</b><h3>Sipariş bilgileri</h3><p>Ad, telefon, adet ve teslimat bilgilerini eksiksiz gir.</p></div>
              <div><b>03</b><h3>Onay ve ödeme</h3><p>Sipariş kaydından sonra tercih ettiğin yöntemle ödemeyi tamamla.</p></div>
            </div>
          </div>
        </section>

        <section className="info-block" id="odeme">
          <div className="info-block-title"><span>03</span><p>ÖDEME</p></div>
          <div className="info-block-copy">
            <h2>Güvenli ödeme,<br />iki seçenek.</h2>
            <div className="info-payment-cards">
              <article><span>KARTLA ÖDEME</span><h3>3D Secure</h3><p>Kart ödemesi banka doğrulama ekranında tamamlanır. Kart bilgileri mağazada saklanmaz.</p></article>
              <article><span>ALTERNATİF</span><h3>Havale</h3><p>Sipariş oluşturulduktan sonra ekranda gösterilen hesap bilgileriyle ödeme yapılabilir.</p></article>
            </div>
          </div>
        </section>

        <section className="info-block" id="teslimat">
          <div className="info-block-title"><span>04</span><p>TESLİMAT</p></div>
          <div className="info-block-copy">
            <h2>Sana uygun teslimat<br />yöntemini seç.</h2>
            <ul className="info-delivery-list">
              <li><span>Genel Merkez</span><p>Siparişini Genel Merkezden teslim al.</p></li>
              <li><span>İzmir elden teslim</span><p>İzmir için elden teslim seçeneğini kullan.</p></li>
              <li><span>Adrese kargo</span><p>Şehir, ilçe ve açık adresini gir. Kargo ücreti alıcıya aittir.</p></li>
            </ul>
          </div>
        </section>
      </div>

      <section className="info-cta">
        <p>HAZIRSAN BAŞLAYALIM</p>
        <h2>Ürünü 360° incele.<br />Bedenini seç. Sipariş ver.</h2>
        <a className="shop-button shop-button-primary" href={productHref}>Ürün sayfasına git</a>
      </section>

      <footer className="shop-footer">
        <a className="shop-brand" href="/">
          <Image src="/ufaklogo.png" alt="" width={48} height={40} />
          <span><strong>GÖKTÜRK ULUSAL BİRLİĞİ</strong><small>RESMÎ MERCH MAĞAZASI</small></span>
        </a>
        <div className="shop-footer-links"><a href="/">Ana sayfa</a><a href={productHref}>Ürün</a></div>
        <p>© 2026 GÖKTÜRK ULUSAL BİRLİĞİ</p>
      </footer>
    </main>
  );
}
