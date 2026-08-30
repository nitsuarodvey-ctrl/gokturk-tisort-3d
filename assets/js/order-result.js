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
        items.forEach((item) => {
          const quantity = Number.parseInt(item.quantity, 10);
          const lineTotal = Number.parseInt(item.line_total, 10);
          if (item.product_name !== 'SELÇUK T-SHIRT' || !['S', 'M', 'L', 'XL'].includes(item.size) || !Number.isInteger(quantity) || quantity < 1 || quantity > 10 || !Number.isInteger(lineTotal) || lineTotal < 0) return;
          const $row = $('<div>').addClass('summary-row');
          $('<span>').text(`${item.product_name} · Beden ${item.size} · ${quantity} adet`).appendTo($row);
          $('<strong>').text(window.Shop.money(lineTotal)).appendTo($row);
          $items.append($row);
        });
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
