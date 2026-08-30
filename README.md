# GÖKTÜRK ULUSAL BİRLİĞİ Resmî Mağaza

Build işlemi gerektirmeyen HTML5, CSS3, Bootstrap 5, jQuery ve PHP/MySQL mağaza projesidir. Dosyalar cPanel `public_html` dizinine FTP ile yüklenerek çalıştırılabilir.

## cPanel kurulumu

1. Projedeki dosya ve klasörleri `public_html` içine yükleyin.
2. cPanel > MySQL Databases bölümünden veritabanı ve sınırlı yetkili bir kullanıcı oluşturun.
3. phpMyAdmin üzerinden `api/database.sql` dosyasını içe aktarın.
4. `api/config.example.php` dosyasını `api/config.php` adıyla kopyalayıp yalnızca veritabanı bilgilerini girin.
5. `api/config.php` dosya iznini mümkünse `600`, diğer PHP dosyalarını `644` yapın.
6. Alan adında geçerli SSL sertifikasını etkinleştirin ve PHP 8.1 veya üstünü seçin.
7. `teslimat.html`, `iletisim.html` ve hukuki sayfalardaki açık bilgi alanlarını yetkili/hukuk danışmanı onayıyla doldurun.
8. Yönetim girişi için `api/config.php` içindeki `admin.email` değerini ve PHP `password_hash()` ile üretilmiş `admin.password_hash` değerini ayarlayın. Yönetim adresi `/admin/login.php` olur.

`api/config.php` kaynak kod deposuna alınmaz ve web üzerinden `.htaccess` ile engellenir. Sipariş fiyatı tarayıcıdan kabul edilmez; sunucuda 499 TL üzerinden yeniden hesaplanır. Kart verisi bu projede alınmaz veya saklanmaz.

## Ödeme entegrasyonu

Sipariş kaydı `api/order-create.php` içinde gerçek MySQL işlemiyle oluşturulur. Ödeme kuruluşu bağlanacağı zaman sipariş kaydından sonra sağlayıcının sunucu tarafı başlatma adresine yönlendirme eklenmelidir. Gizli anahtarlar yalnızca `api/config.php` içinde tutulmalı, JavaScript'e yazılmamalıdır. Sağlayıcının imza doğrulaması yapılmadan `payment_status` değeri `paid` yapılmamalıdır.

## Dosyalar

- `index.html`: ana mağaza
- `urun.html`: ürün galerisi ve beden seçimi
- `sepet.html`: tarayıcıda saklanan sepet
- `odeme.html`: iletişim, teslimat ve sipariş formu
- `siparis-takip.html`: numara + telefon/e-posta ile sınırlı sorgu
- `api/`: PDO prepared statements kullanan PHP API ve MySQL şeması
