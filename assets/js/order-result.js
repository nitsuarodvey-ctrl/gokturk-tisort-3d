(function ($) {
  'use strict';
  $(function () {
    const query = new URLSearchParams(window.location.search).get('order');
    const stored = sessionStorage.getItem('last_order_number');
    const order = query && stored && query === stored ? query : stored;
    if (order) {
      $('[data-order-number]').text(order);
      try {
        const details = JSON.parse(sessionStorage.getItem('last_order_details') || '{}');
        const items = Array.isArray(details.items) ? details.items : [];
        const $items = $('[data-result-items]').empty();
        items.forEach((item) => $items.append(`<div class="summary-row"><span>${item.product_name} · Beden ${item.size} · ${item.quantity} adet</span><strong>${window.Shop.money(item.line_total)}</strong></div>`));
        $('[data-result-delivery]').text(details.delivery_type === 'pickup' ? 'Genel merkezden elden teslim' : 'Kargo');
        $('[data-result-address]').text(details.address_summary || '').closest('.summary-row').toggle(Boolean(details.address_summary));
        $('[data-result-total]').text(window.Shop.money(details.total || 0));
      } catch (_) {
        $('[data-result-summary]').hide();
      }
    }
    else $('[data-success-content]').html('<h1>Sipariş bilgisi bulunamadı</h1><p>Bu sayfa yalnızca başarıyla kaydedilmiş bir siparişten sonra görüntülenebilir.</p><a class="button button-primary" href="index.html">Mağazaya dön</a>');
  });
})(jQuery);
