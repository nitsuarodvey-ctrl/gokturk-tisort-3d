(function ($) {
  'use strict';

  function renderSummary() {
    const items = window.Shop.getCart();
    const $target = $('[data-checkout-items]').empty();
    if (!items.length) {
      window.location.replace('sepet.html');
      return;
    }
    items.forEach((item) => $target.append(`<div class="summary-row"><span>${item.name} · ${item.size} × ${item.quantity}</span><strong>${window.Shop.money(item.price * item.quantity)}</strong></div>`));
    $('[data-subtotal], [data-total]').text(window.Shop.money(window.Shop.subtotal()));
  }

  function setDelivery() {
    const cargo = $('input[name="delivery_type"]:checked').val() === 'cargo';
    $('[data-address-fields]').toggle(cargo).find('input, textarea').prop('required', cargo);
  }

  $(function () {
    renderSummary();
    setDelivery();
    $('input[name="delivery_type"]').on('change', setDelivery);

    $('#checkout-form').on('submit', async function (event) {
      event.preventDefault();
      const form = this;
      const $message = $('.status-message');
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      const $button = $(form).find('button[type="submit"]');
      $button.prop('disabled', true).text('Sipariş kaydediliyor…');
      $message.removeClass('is-visible is-success');
      const data = Object.fromEntries(new FormData(form).entries());
      data.items = window.Shop.getCart().map(({ id, size, quantity }) => ({ id, size, quantity }));
      try {
        const response = await fetch('api/order-create.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin', body: JSON.stringify(data)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.message || 'Sipariş şu anda kaydedilemedi.');
        sessionStorage.setItem('last_order_number', result.order_number);
        sessionStorage.setItem('last_order_details', JSON.stringify(result.order));
        window.Shop.clearCart();
        window.location.assign(`siparis-basarili.html?order=${encodeURIComponent(result.order_number)}`);
      } catch (error) {
        $message.text(error.message || 'Bir bağlantı hatası oluştu. Lütfen tekrar deneyin.').addClass('is-visible').attr('role', 'alert');
        $button.prop('disabled', false).text('Siparişi oluştur');
      }
    });
  });
})(jQuery);
