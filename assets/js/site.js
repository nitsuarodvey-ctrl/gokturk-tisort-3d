(function ($) {
  'use strict';

  const CART_KEY = 'gokturk_ulusal_birligi_cart_v1';
  const PRODUCT = Object.freeze({
    id: 'selcuk-tshirt',
    name: 'SELÇUK T-SHIRT',
    subtitle: 'Çift başlı kartal işlemeli siyah oversize tişört.',
    price: 499,
    image: 'assets/img/selcuk-tshirt.png'
  });
  const SIZES = Object.freeze(['S', 'M', 'L', 'XL']);

  const money = (value) => `${new Intl.NumberFormat('tr-TR', {
    maximumFractionDigits: 0
  }).format(value)} TL`;

  function getCart() {
    try {
      const parsed = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
      if (!Array.isArray(parsed)) return [];
      const normalized = [];
      parsed.forEach((item) => {
        if (!item || item.id !== PRODUCT.id || !SIZES.includes(item.size)) return;
        const quantity = Number.parseInt(item.quantity, 10);
        if (!Number.isInteger(quantity) || quantity < 1) return;
        const remaining = 10 - normalized.reduce((sum, entry) => sum + entry.quantity, 0);
        if (remaining < 1) return;
        const acceptedQuantity = Math.min(quantity, remaining);
        const existing = normalized.find((entry) => entry.size === item.size);
        if (existing) existing.quantity += acceptedQuantity;
        else normalized.push({ ...PRODUCT, size: item.size, quantity: acceptedQuantity });
      });
      return normalized;
    } catch (_) {
      return [];
    }
  }

  function saveCart(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
    window.dispatchEvent(new CustomEvent('cart:changed', { detail: items }));
  }

  function addToCart(size, quantity) {
    if (!SIZES.includes(size)) return getCart();
    const qty = Math.max(1, Math.min(10, Number(quantity) || 1));
    const items = getCart();
    const available = 10 - items.reduce((sum, item) => sum + item.quantity, 0);
    if (available < 1) return items;
    const acceptedQuantity = Math.min(qty, available);
    const existing = items.find((item) => item.id === PRODUCT.id && item.size === size);
    if (existing) existing.quantity += acceptedQuantity;
    else items.push({ ...PRODUCT, size, quantity: acceptedQuantity });
    saveCart(items);
    return items;
  }

  function updateItem(size, quantity) {
    if (!SIZES.includes(size)) return;
    const items = getCart();
    const otherQuantity = items.filter((item) => item.size !== size).reduce((sum, item) => sum + item.quantity, 0);
    const qty = Math.max(0, Math.min(10 - otherQuantity, Number(quantity) || 0));
    const updatedItems = items
      .map((item) => item.size === size ? { ...item, quantity: qty } : item)
      .filter((item) => item.quantity > 0);
    saveCart(updatedItems);
  }

  function removeItem(size) {
    if (!SIZES.includes(size)) return;
    saveCart(getCart().filter((item) => item.size !== size));
  }

  function clearCart() { saveCart([]); }
  function itemCount() { return getCart().reduce((sum, item) => sum + item.quantity, 0); }
  function subtotal() { return getCart().reduce((sum, item) => sum + item.price * item.quantity, 0); }

  window.Shop = { PRODUCT, money, getCart, addToCart, updateItem, removeItem, clearCart, itemCount, subtotal };

  const header = `
    <header class="site-header">
      <div class="site-container header-inner">
        <a class="brand" href="index.html" aria-label="GÖKTÜRK ULUSAL BİRLİĞİ ana sayfa">
          <img src="assets/img/logo.png" alt="" width="44" height="44">
          <span><strong>GÖKTÜRK ULUSAL BİRLİĞİ</strong><small>RESMÎ MAĞAZA</small></span>
        </a>
        <nav class="desktop-nav" aria-label="Ana menü">
          <a href="index.html">Mağaza</a><a href="hakkimizda.html">Hakkımızda</a>
          <a href="teslimat.html">Teslimat</a><a href="sikca-sorulan-sorular.html">SSS</a><a href="iletisim.html">İletişim</a>
        </nav>
        <div class="header-actions">
          <a class="cart-link" href="sepet.html" aria-label="Sepet"><span>Sepet</span><b class="cart-count" aria-live="polite">0</b></a>
          <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-navigation" aria-label="Menüyü aç"><span></span><span></span><span></span></button>
        </div>
      </div>
      <div class="mobile-nav" id="mobile-navigation">
        <nav class="site-container" aria-label="Mobil menü">
          <a href="index.html">Mağaza</a><a href="hakkimizda.html">Hakkımızda</a><a href="teslimat.html">Teslimat</a>
          <a href="sikca-sorulan-sorular.html">Sıkça Sorulan Sorular</a><a href="iletisim.html">İletişim</a><a href="siparis-takip.html">Sipariş Takip</a>
        </nav>
      </div>
    </header>`;

  const footer = `
    <footer class="site-footer">
      <div class="site-container footer-main">
        <div><p class="footer-name">GÖKTÜRK ULUSAL BİRLİĞİ</p><p>Dernek ürünlerini güvenli ve sade bir alışveriş deneyimiyle sunan resmî mağaza.</p></div>
        <nav class="footer-links" aria-label="Mağaza bağlantıları"><strong>Mağaza</strong><a href="urun.html">SELÇUK T-SHIRT</a><a href="sepet.html">Sepet</a><a href="siparis-takip.html">Sipariş Takip</a></nav>
        <nav class="footer-links" aria-label="Bilgi bağlantıları"><strong>Bilgi</strong><a href="hakkimizda.html">Hakkımızda</a><a href="teslimat.html">Teslimat</a><a href="degisim-iade.html">Değişim ve İade</a><a href="sikca-sorulan-sorular.html">SSS</a></nav>
        <nav class="legal-links" aria-label="Yasal bağlantılar"><strong>Yasal</strong><a href="kvkk.html">KVKK Aydınlatma</a><a href="gizlilik.html">Gizlilik</a><a href="kullanim-kosullari.html">Kullanım Koşulları</a><a href="mesafeli-satis-sozlesmesi.html">Mesafeli Satış Sözleşmesi</a></nav>
      </div>
      <div class="site-container footer-bottom"><span>© ${new Date().getFullYear()} GÖKTÜRK ULUSAL BİRLİĞİ</span><a href="iletisim.html">İletişim</a></div>
    </footer>`;

  function updateCartCount() {
    $('.cart-count').text(itemCount()).attr('aria-label', itemCount() + ' ürün');
  }

  $(function () {
    $('[data-site-header]').replaceWith(header);
    $('[data-site-footer]').replaceWith(footer);
    updateCartCount();

    const $toggle = $('.menu-toggle');
    const $mobile = $('.mobile-nav');
    $toggle.on('click', function () {
      const open = !$mobile.hasClass('is-open');
      $mobile.toggleClass('is-open', open);
      $('body').toggleClass('menu-open', open);
      $toggle.attr('aria-expanded', open).attr('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
    });
    $mobile.on('click', 'a', () => $toggle.trigger('click'));
    $(document).on('keydown', (event) => {
      if (event.key === 'Escape' && $mobile.hasClass('is-open')) $toggle.trigger('click');
    });

    $('.faq-button').on('click', function () {
      const $item = $(this).closest('.faq-item');
      const open = !$item.hasClass('is-open');
      $item.toggleClass('is-open', open);
      $(this).attr('aria-expanded', open);
    });
  });

  window.addEventListener('cart:changed', updateCartCount);
})(jQuery);
