# 🌐 Parti Dünyası (Wonder Board) - Web Sitesine Yükleme & Dağıtım Rehberi

Bu rehber, **Parti Dünyası (PartyCraft: Wonder Board)** oyununu kişisel web sitenize, hostinginize veya bulut sunucunuza **mümkün olan en az dosya ile (tek bir dosya!)** nasıl yükleyip hemen oynayabileceğinizi anlatır.

---

## ⚡ HANGİ DOSYAYI YÜKLEYECEKSİNİZ? (ÖZET SEÇENEKLER)

İhtiyacınıza göre aşağıdaki dosyalardan **yalnızca birini** sitenize yüklemeniz yeterlidir:

| Barındırma Türü | Yüklenecek Dosya | Açıklama |
| :--- | :---: | :--- |
| **PHP Web Hosting** *(cPanel, Plesk, DirectAdmin, Apache, Nginx, Turhost, Natro, Hostinger vb.)* | **`index.php`** | **ÖNERİLEN (TEK DOSYA):** Çok oyunculu oda sistemi, QR kod daveti, arkadaş bağlantıları ve oyunun tüm görsel arayüzü tek dosyada toplanmıştır. Başka hiçbir dosya veya veritabanı gerekmez! |
| **Statik Web Hosting** *(GitHub Pages, Netlify, Vercel, Cloudflare Pages)* | **`index.html`** | **TEK DOSYA:** Sıfır sunucu ile çalışır. Akıllı yapay zeka botları ve tam oyun motoru tarayıcı belleğinde çalışır. |
| **Geleneksel Ayrık Mod** *(HTML + PHP ayrı dosyalarda)* | **`index.html`** + **`api.php`** | Ön yüz ve arka uç dosyalarını ayrı tutmak isteyenler için standart kurulum. |
| **Node.js / VPS Sunucu** *(DigitalOcean, Hetzner, AWS, Render)* | **`server.js`** + **`public/`** | Gerçek zamanlı WebSocket tabanlı Node.js sunucusu. |

---

## 1. 🚀 EN KOLAY YÖNTEM: SADECE `index.php` YÜKLEMEK (1 Dakikada Canlıda!)

Eğer standart bir web hostinginiz (cPanel, Plesk vb.) varsa:

1. Bu depodan veya ZIP arşivinden **`index.php`** dosyasını bilgisayarınıza alın.
2. Web hostinginizin Dosya Yöneticisi (File Manager) veya FileZilla (FTP) programı ile:
   - Sitenizin ana dizinine (`public_html/`) **veya**
   - Bir alt klasöre (örneğin `public_html/parti/` veya `public_html/oyun/`) yükleyin.
3. Tarayıcınızdan adresinizi açın:
   - `https://siteniz.com/` veya `https://siteniz.com/parti/`
4. **Tebrikler, oyununuz hazır!**
   - Kullanıcılar bağlandığında oyun arayüzü doğrudan açılır.
   - Çok oyunculu oda açıldığında `index.php` otomatik olarak oda verilerini yönetir.
   - Veritabanı (MySQL), şifre, port açma veya ek kütüphane ayarlarına **gerek yoktur**.

---

## 2. 🌐 GITHUB PAGES / NETLIFY İLE ÜCRETSİZ YAYINLAMA (Statik Mod)

Eğer PHP hostinginiz yoksa ve tamamen ücretsiz yayınlamak istiyorsanız:

1. GitHub'da yeni bir depo açın.
2. Deponuza yalnızca **`index.html`** dosyasını yükleyin.
3. **Settings -> Pages** sekmesine girip `main` dalını seçin ve kaydedin.
4. Birkaç saniye içinde `https://kullaniciadiniz.github.io/deponuz/` adresinde oyununuz yayına girer.
5. Ziyaretçiler 3 akıllı yapay zeka botu ile harika bir parti deneyimi yaşayabilir.

---

## 3. 🧩 MEVCUT BİR SİTEYE (WordPress, Blog, Portfolyo) GÖMME

Eğer oyunu web sitenizdeki bir sayfanın veya blog yazısının içine gömmek isterseniz:

```html
<iframe 
  src="https://siteniz.com/oyun/index.php" 
  width="100%" 
  height="850px" 
  style="border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);" 
  allow="autoplay; fullscreen"
  allowfullscreen>
</iframe>
```

---

## 4. 💻 YEREL TEST (Kendi Bilgisayarınızda Deneme)

- **İnternetsiz / Doğrudan Açma:** `index.html` dosyasına çift tıklayarak Chrome, Edge, Safari veya Firefox tarayıcınızda hemen oynayabilirsiniz.
- **Node.js ile Yerel Ağ Partisi:**
  ```bash
  npm install
  npm start
  ```
  Terminalde görünen yerel ağ IP'si (örneğin `http://192.168.1.50:3000`) üzerinden aynı Wi-Fi'a bağlı telefon ve bilgisayarlar birlikte oynayabilir.
