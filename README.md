# 🎲 PartyCraft: Wonder Board (Parti Dünyası)

<p align="center">
  <img src="https://img.shields.io/badge/Diller%20%2F%20Languages-🇹🇷%20TR%20%7C%20🇬🇧%20EN%20%7C%20🇩🇪%20DE%20%7C%20🇪🇸%20ES%20%7C%20🇫🇷%20FR-blue.svg" alt="Languages">
  <img src="https://img.shields.io/badge/Dağıtım-Tek%20Dosya%20%28Single--File%20index.php%20%2F%20index.html%29-brightgreen.svg" alt="Single File">
  <img src="https://img.shields.io/badge/Bağımlılıklar-Sıfır%20NPM%20%2F%20Sıfır%20Harici%20Kütüphane-success.svg" alt="Zero Dependencies">
  <img src="https://img.shields.io/badge/Platform-Mobil%20%7C%20Tablet%20%7C%20Masaüstü-purple.svg" alt="Platforms">
  <img src="https://img.shields.io/badge/Lisans-MIT-green.svg" alt="License">
</p>

<p align="center">
  <strong>Parti Dünyası</strong>; İstanbul, Tokyo, Paris ve New York sokaklarında arkadaşlarınızla veya akıllı yapay zeka botlarıyla oynayabileceğiniz; sevimli Lego tarzı görsel tasarımı, canlı harita animasyonları, taktik eşyaları ve 6 adet zengin mini oyunu olan <strong>sıra tabanlı çok oyunculu masa oyunudur</strong>.
  <br><br>
  <em>A modern, zero-dependency turn-based multiplayer board game celebrating iconic world cities with procedural animations, tactical cards, 6 mini-games, and 5-language international support (TR, EN, DE, ES, FR).</em>
</p>

---

## 🌟 Öne Çıkan Özellikler / Key Highlights

- ⚡ **Tek Dosya ile Anında Kurulum (Ultra Zero-Setup):**
  - PHP sunucular için tek dosya: **`index.php`** (Çok oyunculu arka uç + tam oyun arayüzü tek dosyada!).
  - Statik / GitHub Pages / Çevrimdışı kullanım için tek dosya: **`index.html`** (Sıfır bağımlılık).
- 🌐 **5 Farklı Dil Desteği (Multi-Language):**
  - 🇹🇷 Türkçe, 🇬🇧 English, 🇩🇪 Deutsch, 🇪🇸 Español, 🇫🇷 Français.
  - Sayfa yenilemeden tek tıkla dil değişimi, kalıcı `localStorage` kaydı.
- 🎮 **6 Farklı Mini Oyun:**
  1. 🪙 **Altın Toplama Sepeti (Coin Catcher):** Sağa-sola hareketli sepetle gökten düşen altınları topla (mobil çift tıklama zoom korumalı).
  2. 🎯 **Refleks Hedefleri (Reflex Pop):** Ekranda aniden beliren hedefleri kaçırmadan en hızlı sürede vur.
  3. 🎈 **Balon Patlatma (Balloon Mash):** Süre dolmadan ekrandaki balona hızlıca tıklayarak şişir ve patlat.
  4. 🎁 **Şans Sandıkları (Lucky Chests):** 3 gizemli sandıktan en büyük ödülü saklayanı tahmin et.
  5. 🧠 **Hafıza Matrisi (Memory Matrix):** Yanıp sönen renkli karo kombinasyonunu aklında tut ve doğru sırayla dokun.
  6. 🔨 **Güç Çekici & Hassas Zamanlama (Timing Hammer - YENİ!):** Hızlıca salınan güç barını tam merkezdeki %100 hedefte durdur, çanı çaldır!
- 🎒 **Genişletilmiş Taktik Çantası:**
  - 🎲 **Çift Zar**, 🚀 **Süper Roket**, 🛡️ **Manyetik Kalkan**, 🧲 **Yıldız Mıknatısı**, 🔄 **Konum Takası**, 👻 **Hayalet Pelerin (YENİ!)**, 🍀 **Şanslı Altın Zar (YENİ!)**.
- 🗺️ **Canlı Şehir Haritaları (Living World Circuit):**
  - İstanbul (Kız Kulesi dönen feneri, Taksim Nostaljik Tramvayı, Boğaz martıları), Tokyo (Neon gökdelenler, Sakura yaprakları), Paris (Eyfel Kulesi ve Seine nehri), New York (Gökdelenler ve sarı taksiler).
- 🎡 **Hassas Mekanik Şans Çarkı:**
  - Taşmayan altın yataklı SVG ibre, kübik yavaşlama fiziği ve mekanik ses efektleri.
- 🚪 **Kesintisiz Oyundan Ayrılma & Bot Devralması:**
  - Oyuncu dilediği an maçı terk edebilir; yapay zeka botu oyun bölünmeden yerini devralır.

---

## 🌐 Çoklu Dil Desteği / Multi-Language Support

| Dil / Language | Kod | Durum |
| :--- | :---: | :---: |
| 🇹🇷 Türkçe | `tr` | ✅ Tam Destek (Varsayılan) |
| 🇬🇧 English | `en` | ✅ Full Support |
| 🇩🇪 Deutsch | `de` | ✅ Vollständige Unterstützung |
| 🇪🇸 Español | `es` | ✅ Soporte Completo |
| 🇫🇷 Français | `fr` | ✅ Support Complet |

> Üst menü çubuğundaki dil butonlarına basarak veya **Ayarlar (⚙️)** penceresinden dilediğiniz an tek tıkla dil değiştirebilirsiniz. Tüm metinler, kurallar, HUD göstergeleri, karo açıklamaları ve çanta eşyaları anında güncellenir.

---

## 🚀 Web Sitenize Nasıl Yüklersiniz? (Tek Dosya Kurulumu)

Kurulum için karmaşık sunucu komutlarına veya veritabanı ayarlarına **kesinlikle gerek yoktur**.

```
📁 Web Sitenizin Klasörü (Örn: public_html/ veya public_html/oyun/)
└── 📄 index.php   <-- SADECE BU TEK DOSYAYI YÜKLEMENİZ YETERLİDİR!
```

### 1. Seçenek: PHP Web Hosting (cPanel / Plesk / DirectAdmin / Paylaşımlı Hosting)
1. Bu depodaki veya ZIP arşivindeki **`index.php`** dosyasını sitenizin ana dizinine (veya `site.com/oyun/` klasörüne) yükleyin.
2. Tarayıcınızda sitenizi açın (`https://siteniz.com/` veya `https://siteniz.com/oyun/`).
3. **Bitti!**
   - Ziyaretçiler siteye girdiğinde tam oyun arayüzü açılır.
   - Oda oluşturulduğunda sistem otomatik olarak oda kodu, davet bağlantısı ve QR kod üretir.
   - Tüm çok oyunculu eşzamanlama `index.php` içerisindeki hafif JSON dosya kilitli arka uç tarafından otomatik yönetilir.

### 2. Seçenek: Statik Hosting (GitHub Pages / Netlify / Vercel / Cloudflare Pages)
1. **`index.html`** dosyasını deponuza veya barındırma alanınıza yükleyin.
2. Oyun sunucuya ihtiyaç duymadan **Bağımsız Çevrimdışı & Bot Modu**'nda çalışır.

### 3. Seçenek: Bilgisayarınızda Çift Tıklayarak Oynama
- `index.html` dosyasına bilgisayarınızda çift tıklayarak herhangi bir internet bağlantısı olmadan hemen botlarla oynayabilirsiniz.

### 4. Seçenek: Node.js Sunucusu (Geliştiriciler İçin)
```bash
# Bağımlılıkları yükleyin
npm install

# Sunucuyu başlatın (Port: 3000)
npm start
```

---

## 🎮 Oyun Modları

| Mod | Açıklama |
| :--- | :--- |
| **⚡ Hızlı Oyna** | Hiç beklemeden anında 3 akıllı yapay zeka botu ile seçili haritada maça başlar. |
| **🌐 Arkadaşlarınla Oyna** | Tek tıkla oda kurar; benzersiz oda kodu, WhatsApp/Discord davet bağlantısı (`?room=ABCD42`) ve taranabilir QR kod üretir. |
| **🤖 Botlarla Oyna** | Harita (İstanbul, Tokyo, Paris, New York) ve tur sayısını (5, 10, 15) seçip pratik yapın. |

### 🎯 Temel Amaç ve Kurallar:
- Belirlenen tur sayısı tamamlanana kadar en çok **Yıldız (⭐)** ve **Altın (🪙)** toplayan oyuncu şampiyon olur!
- **1 Altın Yıldız = 20 Altın** değerindedir ve sıralamada en büyük önceliğe sahiptir.
- Başlangıç karosundan her geçişte **+10 Altın** tur primi kazanırsınız.
- **Yıldız Kaidesi:** Haritada belirli aralıklarla yer alır. 20 Altını olan oyuncu Yıldız'ı satın alır; satın alınan Yıldız haritanın başka bir köşesine ışınlanır!

---

## 🎒 Taktik Eşyaları (Çanta)

| Eşya | İkon | Etki |
| :--- | :---: | :--- |
| **Çift Zar** | 🎲 | Sonraki turda 2 zar birden atarsınız (2 - 12 adım). |
| **Süper Roket** | 🚀 | Hedefe doğru direkt olarak 7 adım ileri fırlar. |
| **Manyetik Kalkan** | 🛡️ | Tuzaklara ve altın çalma hamlelerine karşı 2 tur tam koruma sağlar. |
| **Yıldız Mıknatısı** | 🧲 | Yıldız kaidesini haritada 3 adım kendinize doğru çeker. |
| **Takas Işınlanması** | 🔄 | Seçilen bir rakip ile haritadaki konumlarınızı takas eder. |
| **Hayalet Pelerin** *(Yeni!)* | 👻 | 2 tur boyunca tüm karo tuzaklarından ve cezalardan muaf olursunuz. |
| **Şanslı Altın Zar** *(Yeni!)* | 🍀 | Sonraki zarınız kesinlikle 6 (çift zarda 12) gelir! |

---

## 🕹️ Ekran ve Cihaz Kontrolleri

- **Masaüstü (PC / Mac):**
  - **Zar Atma:** Butona tıklayın veya <kbd>SPACE</kbd> tuşuna basın.
  - **Çanta:** <kbd>I</kbd> tuşu ile envanteri açın.
  - **Kamera Gezintisi:** Mouse ile haritayı sürükleyin, tekerlek ile yakınlaştırıp uzaklaştırın.
- **Mobil ve Tablet (iOS / Android):**
  - Parmakla dokunarak kaydırma ve çift parmakla harita yakınlaştırma.
  - Çift tıklamada tarayıcı yakınlaştırmasını önleyen optimize dokunmatik motoru.
  - Başparmakla kolay erişilebilir ergonomik alt kontrol paneli.

---

## 📜 Lisans & Geliştirici

- **Geliştirici:** Nedim Demir
- **Lisans:** [MIT Lisansı](LICENSE) - Açık kaynak, dilediğiniz gibi geliştirebilir ve kendi web sitelerinizde yayınlayabilirsiniz.
