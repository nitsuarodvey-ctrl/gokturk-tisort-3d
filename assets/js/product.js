(function ($) {
  'use strict';
  let currentView = 0;
  let touchStartX = 0;
  const views = ['whole', 'chest', 'sleeve'];

  function setView(index) {
    currentView = (index + views.length) % views.length;
    const view = views[currentView];
    $('.gallery-thumb').removeClass('is-active').attr('aria-current', 'false').eq(currentView).addClass('is-active').attr('aria-current', 'true');
    $('.gallery-main-image').removeClass('view-chest view-sleeve').toggleClass('view-chest', view === 'chest').toggleClass('view-sleeve', view === 'sleeve');
  }

  function selectedSize() { return $('input[name="size"]:checked').val(); }

  function add() {
    const size = selectedSize();
    const $message = $('.form-message');
    if (!size) {
      $message.text('Lütfen beden seçin.').addClass('is-visible').attr('role', 'alert');
      $('#size-s').trigger('focus');
      return;
    }
    window.Shop.addToCart(size, Number($('#quantity').text()) || 1);
    $message.text('Ürün sepete eklendi.').addClass('is-visible').attr('role', 'status');
  }

  $(function () {
    $('.gallery-thumb').on('click', function () { setView($(this).index()); });
    $('.quantity-control button').on('click', function () {
      const delta = $(this).data('delta');
      const $output = $('#quantity');
      $output.text(Math.max(1, Math.min(10, Number($output.text()) + delta)));
    });
    $('[data-add-to-cart]').on('click', add);
    $('input[name="size"]').on('change', () => $('.form-message').removeClass('is-visible'));

    const $stage = $('.gallery-stage');
    $stage.on('mouseenter mousemove', function (event) {
      if (window.matchMedia('(hover: hover)').matches) {
        const rect = this.getBoundingClientRect();
        $('.gallery-main-image').addClass('is-zooming').css('transform-origin', `${((event.clientX - rect.left) / rect.width) * 100}% ${((event.clientY - rect.top) / rect.height) * 100}%`);
      }
    }).on('mouseleave', () => $('.gallery-main-image').removeClass('is-zooming').css('transform-origin', 'center'));

    $stage.on('click', function () {
      $('.lightbox img').attr('class', $('.gallery-main-image').attr('class')).removeClass('gallery-main-image is-zooming');
      $('.lightbox').addClass('is-open').attr('aria-hidden', 'false');
      $('body').addClass('lightbox-open');
      $('.lightbox-close').trigger('focus');
    });
    $stage.on('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); $(this).trigger('click'); } });
    $('.lightbox-close, .lightbox').on('click', function (event) {
      if (event.target === this) { $('.lightbox').removeClass('is-open').attr('aria-hidden', 'true'); $('body').removeClass('lightbox-open'); }
    });
    $(document).on('keydown', (event) => { if (event.key === 'Escape') { $('.lightbox').removeClass('is-open').attr('aria-hidden', 'true'); $('body').removeClass('lightbox-open'); } });
    $stage.on('touchstart', (event) => { touchStartX = event.originalEvent.touches[0].clientX; });
    $stage.on('touchend', (event) => {
      const distance = event.originalEvent.changedTouches[0].clientX - touchStartX;
      if (Math.abs(distance) > 45) setView(currentView + (distance < 0 ? 1 : -1));
    });
  });
})(jQuery);
