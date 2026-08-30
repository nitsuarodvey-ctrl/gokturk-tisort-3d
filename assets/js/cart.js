(function ($) {
  'use strict';

  function render() {
    const items = window.Shop.getCart();
    const itemCount = window.Shop.itemCount();
    const $list = $('.cart-list').empty();
    $('.empty-state').toggleClass('is-visible', items.length === 0);
    $('.cart-layout').toggle(items.length > 0);
    items.forEach((item) => {
      const row = $(`
        <article class="cart-item">
          <img width="140" height="140">
          <div><p class="eyebrow">GÖKTÜRK ULUSAL BİRLİĞİ</p><h2></h2><p class="cart-meta"></p>
            <div class="quantity-control" aria-label="Adet seçimi"><button type="button" data-delta="-1" aria-label="Azalt">−</button><output></output><button type="button" data-delta="1" aria-label="Artır">+</button></div>
            <button class="remove-item" type="button">Sepetten çıkar</button>
          </div>
          <div class="cart-item-total"><span></span><strong></strong></div>
        </article>`);
      row.find('img').attr({ src: item.image, alt: item.name });
      row.find('h2').text(item.name);
      row.find('.cart-meta').text(`Beden: ${item.size}`);
      row.find('output').text(item.quantity);
      row.find('.cart-item-total span').text(`${item.quantity} × ${window.Shop.money(item.price)}`);
      row.find('.cart-item-total strong').text(window.Shop.money(item.price * item.quantity));
      row.find('[data-delta="1"]').prop('disabled', itemCount >= 10);
      row.find('[data-delta]').on('click', function () { window.Shop.updateItem(item.size, item.quantity + Number($(this).data('delta'))); });
      row.find('.remove-item').on('click', () => window.Shop.removeItem(item.size));
      $list.append(row);
    });
    $('[data-subtotal], [data-total]').text(window.Shop.money(window.Shop.subtotal()));
  }

  $(render);
  window.addEventListener('cart:changed', render);
})(jQuery);
