(function ($) {
  'use strict';

  function render() {
    const items = window.Shop.getCart();
    const $list = $('.cart-list').empty();
    $('.empty-state').toggleClass('is-visible', items.length === 0);
    $('.cart-layout').toggle(items.length > 0);
    items.forEach((item) => {
      const row = $(`
        <article class="cart-item">
          <img src="${item.image}" alt="${item.name}" width="140" height="140">
          <div><p class="eyebrow">GÖKTÜRK ULUSAL BİRLİĞİ</p><h2>${item.name}</h2><p class="cart-meta">Beden: ${item.size}</p>
            <div class="quantity-control" aria-label="Adet seçimi"><button type="button" data-delta="-1" aria-label="Azalt">−</button><output>${item.quantity}</output><button type="button" data-delta="1" aria-label="Artır">+</button></div>
            <button class="remove-item" type="button">Sepetten çıkar</button>
          </div>
          <div class="cart-item-total"><span>${item.quantity} × ${window.Shop.money(item.price)}</span><strong>${window.Shop.money(item.price * item.quantity)}</strong></div>
        </article>`);
      row.find('[data-delta]').on('click', function () { window.Shop.updateItem(item.size, item.quantity + Number($(this).data('delta'))); });
      row.find('.remove-item').on('click', () => window.Shop.removeItem(item.size));
      $list.append(row);
    });
    $('[data-subtotal], [data-total]').text(window.Shop.money(window.Shop.subtotal()));
  }

  $(render);
  window.addEventListener('cart:changed', render);
})(jQuery);
