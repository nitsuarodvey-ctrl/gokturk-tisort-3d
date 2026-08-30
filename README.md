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

`.env` kaynak kod deposuna alınmaz ve web kökünün dışında tutulur. Üretimde `APP_URL` mutlaka `https://` ile başlamalı ve `SESSION_SECURE=true` olmalıdır. Sipariş fiyatı tarayıcıdan kabul edilmez; sunucuda 499 TL üzerinden yeniden hesaplanır. Kart verisi bu projede alınmaz veya saklanmaz.

## Ödeme entegrasyonu

Sipariş kaydı `api/order-create.php` içinde gerçek MySQL işlemiyle oluşturulur. Ödeme kuruluşu bağlanacağı zaman sipariş kaydından sonra sağlayıcının sunucu tarafı başlatma adresine yönlendirme eklenmelidir. Gizli anahtarlar yalnızca `.env` içinde tutulmalı, JavaScript'e yazılmamalıdır. Sağlayıcının imza doğrulaması yapılmadan `payment_status` değeri `paid` yapılmamalıdır.

Mevcut bir kurulumu güncelliyorsanız `api/migrations/20260830_security_hardening.sql` dosyasını bir kez içe aktarın. Yeni kurulumlarda yalnızca güncel `api/database.sql` yeterlidir.

## Dosyalar

- `index.html`: ana mağaza
- `urun.html`: ürün galerisi ve beden seçimi
- `sepet.html`: tarayıcıda saklanan sepet
- `odeme.html`: iletişim, teslimat ve sipariş formu
- `siparis-takip.html`: numara + telefon/e-posta ile sınırlı sorgu
- `api/`: PDO prepared statements kullanan PHP API ve MySQL şeması
