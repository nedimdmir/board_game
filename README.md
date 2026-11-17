# 🎲 PartyCraft: Wonder Board (Parti Dünyası)

<p align="center">
  <img src="https://img.shields.io/badge/Language-Türkçe%20%7C%20English-blue.svg" alt="Languages">
  <img src="https://img.shields.io/badge/Architecture-Single--File%20(Zero%20NPM%20Client)-brightgreen.svg" alt="Zero Dependencies">
  <img src="https://img.shields.io/badge/Backend-PHP%20%7C%20Node.js%20%7C%20Offline%20Engine-orange.svg" alt="Backend Options">
  <img src="https://img.shields.io/badge/Platform-Web%20%7C%20Mobile%20%7C%20Desktop-purple.svg" alt="Platforms">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
</p>

<p align="center">
  <strong>Parti Dünyası</strong>, İstanbul, Tokyo, Paris ve New York sokaklarında arkadaşlarınızla veya akıllı botlarla oynayabileceğiniz, sevimli Lego tarzı görsel tasarıma, canlı harita animasyonlarına ve mini oyunlara sahip <strong>sıra tabanlı çok oyunculu masa oyunudur</strong>.
  <br>
  <em>A modern, zero-dependency turn-based multiplayer board game celebrating iconic world cities with procedural animations, tactical cards, minigames, and dual-language (TR/EN) support.</em>
</p>

---

## 🌐 Çoklu Dil Desteği / Multi-Language Support (TR / EN)

Oyun hem **Türkçe 🇹🇷** hem de **İngilizce 🇬🇧** dillerini yerel olarak destekler:
- Ana menünün en üstündeki **🇹🇷 Türkçe | 🇬🇧 English** hap butonlarına basarak veya **Ayarlar (⚙️)** penceresinden dilediğiniz an tek tıkla dil değiştirebilirsiniz.
- Sayfa yenilenmesine gerek kalmadan; tüm arayüz, karolar, HUD bildirimleri, çark metinleri, taktik eşyaları ve kurallar anında seçilen dile güncellenir.
- Dil tercihiniz tarayıcınızın `localStorage` belleğinde otomatik olarak saklanır.

*Seamlessly switch between Turkish and English in real-time with one click from the header or settings menu. Zero page reload required.*

---

## 🗺️ Canlı Haritalar ve Şehir Atmosferi (Living World Circuit)

Oyun parkuru geleneksel sert dikdörtgenler yerine, kıvrımlı ve organik **32 karoluk sahil ve meydan rotasından** oluşur:

```
                          [ 11: Portal ] --- [ 12: Altın ] --- [ 13: Şans ]
                         /                                                \
           [ 10: Düello ]                                                   [ 14: Dükkan ]
          /                                                                         \
   [ 9: Altın ]             🌊 BOĞAZİÇİ & MARMARA MANZARASI                          [ 15: Kaide ⭐ ]
       |                                                                                |
   [ 8: Portal ]            🗼 KIZ KULESİ (Dönen Fener Işığı)                          [ 16: Altın ]
       |                    🚃 NOSTALJİK KIRMIZI TRAMVAY                                |
   [ 7: Kaide ⭐ ]          💡 Işıklı Sokak Lambaları & Yıldızlı Gece                  [ 17: Karakol ]
       |                                                                                |
   [ 6: Altın ]                                                                    [ 18: Şans ]
          \                                                                         /
           [ 5: Dükkan ]                                                    [ 19: Altın ]
                         \                                                /
                          [ 0: BAŞLANGIÇ 🏁 ] --- [ 1: Kordon ] --- [ 2: İskele ]
```

### 🏙️ 4 Farklı Dünya Teması:
1. **İstanbul:** Kız Kulesi'nin dönen fener ışığı huzmesi, raylarında süzülen Nostaljik Taksim Tramvayı, sıcak sokak lambaları, hilal ay ve Boğaz martıları.
2. **Tokyo:** Neon ışıklar, Shinto Torii kapısı, süzülen Sakura kiraz yaprakları ve fütüristik gece gökyüzü.
3. **Paris:** Eyfel Kulesi silueti, Seine nehri köprüleri ve pastel Paris sokak kafeleri.
4. **New York:** Sarı taksiler, gökdelen siluetleri, Özgürlük Heykeli ve Broadway neon parıltıları.

---

## 🎡 Hassas Mekanik Şans Çarkı (Wheel of Fortune)

- **Taşmayan Vektörel İbre:** Çarkın üst kenarına kusursuz monte edilmiş, altın yataklı ve yakut uçlu özel SVG ibre. Çark döndükçe sesli mekanik tıkırtı efektiyle dilimlerin üzerinden atlar.
- **Dinamik Fiziksel Yavaşlama:** Kübik sönümleme (`cubic-ease-out`) fiziği ile gerçekçi bir dönüş deneyimi sunar.
- **Sürpriz Ödüller:** +15 Altın, 🎲 Çift Zar, 🛡️ Manyetik Kalkan, 🚀 Süper Roket, ⭐ Altın Yıldız ve Hazine Sandığı.

---

## 🚪 İstediğin Zaman Oyundan Ayrılabilme (Seamless Leave & Bot Takeover)

Oyunun akışını bozmadan istediğiniz an maçı terk edebilirsiniz:
- **Oyun İçi HUD Butonu:** Sağ üst köşedeki **"Odadan Ayrıl"** butonu ile onay penceresi açılır.
- **Yapay Zeka Devralması (Bot Handover):** Çok oyunculu bir maçtan ayrıldığınızda diğer arkadaşlarınızın oyunu bölünmez; yerinize anında bir yapay zeka botu geçerek maçı sürdürür.
- **Lobi Yönetimi:** Lobi aşamasında da tek tıkla odadan çıkabilir veya ana menüye dönebilirsiniz.

---

## 🚀 Kurulum ve Web Sitesine Yükleme (Zero Setup Deployment)

Bu oyun, harici hiçbir kütüphane (`npm`, `cdn`, `firebase`, harici veritabanı) gerektirmez.

```
📁 Web Sitenizin Kök veya Alt Klasörü (Örn: public_html/oyun/)
├── 📄 index.html      -> Tüm oyunun (HTML + CSS + JS) toplandığı tek bağımsız dosya (~396 KB)
├── 📄 api.php          -> Standart paylaşımlı PHP hostinglerde çok oyunculu oda sistemi (Opsiyonel)
```

### 1. Yöntem: Standart Web Hosting (cPanel / Apache / Nginx + PHP)
1. `index.html` ve `api.php` dosyalarını web sitenizin ana dizinine veya dilediğiniz bir klasöre (örn. `site.com/parti/`) yükleyin.
2. Tarayıcınızda adresi açın (`https://siteniz.com/parti/`).
3. Çok oyunculu oda kurma, QR kodla davet etme ve arkadaş bağlantıları **otomatik olarak aktifleşir**!

### 2. Yöntem: Statik Hosting (GitHub Pages / Netlify / Vercel)
1. Depodaki `index.html` dosyasını GitHub Pages veya Netlify'a yükleyin.
2. Oyun sunucusuz **Çevrimdışı Mod**'da 3 akıllı bot ile kusursuzca oynanabilir.

### 3. Yöntem: Yerel Node.js Sunucusu
```bash
# Bağımlılıkları yükleyin
npm install

# Sunucuyu başlatın (Varsayılan port: 3000)
npm start
```

---

## 🎮 Oyun Modları ve Kurallar

| Mod | Açıklama |
| :--- | :--- |
| **⚡ Hızlı Oyna** | Beklemeden hemen 3 akıllı yapay zeka botu ile İstanbul haritasında partiye başlar. |
| **🌐 Arkadaşlarınla Oyna** | Tek tıkla oda kurar, benzersiz oda kodu, davet bağlantısı (`?room=ABCD`) ve taranabilir QR kod üretir. |
| **🤖 Botlarla Oyna** | İstediğiniz haritayı (İstanbul, Tokyo, Paris, New York) ve tur sayısını (5, 10, 15) seçip pratik yapın. |

### 🎯 Oyunun Temel Amacı:
Belirlenen tur sayısı bitmeden önce en çok **Yıldız (⭐)** ve **Altın (🪙)** toplayan oyuncu partinin şampiyonu olur!
- **1 Altın Yıldız = 20 Altın** değerindedir ve liderlik sıralamasında en büyük önceliğe sahiptir.
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

---

## 🕹️ Ekran ve Cihaz Kontrolleri

- **Masaüstü (PC):**
  - **Zar Atma:** Butona tıklayın veya <kbd>SPACE</kbd> tuşuna basın.
  - **Çanta:** <kbd>I</kbd> tuşu ile envanteri açın.
  - **Kamera Gezintisi:** Mouse ile haritayı sürükleyin, tekerlek ile yakınlaştırın/uzaklaştırın.
- **Mobil ve Tablet:**
  - Parmakla dokunarak kaydırma ve çift parmakla harita yakınlaştırma.
  - Çift tıklamada istenmeyen tarayıcı zoomunu engelleyen dokunmatik optimizasyonu.
  - Başparmakla kolay erişilebilir büyük ergonomik kontrol rıhtımı.

---

<p align="center">
  Geliştirici: <strong>Nedim Demir</strong> • Açık Kaynak Topluluk Lisansı (MIT)
</p>
