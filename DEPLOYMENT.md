# 🌐 Parti Dünyası (Wonder Board) - Web Sitesine Yükleme & Dağıtım Rehberi

Bu rehber, **Parti Dünyası** oyununu kendi web sitene, sunucuna veya ücretsiz bulut platformlarına nasıl yükleyip hemen arkadaşlarınla ve tüm dünyayla oynayabileceğini adım adım anlatır.

---

## 1. 📁 Hangi Dosyaları Yükleyeceksin? (Dosya Listesi)

Sunucuna yüklemen gereken dosyalar şunlardır:

```text
📁 Proje Ana Dizini
├── 📄 server.js          (Arka plan Node.js & WebSocket sunucu motoru)
├── 📄 package.json       (Proje ayarları ve ws bağımlılığı)
└── 📁 public/            (Ön yüz web dosyaları)
    ├── 📄 index.html     (Oyunun ana HTML sayfası)
    ├── 📁 css/
    │   ├── style.css     (Tüm arayüz, pencereler, animasyonlar ve responsive tasarım)
    │   └── board.css     (Masa oyunu tahtası stilleri)
    ├── 📁 js/
    │   ├── ui.js         (Arayüz yönetimi, ses ve bildirim tetikleyicileri)
    │   ├── board-map.js  (3D platform karoları, şehir haritaları ve zıplama motoru)
    │   ├── minigames.js  (5 adet çok oyunculu mini oyun)
    │   ├── game-client.js(Sunucu ile WebSocket iletişimi)
    │   ├── audio.js      (Web Audio API ile sıfır telifli gerçek zamanlı ses sentezleyici)
    │   ├── progression.js(Seviye, XP, unvanlar, avatarlar ve gardırop)
    │   └── icons.js      (Vektörel ikon kütüphanesi)
    └── 📁 assets/        (Varsa statik görsel/ikon kaynakları)
```

> [!WARNING]
> ### ⚠️ NELERİ YÜKLEMEMELİSİN?
> - **`node_modules/` klasörünü ASLA FTP veya Git ile yükleme!** Bu klasör yüz binlerce küçük dosyadan oluşur ve yükleme saatler sürer. Sunucunda tek bir komutla (`npm install`) birkaç saniyede otomatik kurulur.
> - `.git/` veya geçici test dosyalarını yüklemene gerek yoktur.

---

## 2. ⚡ En Kolay ve Ücretsiz Yayınlama Yolları (1-Tıkla Canlıya Al)

Eğer kendi sunucun yoksa, bu oyunu **tamamen ücretsiz** olarak 2-3 dakikada internete açabilirsin:

### Yöntem A: Render.com (Önerilen - Ücretsiz SSL & WebSocket Desteği)
1. Dosyalarını ücretsiz bir [GitHub](https://github.com) deposuna yükle (`git push`).
2. [Render.com](https://render.com)'a ücretsiz üye ol.
3. **New +** -> **Web Service** seçeneğine tıkla ve GitHub deponu bağla.
4. Ayarları şöyle yap:
   - **Environment:** `Node`
   - **Build Command:** `npm install`
   - **Start Command:** `node server.js`
   - **Plan:** `Free` (Ücretsiz)
5. **Create Web Service** butonuna bas. 1-2 dakika içinde sana özel `https://parti-dunyasi.onrender.com` gibi güvenli bir link verir. Bu linki arkadaşlarına atıp hemen oynayabilirsin!

### Yöntem B: Railway.app veya Glitch.com
- **Railway.app:** GitHub deponu bağla, otomatik algılar ve anında deploy eder.
- **Glitch.com:** "New Project" -> "Import from GitHub" diyerek anında tarayıcı içinde canlı sunucu elde edebilirsin.

---

## 3. 🖥️ Kendi Linux / VPS Sunucunda Yayınlama (Ubuntu / Debian / CentOS)

Eğer DigitalOcean, Hetzner, Vultr, AWS veya Türk hosting firmasından aldığın bir VPS/VDS varsa:

### Adım 1: Node.js Kurulumu
SSH ile sunucuna bağlan ve Node.js 18+ kur:
```bash
# Ubuntu / Debian için:
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs
```

### Adım 2: Dosyaları Sunucuya Yükleme
FileZilla (SFTP) veya SCP ile yukarıdaki dosyaları (`server.js`, `package.json`, `public/`) sunucundaki bir klasöre yükle (Örneğin: `/var/www/parti-oyunu`).

### Adım 3: Bağımlılıkları Kur ve PM2 ile Arka Planda Başlat
```bash
cd /var/www/parti-oyunu
npm install --production

# Sunucu kapansa bile oyunun 7/24 kesintisiz çalışması için PM2 kur:
sudo npm install -g pm2
pm2 start server.js --name "parti-oyunu"
pm2 save
pm2 startup
```

### Adım 4: Nginx Reverse Proxy (Domain ve SSL için)
Domainini (örneğin `oyun.siten.com`) oyuna bağlamak için Nginx konfigürasyonuna WebSocket desteğini ekle:

```nginx
server {
    server_name oyun.siten.com;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        
        # WebSocket Bağlantı Başlıkları (Çok Önemli!)
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```
Ardından ücretsiz SSL almak için:
```bash
sudo certbot --nginx -d oyun.siten.com
```

---

## 4. 🗄️ cPanel / Plesk Hostingde Yayınlama

cPanel paketinde "Setup Node.js App" özelliği varsa:
1. cPanel'e gir ve **Setup Node.js App** simgesine tıkla.
2. **Create Application** butonuna bas.
3. **Application root:** `parti-oyunu`
4. **Application startup file:** `server.js`
5. Dosyalarını cPanel Dosya Yöneticisi ile `parti-oyunu` klasörüne yükle.
6. Sayfadaki **Run NPM Install** butonuna bas.
7. **Restart** butonuna tıkla. Oyunun subdomaininde çalışmaya başlayacaktır!

---

## 5. 🧩 Mevcut Bir Sitenin (WordPress, Blogger vb.) İçine Gömme

Eğer oyunu yukarıdaki adımlardan biriyle yayınladıysan (örn: `https://oyun.siten.com`), mevcut sitendeki herhangi bir yazıya veya sayfaya şu kodla gömebilirsin:

```html
<iframe 
  src="https://oyun.siten.com" 
  width="100%" 
  height="850px" 
  style="border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);" 
  allow="autoplay; fullscreen"
  allowfullscreen>
</iframe>
```

---

## 6. 🏠 Evde / Arkadaş Ortamında Wi-Fi Üzerinden Oynama (Yerel Ağ / LAN)

İnternete yüklemeden önce evdeki arkadaşlarınla aynı Wi-Fi üzerinden test etmek istersen:
1. Bilgisayarında terminali aç ve projeyi çalıştır:
   ```bash
   node server.js
   ```
2. Terminalde çıkan yerel ağ adresini görürsün (Örn: `http://192.168.1.35:3000`).
3. Arkadaşların telefonlarından bu adrese girer veya oyundaki **"📱 Mobilden Katıl"** butonuna basıp ekrandaki QR kodu telefonlarına okuturlar.
4. Bilgisayar ve telefonlar aynı Wi-Fi'da olduğu sürece sıfır gecikmeyle birlikte oynayabilirsiniz!
