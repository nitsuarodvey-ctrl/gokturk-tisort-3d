(function ($) {
  'use strict';
  $(function () {
    $('#contact-form').on('submit', async function (event) {
      event.preventDefault();
      if (!this.checkValidity()) return this.reportValidity();
      const form = this;
      const $button = $(form).find('button[type="submit"]').prop('disabled', true).text('Gönderiliyor…');
      const $message = $('.status-message').removeClass('is-visible is-success');
      try {
        const response = await fetch('api/contact.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(Object.fromEntries(new FormData(form).entries())) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.message || 'Mesaj gönderilemedi.');
        form.reset();
        $message.text('Mesajınız alındı. En kısa sürede size dönüş yapacağız.').addClass('is-visible is-success').attr('role', 'status');
      } catch (error) {
        $message.text(error.message).addClass('is-visible').attr('role', 'alert');
      } finally {
        $button.prop('disabled', false).text('Mesajı gönder');
      }
    });
  });
})(jQuery);
