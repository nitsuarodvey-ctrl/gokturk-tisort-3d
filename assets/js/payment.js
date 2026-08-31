(function () {
  'use strict';
  const orderNumber = sessionStorage.getItem('last_order_number') || '';
  const paymentToken = sessionStorage.getItem('payment_token') || '';
  let details = null;
  try { details = JSON.parse(sessionStorage.getItem('last_order_details') || 'null'); } catch (_) { details = null; }
  const form = document.getElementById('card-payment-form');
  const message = document.querySelector('[data-payment-message]');
  if (!form || !orderNumber || !/^[a-f0-9]{64}$/.test(paymentToken) || !details) {
    if (form) form.hidden = true;
    if (message) message.textContent = 'Ödeme bağlantısı bulunamadı veya süresi doldu. Lütfen siparişi yeniden oluşturun.';
    return;
  }
  form.elements.order_number.value = orderNumber;
  form.elements.payment_token.value = paymentToken;
  document.querySelector('[data-payment-order]').textContent = orderNumber;
  document.querySelector('[data-payment-total]').textContent = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(Number(details.total) || 0);
  if (details.billing) {
    form.elements.billing_city.value = details.billing.city || '';
    form.elements.billing_postal_code.value = details.billing.postal_code || '';
    form.elements.billing_address.value = details.billing.address || '';
  }
  form.addEventListener('submit', function () {
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Banka ekranına bağlanılıyor…';
  });
})();
