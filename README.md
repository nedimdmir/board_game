# 🎲 PartyCraft: Wonder Board (Parti Adası)
### Lego & Mario Party Tarzı Çok Oyunculu Masa ve Parti Oyunu

**PartyCraft: Wonder Board**, arkadaşlarınızla aynı yerel ağdan (Wi-Fi / LAN) veya internet üzerinden web tarayıcınızda (PC ve Mobil) oynayabileceğiniz, sevimli görsellere ve akıllı bot desteğine sahip sıra tabanlı bir masa oyunudur.

---

## 🌟 Öne Çıkan Özellikler

1. **Aynı Ağdan ve İnternetten Kesintisiz Çok Oyunculu:**
   - Aynı Wi-Fi ağındaki arkadaşlarınız ekranınızdaki **QR Kodu** telefon kameralarıyla taratarak tek tıkla odaya katılabilir.
   - Bilgisayar ve telefonlar arasında tam uyumlu (çapraz platform) çalışır.
   - Herhangi bir sunucuya (Render, Railway, Fly.io, VPS) yüklendiğinde dünya genelindeki oyuncularla anında oynanabilir.

2. **Sıra Tabanlı 3D Lego Tarzı Harita & Karolar:**
   - **32 Özel Karo:** Altın karoları, Tuzak çukurları, Sürpriz Şans Çarkı (Rulet), Eşya Dükkanı, Hava Geçidi (Portal), Düello Meydanı ve Muz Kabuğu tuzakları.
   - **Dinamik Altın Yıldız (Kupa) Kaidesi:** 20 Altın toplayan oyuncu Altın Yıldız'ı satın alır; Yıldız satın alındıkça haritada yeni bir konuma ışınlanır!
   - **3D Dönen Zar Animasyonu:** Ses efektli, takla atan zar fiziği.

3. **Akıllı Botlar & Kesintisiz Devamlılık (Reconnect):**
   - Arkadaşlarınız yokken tek başınıza istediğiniz sayıda (1-3) akıllı bot ile hemen oynayabilirsiniz.
   - Çok oyunculu maç esnasında bir oyuncunun bağlantısı koparsa veya odadan çıkarsa, **oyun durmaz!** Yapay zeka bot hemen o oyuncunun yerine geçer ve oyunu sürdürür.
   - Oyuncu sayfayı yenilediğinde veya tekrar bağlandığında, kaldığı yerden kendi karakterini ve altınlarını geri devralır.

4. **Seviye (Level) & İlerleme Sistemi:**
   - Her maçtan, toplanan yıldızlardan ve kazanılan mini-oyunlardan **XP** kazanırsınız.
   - **Açılabilir Karakterler:** Minifig Bob (Lv. 1), Şapkalı Dino (Lv. 2), Siber Neko (Lv. 4), Kozmik Kaşif (Lv. 7), Büyücü Merlin (Lv. 10), Siber Ninja (Lv. 14), Parti Pandası (Lv. 18), Altın Lego Kralı (Lv. 25).
   - **Açılabilir Zar Temaları:** Klasik Beyaz, Zümrüt Yeşili, Alev Kırmızısı, Siber Neon, Kozmik Galaksi, Saf Altın Zar.
   - **Unvanlar:** "Acemi Gezgin", "Şanslı Tilki", "Zar Ustası", "Tuzak Bükücü", "Yıldız Avcısı", "Parti Efsanesi".
   - **Canlı İfade Baloncukları (Emotes):** 😂, 😎, 😱, 🥳, 🔥, 💀, 🚀, 👑 sesli tepkiler.

5. **Tur Sonu Heyecan Dolu Mini-Oyunlar:**
   - **Altın Yağmuru (Coin Catch):** Sepetinizi sağa-sola kaydırarak gökten yağan altın ve elmasları toplayın, bombalardan kaçın!
   - **Hızlı Parmak (Reflex Duel):** Yeşil ışık yandığında en hızlı tepki veren oyuncu ekstra puanı kapar.
   - **Lego Balon Şişirme (Balloon Mash):** Butona en hızlı basan oyuncu balonunu ilk patlatır!

6. **Taktiksel Eşyalar (Çanta):**
   - 🎲 **Çift Zar:** Aynı anda 2 zar atar (2-12 adım).
   - 🚀 **Süper Roket:** Hedefe doğru direkt 7 adım uçar.
   - 🛡️ **Manyetik Kalkan:** Tuzaklardan ve altın hırsızlığından 2 tur korur.
   - 🧲 **Altın Mıknatısı:** Rakiplerden altın çeker.
   - 🍌 **Muz Kabuğu:** Karoya tuzak bırakır, basan 3 adım geri kayar.
   - 🔄 **Takas Asası:** Seçtiğiniz bir rakiple konumunuzu takas eder.

7. **Süre & Tur Seçenekleri:**
   - ⚡ **Hızlı Parti:** 5 Tur (~5 dakika)
   - 🏆 **Standart Parti:** 10 Tur (~10-12 dakika)
   - 🔥 **Destansı Parti:** 15 Tur (~18 dakika)

8. **Telifsiz & Gecikmesiz Web Audio API Ses Motoru:**
   - Dışarıdan MP3 veya telifli dosya indirmeye gerek duymadan, tarayıcının kendi Web Audio sentezleyicisi ile zar tıkırtısı, altın şıngırtısı, kupa fanfarı ve neşeli arka plan müziği üretilir.

---

## 🚀 Nasıl Çalıştırılır?

### 1. Bağımlılıkları Yükleyin & Başlatın
```bash
npm install
npm start
```

### 2. Tarayıcıdan Giriş Yapın
- **Kendi Bilgisayarınızdan:** `http://localhost:3000`
- **Aynı Wi-Fi Ağındaki Telefon veya Başka PC'den:** Terminalde gösterilen yerel IP adresini girin:
  `http://192.168.235.20:3000`
  *(Ya da lobi ekranındaki QR kodu telefonunuzun kamerasıyla taratın!)*

---

## 📱 Mobil Uyumluluk & Kontroller
- **PC Kontrolleri:** Mouse tıklaması, `Space` tuşu ile zar atma, `E` tuşu ile ifade gönderme, `I` tuşu ile çanta açma.
- **Mobil Kontroller:** Dokunmatik alt kontrol çubuğu, tek parmakla haritayı kaydırma / yakınlaştırma, büyük dokunmatik zar atma butonu.
