(function ($) {
  'use strict';
  const labels = { received: 'Sipariş alındı', preparing: 'Hazırlanıyor', shipped: 'Kargoya verildi', delivered: 'Teslim edildi', cancelled: 'İptal edildi' };
  const order = ['received', 'preparing', 'ready', 'shipped', 'delivered'];

  $(function () {
    $('#tracking-form').on('submit', async function (event) {
      event.preventDefault();
      if (!this.checkValidity()) return this.reportValidity();
      const $message = $('.status-message').removeClass('is-visible');
      const $button = $(this).find('button[type="submit"]').prop('disabled', true).text('Sorgulanıyor…');
      try {
        const response = await fetch('api/order-track.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(Object.fromEntries(new FormData(this).entries())) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.message || 'Sipariş bulunamadı.');
        $('[data-track-number]').text(result.order.order_number);
        const statusTitle = result.order.status === 'ready' ? (result.order.delivery_type === 'pickup' ? 'Genel merkezde hazır' : 'Hazır') : (labels[result.order.status] || 'İşleniyor');
        $('[data-track-status]').text(statusTitle);
        const pickup = result.order.delivery_type === 'pickup';
        $('[data-ready-step]').text(pickup ? 'Genel merkezde hazır' : 'Kargoya verildi');
        $('[data-final-step]').text('Teslim edildi');
        const progress = pickup ? ['received', 'preparing', 'ready', 'delivered'] : ['received', 'preparing', 'shipped', 'delivered'];
        const current = progress.indexOf(result.order.status);
        $('.timeline li').each(function (index) { $(this).toggleClass('is-done', current >= index); });
        $('.track-result').addClass('is-visible');
      } catch (error) {
        $('.track-result').removeClass('is-visible');
        $message.text(error.message).addClass('is-visible').attr('role', 'alert');
      } finally {
        $button.prop('disabled', false).text('Siparişi sorgula');
      }
    });
  });
})(jQuery);
