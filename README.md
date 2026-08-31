# GÖKTÜRK ULUSAL BİRLİĞİ Resmî Mağaza

Build işlemi gerektirmeyen HTML5, CSS3, Bootstrap 5, jQuery ve PHP/MySQL mağaza projesidir. Dosyalar cPanel `public_html` dizinine FTP ile yüklenerek çalıştırılabilir.

## cPanel kurulumu

1. Projedeki dosya ve klasörleri `public_html` içine yükleyin.
2. cPanel > MySQL Databases bölümünden veritabanı ve sınırlı yetkili bir kullanıcı oluşturun.
3. phpMyAdmin üzerinden `api/database.sql` dosyasını içe aktarın.
4. `.env.example` içeriğini `public_html` klasörünün **bir üstündeki** `.env` dosyasına yazın; alan adı, veritabanı ve yönetici değerlerini doldurun. `APP_KEY` için kriptografik olarak rastgele en az 32 karakter kullanın. Üretim modu, sır içeren `.env` dosyasının proje/web dizini içinde bulunmasını bilinçli olarak reddeder.
5. Dışarıdaki `.env` dosya iznini mümkünse `600`, PHP dosyalarını `644` yapın.
6. Alan adında geçerli SSL sertifikasını etkinleştirin ve PHP 8.1 veya üstünü seçin.
7. `teslimat.html`, `iletisim.html` ve hukuki sayfalardaki açık bilgi alanlarını yetkili/hukuk danışmanı onayıyla doldurun.
8. Yönetim girişi için `.env` içindeki `ADMIN_EMAIL` değerini ve PHP `password_hash($parola, PASSWORD_DEFAULT, ['cost' => 12])` ile üretilmiş `ADMIN_PASSWORD_HASH` değerini ayarlayın. Yönetim adresi `/admin/login.php` olur.

`.env` kaynak kod deposuna alınmaz ve web kökünün dışında tutulur. Üretimde `APP_URL` mutlaka `https://` ile başlamalı ve `SESSION_SECURE=true` olmalıdır. Sipariş fiyatı tarayıcıdan kabul edilmez; sunucuda 499 TL üzerinden yeniden hesaplanır. Kart verisi yalnızca ödeme başlatma isteği sırasında bellekte işlenir; veritabanına, oturuma veya loglara yazılmaz.

## Ödeme entegrasyonu

Kuveyt Türk FreePos 3D Secure iki aşamalı akış kullanılır. `api/payment-start.php` bankanın 3D ödeme ekranını başlatır; banka dönüşü `api/payment-callback.php` adresine gelir. Callback imzası, işyeri numarası, sipariş numarası ve kuruş cinsinden tutar doğrulandıktan sonra ikinci provizyon isteği gönderilir. Sipariş ancak imzalı provizyon yanıtı `ResponseCode=00` olduğunda `paid` yapılır. Tekrarlanan callback'ler veritabanı satır kilidi ve ödeme denemesi durumu ile ikinci kez provizyon oluşturmaz.

Test/canlı banka adresleri kod içinde moda göre sabittir; `.env` üzerinden değiştirilmez. Önce `APP_ENV=testing` ve `KUVEYT_TURK_MODE=test` ile banka sandbox hesabını doğrulayın. Canlıya geçerken yalnızca banka tarafından verilen canlı müşteri/işyeri/API kullanıcı bilgilerini girin, `APP_ENV=production`, HTTPS ve dışarıda tutulan `.env` kontrolünden sonra `KUVEYT_TURK_MODE=production` yapın. Sistem test modunu production uygulama ortamında, production ödeme modunu da test uygulama ortamında çalıştırmayı reddeder. Callback adresi `APP_URL` üzerinden otomatik olarak `${APP_URL}/api/payment-callback.php` olur; aynı adres istek içindeki `OkUrl` ve `FailUrl` alanlarına gönderilir.

Mevcut bir kurulumu güncelliyorsanız sırasıyla `api/migrations/20260830_security_hardening.sql` ve `api/migrations/20260830_kuveyt_turk_payments.sql` dosyalarını bir kez içe aktarın. Yeni kurulumlarda yalnızca güncel `api/database.sql` yeterlidir.

## Dosyalar

- `index.html`: ana mağaza
- `urun.html`: ürün galerisi ve beden seçimi
- `sepet.html`: tarayıcıda saklanan sepet
- `odeme.html`: iletişim, teslimat ve sipariş formu
- `siparis-takip.html`: numara + telefon/e-posta ile sınırlı sorgu
- `api/`: PDO prepared statements kullanan PHP API ve MySQL şeması
