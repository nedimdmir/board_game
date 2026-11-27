const express = require('express');
const http = require('http');
const path = require('path');
const os = require('os');
const { WebSocketServer, WebSocket } = require('ws');
const QRCode = require('qrcode');
const crypto = require('crypto');

const app = express();
const server = http.createServer(app);
const wss = new WebSocketServer({ server, maxPayload: 16 * 1024 });

const PORT = process.env.PORT || 3000;

// Serve static assets from public
app.use(express.static(path.join(__dirname, 'public')));
app.use(express.json());

// Helper: Get local network IP addresses
function getLocalIpAddresses() {
  const interfaces = os.networkInterfaces();
  const addresses = [];
  for (const name of Object.keys(interfaces)) {
    for (const iface of interfaces[name]) {
      if (iface.family === 'IPv4' && !iface.internal) {
        addresses.push(iface.address);
      }
    }
  }
  return addresses;
}

// Endpoint: QR code generator
app.get('/api/qr', async (req, res) => {
  const text = String(req.query.url || `http://localhost:${PORT}`).slice(0, 500);
  try {
    const qrSvg = await QRCode.toString(text, { type: 'svg', margin: 1, width: 220 });
    res.type('image/svg+xml').send(qrSvg);
  } catch (err) {
    res.status(500).send('QR Generation Error');
  }
});

// Endpoint: Server info (LAN IP, port)
app.get('/api/info', (req, res) => {
  const ips = getLocalIpAddresses();
  res.json({
    port: PORT,
    lanIps: ips,
    primaryLanUrl: ips.length > 0 ? `http://${ips[0]}:${PORT}` : `http://localhost:${PORT}`
  });
});

/* ==========================================================================
   GAME ENGINE & STATE MANAGEMENT
   ========================================================================== */

const ROOMS = new Map(); // roomId -> RoomState

// Multi-City World Maps Generator
function generateBoardForTheme(theme = 'istanbul') {
  // Aliases for backward compatibility
  if (theme === 'cyber') theme = 'tokyo';
  if (theme === 'candy') theme = 'paris';
  if (theme === 'tropics') theme = 'istanbul';

  if (theme === 'tokyo') {
    return [
      { id: 0, type: 'start', title: 'Shibuya Meydanı', desc: '+10 Altın Başlangıç Bonusu!', icon: 'flag', color: '#10B981' },
      { id: 1, type: 'coin', amount: 4, title: 'Akihabara Tech', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 2, type: 'mystery', title: 'Gacha Kapsülü', desc: 'Gizemli anime sürprizi!', icon: 'sparkles', color: '#EC4899' },
      { id: 3, type: 'coin', amount: 5, title: 'Shinjuku Neon', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 4, type: 'freeze', title: 'EMP Siber Şok', desc: 'Sistem dondu! 1 tur eşya kullanamazsın!', icon: 'zap', color: '#0284C7' },
      { id: 5, type: 'shop', title: 'Harajuku Market', desc: 'Fütüristik eşyalar satın al!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 6, type: 'coin', amount: 6, title: 'Ginza Lüksü', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 7, type: 'star', title: 'Tokyo Skytree', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 8, type: 'warp', target: 20, title: 'Shinkansen Hızlı Tren', desc: '20. karoya ışınlan!', icon: 'zap', color: '#06B6D4' },
      { id: 9, type: 'coin', amount: 4, title: 'Roppongi Gece', desc: '+4 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 10, type: 'jail', title: 'Siber Kodes', desc: '1 tur kilitli kal veya 5 altın kefalet öde!', icon: 'shield', color: '#DC2626' },
      { id: 11, type: 'trap', penalty: 5, title: 'Neon Aşırı Yükleme', desc: '-5 Altın hasarı!', icon: 'flame', color: '#EF4444' },
      { id: 12, type: 'mystery', title: 'Şans Kedisi Maneki', desc: 'Şans ruletini çevir!', icon: 'sparkles', color: '#EC4899' },
      { id: 13, type: 'bank', title: 'Tokyo Merkez Bankası', desc: 'Biriken havuz altınlarını tek seferde topla!', icon: 'coins', color: '#10B981' },
      { id: 14, type: 'shop', title: 'Akiba Dükkanı', desc: 'Siber eşyalar!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 15, type: 'star', title: 'Tokyo Kulesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 16, type: 'coin', amount: 4, title: 'Ueno Parkı', desc: '+4 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 17, type: 'slip', steps: -3, title: 'Islak Ramen Zemini', desc: '3 adım geri kaydın!', icon: 'undo-2', color: '#EAB308' },
      { id: 18, type: 'mystery', title: 'Torii Çarkı', desc: 'Kutsal tapınak ödülü!', icon: 'sparkles', color: '#EC4899' },
      { id: 19, type: 'coin', amount: 5, title: 'Asakusa Feneri', desc: '+5 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 20, type: 'duel', title: 'Samuray Düellosu', desc: 'Rakipten altın kap!', icon: 'swords', color: '#EC4899' },
      { id: 21, type: 'reverse', title: 'Zaman Bükülmesi', desc: 'Sonraki zarda ters yöne yürü!', icon: 'undo-2', color: '#9333EA' },
      { id: 22, type: 'coin', amount: 6, title: 'Tsukiji Balık Pazarı', desc: '+6 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 23, type: 'star', title: 'Fuji Dağı Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 24, type: 'tax', title: 'Tokyo Belediye Vergisi', desc: '%25 Altın cezası en fakir oyuncuya gider!', icon: 'coins', color: '#E11D48' },
      { id: 25, type: 'mystery', title: 'Sakura Sandığı', desc: 'Şansını sına!', icon: 'sparkles', color: '#EC4899' },
      { id: 26, type: 'shop', title: 'Shibuya Alışveriş', desc: 'Nadir taktik eşyaları!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 27, type: 'coin', amount: 8, title: 'İmparatorluk Sarayı', desc: '+8 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 28, type: 'trap', penalty: 5, title: 'Yakuza Pususu', desc: '-5 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
      { id: 29, type: 'duel', title: 'Sumo Arenası', desc: 'Liderden altın arakla!', icon: 'swords', color: '#EC4899' },
      { id: 30, type: 'coin', amount: 5, title: 'Omotesando Caddesi', desc: '+5 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 31, type: 'star', title: 'Meiji Tapınağı', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' }
    ];
  }

  if (theme === 'paris') {
    return [
      { id: 0, type: 'start', title: 'Eyfel Kulesi', desc: '+10 Altın Başlangıç Bonusu!', icon: 'flag', color: '#10B981' },
      { id: 1, type: 'coin', amount: 4, title: 'Champs-Élysées', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 2, type: 'mystery', title: 'Louvre Tablosu', desc: 'Mona Lisa gülümsedi!', icon: 'sparkles', color: '#8B5CF6' },
      { id: 3, type: 'coin', amount: 5, title: 'Kruvasan Fırını', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 4, type: 'trap', penalty: 4, title: 'Parke Taşı Çukuru', desc: '-4 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
      { id: 5, type: 'shop', title: 'Fransız Butiği', desc: 'Lüks parti eşyaları al!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 6, type: 'coin', amount: 6, title: 'Notre-Dame Avlusu', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 7, type: 'star', title: 'Zafer Takı', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 8, type: 'warp', target: 18, title: 'Sen Nehri Teknesi', desc: '18. karoya yüz!', icon: 'zap', color: '#06B6D4' },
      { id: 9, type: 'coin', amount: 4, title: 'Montmartre Tepesi', desc: '+4 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 10, type: 'jail', title: 'Bastille Kalesi', desc: '1 tur kilitli kal veya 5 altın kefalet öde!', icon: 'shield', color: '#DC2626' },
      { id: 11, type: 'tax', title: 'Lüks Moda Vergisi', desc: '%25 Altın cezası en fakir oyuncuya dağıtılır!', icon: 'coins', color: '#E11D48' },
      { id: 12, type: 'mystery', title: 'Cam Piramit', desc: 'Rulet çarkını çevir!', icon: 'sparkles', color: '#8B5CF6' },
      { id: 13, type: 'bank', title: 'Banque de France', desc: 'Kasadaki tüm altınları süpür!', icon: 'coins', color: '#10B981' },
      { id: 14, type: 'shop', title: 'Parfüm Marketi', desc: 'Taktik eşyaları!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 15, type: 'star', title: 'Sacre-Coeur', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 16, type: 'coin', amount: 4, title: 'Latin Mahallesi', desc: '+4 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 17, type: 'slip', steps: -3, title: 'Eriyen Dondurma', desc: '3 adım geri kaydın!', icon: 'undo-2', color: '#EAB308' },
      { id: 18, type: 'mystery', title: 'Moulin Rouge Çarkı', desc: 'Büyük ödüller!', icon: 'sparkles', color: '#8B5CF6' },
      { id: 19, type: 'coin', amount: 5, title: 'Lüksemburg Bahçesi', desc: '+5 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 20, type: 'duel', title: 'Eskrim Meydanı', desc: 'Rakipten altın kap!', icon: 'swords', color: '#EC4899' },
      { id: 21, type: 'reverse', title: 'Sen Nehri Akıntısı', desc: 'Sonraki zarda ters yöne yürü!', icon: 'undo-2', color: '#9333EA' },
      { id: 22, type: 'coin', amount: 6, title: 'Opera Garnier', desc: '+6 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 23, type: 'star', title: 'Pantheon Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 24, type: 'freeze', title: 'Paris Kar Fırtınası', desc: '1 tur eşya kullanımın engellendi!', icon: 'zap', color: '#0284C7' },
      { id: 25, type: 'mystery', title: 'Sanatçı Sandığı', desc: 'Şansını sına!', icon: 'sparkles', color: '#8B5CF6' },
      { id: 26, type: 'shop', title: 'Fransız Butiği', desc: 'Eşya kuşan!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 27, type: 'coin', amount: 8, title: 'Vendome Meydanı', desc: '+8 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 28, type: 'trap', penalty: 5, title: 'Metro Arızası', desc: '-5 Altın!', icon: 'flame', color: '#EF4444' },
      { id: 29, type: 'duel', title: 'Şövalye Düellosu', desc: 'Liderden altın arakla!', icon: 'swords', color: '#EC4899' },
      { id: 30, type: 'coin', amount: 5, title: 'Saint-Germain', desc: '+5 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 31, type: 'star', title: 'Louvre Sarayı', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' }
    ];
  }

  if (theme === 'newyork') {
    return [
      { id: 0, type: 'start', title: 'Times Square', desc: '+10 Altın Başlangıç Bonusu!', icon: 'flag', color: '#10B981' },
      { id: 1, type: 'coin', amount: 4, title: 'Broadway Işıkları', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 2, type: 'mystery', title: 'Sarı Taksi Şansı', desc: 'Sürpriz yolculuk!', icon: 'sparkles', color: '#F59E0B' },
      { id: 3, type: 'coin', amount: 5, title: '5th Avenue Alışverişi', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 4, type: 'trap', penalty: 4, title: 'Buhar Rogarı', desc: '-4 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
      { id: 5, type: 'shop', title: 'Manhattan Market', desc: 'Güçlü eşyalar satın al!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 6, type: 'coin', amount: 6, title: 'Wall Street Borsası', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
      { id: 7, type: 'star', title: 'Empire State', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 8, type: 'warp', target: 20, title: 'Subway Ekspres', desc: '20. karoya fırla!', icon: 'zap', color: '#06B6D4' },
      { id: 9, type: 'coin', amount: 4, title: 'SoHo Sanat', desc: '+4 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 10, type: 'jail', title: 'Rikers Kodesi', desc: '1 tur kilitli kal veya 5 altın kefalet öde!', icon: 'shield', color: '#DC2626' },
      { id: 11, type: 'tax', title: 'Wall Street Vergisi', desc: '%25 Altın cezası en fakir oyuncuya dağıtılır!', icon: 'coins', color: '#E11D48' },
      { id: 12, type: 'mystery', title: 'Gizemli Çatı Katı', desc: 'Rulet çarkını çevir!', icon: 'sparkles', color: '#F59E0B' },
      { id: 13, type: 'bank', title: 'Federal Reserve Bankası', desc: 'Kasadaki tüm altınları topla!', icon: 'coins', color: '#10B981' },
      { id: 14, type: 'shop', title: 'Brooklyn Deposu', desc: 'Taktik eşyaları!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 15, type: 'star', title: 'Özgürlük Heykeli', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 16, type: 'coin', amount: 4, title: 'Central Park', desc: '+4 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 17, type: 'slip', steps: -3, title: 'Muzlu Donut Zemini', desc: '3 adım geri kaydın!', icon: 'undo-2', color: '#EAB308' },
      { id: 18, type: 'mystery', title: 'Grand Central Saati', desc: 'Büyük ödüller!', icon: 'sparkles', color: '#F59E0B' },
      { id: 19, type: 'coin', amount: 5, title: 'DUMBO Manzarası', desc: '+5 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 20, type: 'duel', title: 'Boks Ringi', desc: 'Rakipten altın kap!', icon: 'swords', color: '#EC4899' },
      { id: 21, type: 'freeze', title: 'Blizzard Kar Fırtınası', desc: '1 tur eşya kullanımın engellendi!', icon: 'zap', color: '#0284C7' },
      { id: 22, type: 'coin', amount: 6, title: 'Chrysler Binası', desc: '+6 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 23, type: 'star', title: 'Brooklyn Köprüsü', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
      { id: 24, type: 'reverse', title: 'Trafik Tıkanıklığı', desc: 'Ters yöne gitmek zorundasın!', icon: 'undo-2', color: '#9333EA' },
      { id: 25, type: 'mystery', title: 'Broadway Sandığı', desc: 'Şansını sına!', icon: 'sparkles', color: '#F59E0B' },
      { id: 26, type: 'shop', title: 'Manhattan Market', desc: 'Eşya kuşan!', icon: 'shopping-bag', color: '#F59E0B' },
      { id: 27, type: 'coin', amount: 8, title: 'Federal Reserve Altını', desc: '+8 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 28, type: 'trap', penalty: 5, title: 'Park Cezası', desc: '-5 Altın!', icon: 'flame', color: '#EF4444' },
      { id: 29, type: 'duel', title: 'Wall Street Düellosu', desc: 'Liderden altın arakla!', icon: 'swords', color: '#EC4899' },
      { id: 30, type: 'coin', amount: 5, title: 'Greenwich Village', desc: '+5 Altın!', icon: 'coins', color: '#3B82F6' },
      { id: 31, type: 'star', title: 'One World Observatory', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' }
    ];
  }

  // Default: 'istanbul' (İstanbul - Boğazın İncisi)
  return [
    { id: 0, type: 'start', title: 'Eminönü İskelesi', desc: '+10 Altın Başlangıç Bonusu!', icon: 'flag', color: '#10B981' },
    { id: 1, type: 'coin', amount: 4, title: 'Mısır Çarşısı', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 2, type: 'mystery', title: 'Kapalıçarşı Sandığı', desc: 'Tarihi bir hazine sürprizi!', icon: 'sparkles', color: '#8B5CF6' },
    { id: 3, type: 'coin', amount: 5, title: 'Sultanahmet Meydanı', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 4, type: 'trap', penalty: 4, title: 'Tarihi Arnavutkaldırımı', desc: '-4 Altın düşürdün!', icon: 'flame', color: '#EF4444' },
    { id: 5, type: 'shop', title: 'Kapalıçarşı Bedesteni', desc: 'Güçlü eşyalar satın al!', icon: 'shopping-bag', color: '#F59E0B' },
    { id: 6, type: 'coin', amount: 6, title: 'Karaköy Rıhtımı', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 7, type: 'star', title: 'Galata Kulesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
    { id: 8, type: 'warp', target: 16, title: 'Şehir Hatları Vapuru', desc: '16. karoya yüz!', icon: 'zap', color: '#06B6D4' },
    { id: 9, type: 'coin', amount: 4, title: 'İstiklal Caddesi', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 10, type: 'duel', title: 'Kırkpınar Er Meydanı', desc: 'Rakipten altın kap!', icon: 'swords', color: '#EC4899' },
    { id: 11, type: 'tax', title: 'Boğaz Köprüsü Gişesi', desc: '%25 Altın cezası en fakir oyuncuya dağıtılır!', icon: 'coins', color: '#E11D48' },
    { id: 12, type: 'mystery', title: 'Yerebatan Sarnıcı', desc: 'Rulet çarkını çevir!', icon: 'sparkles', color: '#8B5CF6' },
    { id: 13, type: 'bank', title: 'Topkapı Hazine Kasası', desc: 'Kasadaki tüm altınları tek seferde kap!', icon: 'coins', color: '#10B981' },
    { id: 14, type: 'shop', title: 'Tahtakale Pazarı', desc: 'Nadir taktik eşyaları!', icon: 'shopping-bag', color: '#F59E0B' },
    { id: 15, type: 'star', title: 'Kız Kulesi Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
    { id: 16, type: 'coin', amount: 4, title: 'Üsküdar Sahili', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 17, type: 'jail', title: 'Yedikule Zindanları', desc: '1 tur kilitli kal veya 5 altın kefalet öde!', icon: 'shield', color: '#DC2626' },
    { id: 18, type: 'mystery', title: 'Çamlıca Tepesi', desc: 'Büyük ödüller veya sürprizler!', icon: 'sparkles', color: '#8B5CF6' },
    { id: 19, type: 'coin', amount: 5, title: 'Kadıköy Boğa Meydanı', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 20, type: 'duel', title: 'Boğaziçi Arenası', desc: 'Rakiplerle düello!', icon: 'swords', color: '#EC4899' },
    { id: 21, type: 'freeze', title: 'Karadeniz Kar Fırtınası', desc: 'Buz fırtınası! 1 tur eşya kullanamazsın!', icon: 'zap', color: '#0284C7' },
    { id: 22, type: 'coin', amount: 6, title: 'Dolmabahçe Sarayı', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 23, type: 'star', title: 'Ortaköy Camii Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
    { id: 24, type: 'reverse', title: 'Boğaz Ters Akıntısı', desc: 'Pusula bozuldu! Sonraki zarda geriye yürüyeceksin!', icon: 'undo-2', color: '#9333EA' },
    { id: 25, type: 'mystery', title: 'Balat Renkli Sandık', desc: 'Şansını sına!', icon: 'sparkles', color: '#8B5CF6' },
    { id: 26, type: 'shop', title: 'Beşiktaş Çarşısı', desc: 'Zafer için eşya kuşan!', icon: 'shopping-bag', color: '#F59E0B' },
    { id: 27, type: 'coin', amount: 8, title: 'Boğaziçi Yalıları', desc: '+8 Altın!', icon: 'coins', color: '#3B82F6' },
    { id: 28, type: 'trap', penalty: 4, title: 'Trafik Yoğunluğu', desc: '-4 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
    { id: 29, type: 'duel', title: 'Haliç Hesaplaşması', desc: 'Liderden altın arakla!', icon: 'swords', color: '#EC4899' },
    { id: 30, type: 'coin', amount: 5, title: 'Taksim Meydanı', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
    { id: 31, type: 'star', title: 'Ayasofya Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' }
  ];
}

const BOARD_TILES = generateBoardForTheme('istanbul');

const BOT_NAMES = [
  { name: 'Lego Usta', avatar: 'builder', title: 'Mimar', color: '#3B82F6', personality: 'balanced' },
  { name: 'Yeşil Dino', avatar: 'dino', title: 'Parti Canavarı', color: '#10B981', personality: 'chaotic' },
  { name: 'Siber Kedi', avatar: 'cat', title: 'Hızlı Patiler', color: '#EC4899', personality: 'greedy' },
  { name: 'Gandi Bot', avatar: 'wizard', title: 'Büyücü', color: '#8B5CF6', personality: 'strategic' },
  { name: 'Robo-X', avatar: 'robot', title: 'Hesap Uzmanı', color: '#06B6D4', personality: 'balanced' },
  { name: 'Dedektif', avatar: 'detective', title: 'Gizem Avcısı', color: '#F59E0B', personality: 'strategic' }
];

const SHOP_ITEMS = [
  { id: 'double_dice', name: 'Çift Zar', cost: 7, desc: 'Bir sonraki turda 2 zar birden atarsın (2-12 adım).', icon: 'dices' },
  { id: 'rocket', name: 'Süper Roket', cost: 12, desc: 'Hedefine doğru direkt 7 adım uçar!', icon: 'rocket' },
  { id: 'shield', name: 'Manyetik Kalkan', cost: 8, desc: 'Tuzaklara ve altın çalma hamlelerine karşı 2 tur korur.', icon: 'shield' },
  { id: 'magnet', name: 'Altın Mıknatısı', cost: 10, desc: 'Bütün rakiplerden 4\'er altın çeker!', icon: 'magnet' },
  { id: 'banana', name: 'Muz Kabuğu', cost: 5, desc: 'Bulunduğun karoya tuzak kur; üstüne basan 3 adım geri kayar!', icon: 'banana' },
  { id: 'swap_wand', name: 'Takas Asası', cost: 15, desc: 'İstediğin bir rakiple haritadaki yerlerini takas et!', icon: 'refresh-cw' },
  { id: 'ghost_cloak', name: 'Hayalet Pelerin', cost: 9, desc: '2 tur boyunca hiçbir tuzaktan veya cezadan etkilenmezsin!', icon: 'shield' },
  { id: 'lucky_die', name: 'Şanslı Altın Zar', cost: 14, desc: 'Sıradaki zarda 1-6 arasında istediğin sayıyı sen seçersin!', icon: 'dices' }
];

class Room {
  // ---------------------------------------------------------------------------
  // Input sanitizers (static so the single-file build's LocalRoom copy keeps them)
  // ---------------------------------------------------------------------------
  static cleanText(value, maxLen, fallback = '') {
    if (typeof value !== 'string') return fallback;
    // Strip HTML-significant characters and control characters
    const cleaned = value.replace(/[<>"'`&\\]/g, '').replace(/[\u0000-\u001F\u007F]/g, '').trim();
    return cleaned ? Array.from(cleaned).slice(0, maxLen).join('') : fallback;
  }

  static cleanSlug(value, fallback) {
    return (typeof value === 'string' && /^[A-Za-z0-9_-]{1,32}$/.test(value)) ? value : fallback;
  }

  static cleanColor(value, fallback = null) {
    return (typeof value === 'string' && /^#[0-9A-Fa-f]{6}$/.test(value)) ? value : fallback;
  }

  static cleanInt(value, min, max, fallback) {
    const n = parseInt(value, 10);
    if (!Number.isFinite(n)) return fallback;
    return Math.min(max, Math.max(min, n));
  }

  static normalizeTheme(theme) {
    const aliases = { cyber: 'tokyo', candy: 'paris', tropics: 'istanbul' };
    const t = aliases[theme] || theme;
    return ['istanbul', 'tokyo', 'paris', 'newyork'].includes(t) ? t : 'istanbul';
  }

  constructor(roomId, hostSessionId, hostName, settings = {}) {
    this.id = roomId;
    this.hostSessionId = hostSessionId;
    this.state = 'lobby'; // 'lobby' | 'playing' | 'minigame' | 'gameover'
    this.totalRounds = this.constructor.cleanInt(settings.totalRounds, 3, 30, 10);
    this.mapTheme = this.constructor.normalizeTheme(settings.mapTheme || 'istanbul');
    this.currentRound = 1;
    this.turnIndex = 0; // index in players array
    this.players = []; // list of Player objects
    this.activeStarTile = 7; // initial star tile location
    this.board = generateBoardForTheme(this.mapTheme);
    this.trapsOnBoard = {}; // tileIndex -> ownerPlayerId
    this.pendingTrades = {}; // tradeId -> TradeObject
    this.log = []; // recent event feed
    this.minigame = null;
    this.createdAt = Date.now();
    this.turnTimer = null;
    this.botActionTimeout = null;
    this.isProcessingTurn = false;
    this.gameSpeed = 1;
    // Incremented on every finished turn; deferred callbacks capture it so a stale
    // timer (shop auto-close, double finish, etc.) can never end a later turn.
    this.turnSerial = 0;
    this.shopOpenFor = null; // player id allowed to buy from the shop right now
    this.destroyed = false;
    this.bankPool = 10;
  }

  // Run a callback later, but only if the same turn is still in progress.
  deferForTurn(ms, fn) {
    const serial = this.turnSerial;
    setTimeout(() => {
      if (this.destroyed || this.turnSerial !== serial) return;
      fn();
    }, ms);
  }

  deferFinishTurn(ms) {
    this.deferForTurn(ms, () => this.finishTurn());
  }

  destroy() {
    this.destroyed = true;
    this.turnSerial++;
    clearTimeout(this.botActionTimeout);
    clearTimeout(this.minigameTimeout);
    this.players.forEach(p => { p.ws = null; });
  }

  hasConnectedHumans() {
    return this.players.some(p => !p.isBot && p.ws && p.ws.readyState === 1);
  }

  addPlayer(sessionId, name, avatar = 'builder', diceSkin = 'default', level = 1, title = 'Acemi Zarcı', ws = null, hat = 'none', customColor = null) {
    const R = this.constructor;
    name = R.cleanText(name, 16, '');
    title = R.cleanText(title, 24, 'Acemi Zarcı');
    avatar = R.cleanSlug(avatar, 'builder');
    diceSkin = R.cleanSlug(diceSkin, 'default');
    hat = R.cleanSlug(hat, 'none');
    customColor = R.cleanColor(customColor, null);
    level = R.cleanInt(level, 1, 100, 1);

    let existing = this.players.find(p => p.sessionId === sessionId);
    if (existing) {
      // Reconnection!
      existing.ws = ws;
      existing.isBot = false;
      existing.disconnected = false;
      existing.name = name || existing.name;
      existing.avatar = avatar || existing.avatar;
      if (hat && hat !== 'none') existing.hat = hat;
      if (customColor) existing.color = customColor;
      this.addLog(`🌟 ${existing.name} oyuna geri döndü!`);
      return existing;
    }

    if (this.players.length >= 6) return null;
    // New humans can only join while the room is still in the lobby
    if (this.state !== 'lobby') return null;

    const colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4', '#1E293B'];
    const playerColor = customColor || colors[this.players.length % colors.length];

    const newPlayer = {
      id: 'p_' + Math.random().toString(36).substr(2, 6),
      sessionId,
      name: name || `Oyuncu ${this.players.length + 1}`,
      avatar,
      hat: hat || 'none',
      diceSkin,
      level: level || 1,
      title: title || 'Acemi Zarcı',
      color: playerColor,
      position: 0,
      coins: 15, // starting coins
      stars: 0,
      inventory: [],
      shieldActive: 0, // turns remaining
      jailedTurns: 0,
      frozenTurns: 0,
      reverseNext: false,
      doubleDiceActive: false,
      ghostCloakActive: 0,
      fixedRollNext: null,
      isBot: false,
      disconnected: false,
      ws: ws
    };

    this.players.push(newPlayer);
    this.addLog(`👋 ${newPlayer.name} partiye katıldı!`);
    return newPlayer;
  }

  updatePlayerProfile(sessionId, profileData = {}) {
    const player = this.players.find(p => p.sessionId === sessionId);
    if (!player) return;
    const R = this.constructor;
    player.name = R.cleanText(profileData.name, 16, player.name);
    player.avatar = R.cleanSlug(profileData.avatar, player.avatar);
    player.hat = R.cleanSlug(profileData.hat, player.hat);
    player.color = R.cleanColor(profileData.color, player.color);
    player.diceSkin = R.cleanSlug(profileData.diceSkin, player.diceSkin);
    player.title = R.cleanText(profileData.title, 24, player.title);
  }

  addBot(personality = null) {
    if (this.players.length >= 6) return null;
    const botTemplate = BOT_NAMES[this.players.length % BOT_NAMES.length];
    const colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4'];
    const botColor = colors[this.players.length % colors.length];
    const botHats = ['crown', 'wizard', 'pirate', 'cat_ears', 'party_hat', 'visor', 'detective', 'viking'];
    const randomHat = botHats[Math.floor(Math.random() * botHats.length)];

    const botPlayer = {
      id: 'bot_' + Math.random().toString(36).substr(2, 6),
      sessionId: 'bot_sess_' + Math.random().toString(36).substr(2, 8),
      name: `[BOT] ${botTemplate.name}`,
      avatar: botTemplate.avatar,
      hat: randomHat,
      diceSkin: 'neon',
      level: Math.floor(Math.random() * 8) + 3,
      title: botTemplate.title,
      color: botColor,
      position: 0,
      coins: 15,
      stars: 0,
      inventory: ['double_dice'],
      shieldActive: 0,
      jailedTurns: 0,
      frozenTurns: 0,
      reverseNext: false,
      doubleDiceActive: false,
      ghostCloakActive: 0,
      fixedRollNext: null,
      isBot: true,
      personality: personality || botTemplate.personality,
      disconnected: false,
      ws: null
    };

    this.players.push(botPlayer);
    this.addLog(`🤖 ${botPlayer.name} oyuna eklendi!`);
    return botPlayer;
  }

  removePlayer(sessionId) {
    const idx = this.players.findIndex(p => p.sessionId === sessionId);
    if (idx !== -1) {
      const removed = this.players.splice(idx, 1)[0];
      this.addLog(`🚪 ${removed.name} ayrıldı.`);
      // Keep the turn pointer on the same player when someone before them leaves
      if (idx < this.turnIndex) this.turnIndex--;
      if (this.turnIndex >= this.players.length) {
        this.turnIndex = 0;
      }
      // Hand the host role to the next human so the lobby is not orphaned
      if (removed.sessionId === this.hostSessionId) {
        const nextHost = this.players.find(p => !p.isBot);
        this.hostSessionId = nextHost ? nextHost.sessionId : null;
        if (nextHost) this.addLog(`👑 ${nextHost.name} artık oda sahibi!`);
      }
    }
  }

  isHost(sessionId) {
    return !!sessionId && sessionId === this.hostSessionId;
  }

  handleDisconnect(sessionId) {
    const player = this.players.find(p => p.sessionId === sessionId);
    if (!player) return;

    if (this.state === 'lobby') {
      this.removePlayer(sessionId);
    } else {
      // During game: Bot seamlessly takes over!
      player.isBot = true;
      player.disconnected = true;
      player.ws = null;
      this.addLog(`🤖 ${player.name} ayrıldı, yerine akıllı bot geçti!`);
      // If it was currently their turn, trigger bot turn
      if (this.getCurrentPlayer() && this.getCurrentPlayer().sessionId === sessionId) {
        this.scheduleBotTurn();
      }
    }
  }

  getCurrentPlayer() {
    return this.players[this.turnIndex] || null;
  }

  addLog(message) {
    this.log.unshift({
      id: Math.random().toString(36).substr(2, 6),
      time: new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      text: message
    });
    if (this.log.length > 25) this.log.pop();
  }

  // Session ids act as reconnect secrets, so each recipient only ever sees its own.
  static redactSessionIds(list, viewerSessionId) {
    return list.map(entry => {
      if (!entry || entry.sessionId === undefined || entry.sessionId === viewerSessionId) return entry;
      const { sessionId, ...rest } = entry;
      return rest;
    });
  }

  broadcast(type, payload = {}) {
    const needsRedaction = Array.isArray(payload.players) || Array.isArray(payload.leaderboard) || (payload.winner && payload.winner.sessionId);
    const shared = needsRedaction ? null : JSON.stringify({ type, ...payload });
    this.players.forEach(p => {
      if (!p.ws || p.ws.readyState !== WebSocket.OPEN) return;
      if (shared) {
        p.ws.send(shared);
        return;
      }
      const personal = { type, ...payload };
      if (Array.isArray(payload.players)) personal.players = Room.redactSessionIds(payload.players, p.sessionId);
      if (Array.isArray(payload.leaderboard)) personal.leaderboard = Room.redactSessionIds(payload.leaderboard, p.sessionId);
      if (payload.winner) personal.winner = Room.redactSessionIds([payload.winner], p.sessionId)[0];
      p.ws.send(JSON.stringify(personal));
    });
  }

  broadcastState() {
    this.broadcast('state_update', {
      roomId: this.id,
      state: this.state,
      mapTheme: this.mapTheme,
      totalRounds: this.totalRounds,
      currentRound: this.currentRound,
      turnIndex: this.turnIndex,
      activeStarTile: this.activeStarTile,
      trapsOnBoard: this.trapsOnBoard,
      board: this.board,
      players: this.players.map(p => ({
        id: p.id,
        sessionId: p.sessionId,
        name: p.name,
        avatar: p.avatar,
        hat: p.hat || 'none',
        diceSkin: p.diceSkin,
        level: p.level,
        title: p.title,
        color: p.color,
        position: p.position,
        coins: p.coins,
        stars: p.stars,
        inventory: p.inventory,
        shieldActive: p.shieldActive || 0,
        ghostCloakActive: p.ghostCloakActive || 0,
        doubleDiceActive: !!p.doubleDiceActive,
        fixedRollNext: p.fixedRollNext || null,
        jailedTurns: p.jailedTurns,
        frozenTurns: p.frozenTurns,
        reverseNext: p.reverseNext,
        isBot: p.isBot,
        disconnected: p.disconnected
      })),
      log: this.log.slice(0, 15)
    });
  }

  startGame() {
    if (this.players.length < 1) return false;
    // Auto fill with bots if single player
    while (this.players.length < 4) {
      this.addBot();
    }

    this.state = 'playing';
    this.currentRound = 1;
    this.turnIndex = 0;
    const starTiles = this.getStarTiles();
    this.activeStarTile = starTiles[Math.floor(Math.random() * starTiles.length)];
    this.addLog(`🚀 Parti başladı! Toplam ${this.totalRounds} tur oynanacak. Yıldız kaidesi ${this.activeStarTile}. karoda!`);

    this.broadcastState();
    this.checkNextTurnAction();
    return true;
  }

  checkNextTurnAction() {
    clearTimeout(this.botActionTimeout);
    const current = this.getCurrentPlayer();
    if (!current) return;

    if (current.isBot) {
      this.scheduleBotTurn();
    }
  }

  scheduleBotTurn() {
    clearTimeout(this.botActionTimeout);
    if (this.destroyed) return;
    const spd = this.gameSpeed || 1;
    this.botActionTimeout = setTimeout(() => {
      this.executeBotTurn();
    }, Math.max(250, Math.round(1600 / spd)));
  }

  executeBotTurn() {
    const bot = this.getCurrentPlayer();
    if (!bot || !bot.isBot || this.state !== 'playing') return;

    // Bot decision: Maybe use item?
    if (bot.inventory.length > 0) {
      const itemToUse = bot.inventory[0];
      if (itemToUse === 'double_dice' && !bot.doubleDiceActive) {
        this.useItem(bot.sessionId, 'double_dice');
      } else if (itemToUse === 'magnet' && Math.random() > 0.4) {
        this.useItem(bot.sessionId, 'magnet');
      } else if (itemToUse === 'shield' && bot.shieldActive === 0) {
        this.useItem(bot.sessionId, 'shield');
      } else if (itemToUse === 'banana' && Math.random() > 0.5) {
        this.useItem(bot.sessionId, 'banana');
      }
    }

    // Bot rolls dice after small pause
    const spd = this.gameSpeed || 1;
    this.deferForTurn(Math.max(180, Math.round(800 / spd)), () => {
      this.rollDice(bot.sessionId);
    });
  }

  rollDice(sessionId) {
    if (this.isProcessingTurn) return;
    const player = this.getCurrentPlayer();
    if (!player || player.sessionId !== sessionId || this.state !== 'playing') return;

    this.isProcessingTurn = true;

    const spd = this.gameSpeed || 1;

    // Check if player is jailed
    if (player.jailedTurns > 0) {
      if (player.coins >= 5) {
        player.coins -= 5;
        player.jailedTurns = 0;
        this.addLog(`🔑 ${player.name} 5 altın kefalet ödeyerek Kodes'ten serbest kaldı!`);
      } else {
        player.jailedTurns--;
        this.addLog(`⛓️ ${player.name} Kodes cezasını çekiyor, bu turu pas geçti!`);
        this.broadcastState();
        this.deferFinishTurn(Math.max(300, Math.round(1200 / spd)));
        return;
      }
    }

    let die1 = player.fixedRollNext ? player.fixedRollNext : (Math.floor(Math.random() * 6) + 1);
    const wasFixed = !!player.fixedRollNext;
    player.fixedRollNext = null;
    let die2 = null;
    let totalRoll = die1;

    if (player.doubleDiceActive) {
      die2 = Math.floor(Math.random() * 6) + 1;
      totalRoll = die1 + die2;
      player.doubleDiceActive = false;
      this.addLog(`🎲 ${player.name} ÇİFT ZAR attı: [${die1}] + [${die2}] = ${totalRoll} adım!`);
    } else {
      if (wasFixed) {
        this.addLog(`🎯 ${player.name} Şanslı Altın Zarını attı: [${die1}]!`);
      } else {
        this.addLog(`🎲 ${player.name} zar attı: [${die1}]!`);
      }
    }

    // Broadcast dice roll event so client can play 3D dice rolling animation
    this.broadcast('dice_rolled', {
      playerId: player.id,
      playerName: player.name,
      die1,
      die2,
      totalRoll
    });

    // Move player step by step AFTER dice modal finishes and closes completely
    this.deferForTurn(Math.max(300, Math.round(1500 / spd)), () => {
      this.movePlayerSteps(player, totalRoll);
    });
  }

  movePlayerSteps(player, steps) {
    const isReverse = !!player.reverseNext;
    player.reverseNext = false;
    const effectiveSteps = isReverse ? -steps : steps;

    const oldPos = player.position;
    let newPos = (oldPos + effectiveSteps + this.board.length) % this.board.length;

    if (isReverse) {
      this.addLog(`🌀 Ters Yön Laneti devrede! ${player.name} ${steps} adım GERİYE yürüdü!`);
    }

    // Check if player passed or landed on start tile (forward only)
    if (!isReverse && oldPos + steps >= this.board.length) {
      player.coins += 10;
      this.addLog(`💰 ${player.name} Başlangıçtan geçti ve +10 Altın kazandı!`);
    }

    // Check if player passed by Bank tile (deposits 2 coins into the jackpot pool)
    if (!isReverse) {
      for (let s = 1; s <= steps; s++) {
        let passedPos = (oldPos + s) % this.board.length;
        if (this.board[passedPos] && this.board[passedPos].type === 'bank') {
          this.bankPool = (this.bankPool || 10) + 2;
          this.addLog(`🏦 ${player.name} Bankadan geçti ve kasaya +2 Altın faiz eklendi (Kasa: ${this.bankPool} 🪙)!`);
        }
      }
    }

    // Check if player passed by active Star tile along the way
    if (!isReverse) {
      for (let s = 1; s <= steps; s++) {
        let passedPos = (oldPos + s) % this.board.length;
        if (passedPos === this.activeStarTile) {
          // Player has chance to buy star!
          if (player.coins >= 20) {
            player.coins -= 20;
            player.stars += 1;
            this.addLog(`⭐ TEBRİKLER! ${player.name} 20 altına bir ALTIN YILDIZ satın aldı!`);
            this.relocateStar();
            break;
          } else {
            this.addLog(`✨ ${player.name} Yıldız kaidesinden geçti ama 20 altını yoktu (${player.coins}/20).`);
          }
        }
      }
    }

    player.position = newPos;
    this.broadcastState();

    // Trigger Tile Effect strictly after Monopoly-style hopping completes
    const spd = this.gameSpeed || 1;
    const hopDurationMs = Math.max(300, Math.round(((Math.abs(steps) * 380) + 400) / spd));
    this.deferForTurn(hopDurationMs, () => {
      this.resolveTileEffect(player, newPos);
    });
  }

  // Indices of the board's star pedestal tiles (falls back to the classic layout)
  getStarTiles() {
    const stars = this.board.filter(t => t.type === 'star').map(t => t.id);
    return stars.length > 0 ? stars : [7, 15, 23, 31];
  }

  relocateStar() {
    const starCandidates = this.getStarTiles().filter(idx => idx !== this.activeStarTile);
    if (starCandidates.length === 0) return;
    this.activeStarTile = starCandidates[Math.floor(Math.random() * starCandidates.length)];
    this.addLog(`🌟 Yıldız yeni bir konuma ışınlandı: ${this.activeStarTile}. Karo!`);
  }

  resolveTileEffect(player, tileIndex) {
    const tile = this.board[tileIndex];
    const spd = this.gameSpeed || 1;

    // Check if there is a Banana Trap set here by another player
    if (this.trapsOnBoard[tileIndex] && this.trapsOnBoard[tileIndex] !== player.id) {
      if (player.ghostCloakActive > 0) {
        this.addLog(`👻 ${player.name} Hayalet Pelerin sayesinde muz kabuğu tuzağının üstünden süzülüp geçti!`);
      } else {
        delete this.trapsOnBoard[tileIndex];
        this.addLog(`🍌 Oooops! ${player.name} muz kabuğuna bastı ve 3 adım geri kaydı!`);
        player.position = (player.position - 3 + this.board.length) % this.board.length;
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'penalty',
          title: '🍌 MUZ KABUĞUNA BASTI!',
          message: `${player.name} muz kabuğuna basıp 360° takla attı ve 3 adım geri kaydı! 😂`,
          animation: 'banana_slip',
          sound: 'slip'
        });
        this.broadcastState();
        this.deferFinishTurn(Math.max(350, Math.round(1800 / spd)));
        return;
      }
    }

    // Check Ghost Cloak immunity against hazard tiles
    if (player.ghostCloakActive > 0 && (tile.type === 'trap' || tile.type === 'tax' || tile.type === 'jail' || tile.type === 'slip' || tile.type === 'freeze')) {
      this.addLog(`👻 Hayalet Pelerin devrede! ${player.name} ${tile.type.toUpperCase()} cezasını dokunmadan es geçti!`);
      this.broadcastState();
      this.deferFinishTurn(Math.max(250, Math.round(900 / spd)));
      return;
    }

    switch (tile.type) {
      case 'coin': {
        player.coins += tile.amount;
        this.addLog(`🪙 ${player.name} altın karosuna bastı: +${tile.amount} Altın!`);
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'reward',
          title: `🪙 +${tile.amount} ALTIN!`,
          message: `${player.name} hazine karosuna bastı ve ${tile.amount} altın kazandı!`,
          animation: 'coin_shower',
          amount: tile.amount,
          sound: 'coin'
        });
        this.broadcastState();
        this.deferFinishTurn(2000);
        break;
      }

      case 'bank': {
        const pool = this.bankPool || 10;
        player.coins += pool;
        this.bankPool = 4;
        this.addLog(`🏦 PARTİ BANKASI! ${player.name} kasadaki ${pool} altını tek seferde süpürdü!`);
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'reward',
          title: `🏦 BANKA KASASI PATLADI! (+${pool} 🪙)`,
          message: `${player.name} biriken banka kasasındaki ${pool} altını tek hamlede cebe indirdi!`,
          animation: 'coin_shower',
          amount: pool,
          sound: 'fanfare'
        });
        this.broadcastState();
        this.deferFinishTurn(2200);
        break;
      }

      case 'trap': {
        if (player.shieldActive > 0) {
          player.shieldActive--;
          this.addLog(`🛡️ ${player.name} Kalkanı sayesinde tuzağı savuşturdu!`);
          this.broadcast('tile_event', {
            playerId: player.id,
            playerName: player.name,
            avatar: player.avatar,
            category: 'neutral',
            title: '🛡️ KALKAN KORUDU!',
            message: `${player.name} tuzağa bastı ama koruma kalkanı hasarı tamamen engelledi!`,
            animation: 'shield_block',
            sound: 'shield'
          });
        } else {
          const penalty = tile.penalty || 5;
          const lostCoins = Math.min(player.coins, penalty);
          player.coins = Math.max(0, player.coins - penalty);
          this.addLog(`💥 ${player.name} tuzağa düştü: -${penalty} Altın!`);
          this.broadcast('tile_event', {
            playerId: player.id,
            playerName: player.name,
            avatar: player.avatar,
            category: 'penalty',
            title: `💥 DİKENLİ TUZAK! (-${penalty} Altın)`,
            message: `${player.name} kızgın tuzağa düştü ve ${lostCoins} altını çevreye saçıldı!`,
            animation: 'trap_explosion',
            amount: -penalty,
            sound: 'trap'
          });
        }
        this.broadcastState();
        this.deferFinishTurn(2200);
        break;
      }

      case 'mystery':
        this.triggerMysteryEvent(player);
        break;

      case 'shop':
        this.triggerShop(player);
        break;

      case 'warp': {
        this.addLog(`🌀 ${player.name} hava geçidinden geçerek ${tile.target}. karoya ışınlandı!`);
        player.position = tile.target;
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'neutral',
          title: '🌀 IŞINLANMA GEÇİDİ!',
          message: `${player.name} gizemli portaldan geçerek ${tile.target}. karoya fırladı!`,
          animation: 'warp_portal',
          sound: 'warp'
        });
        this.broadcastState();
        this.deferFinishTurn(2200);
        break;
      }

      case 'slip': {
        this.addLog(`🍌 ${player.name} kaygan zeminde 3 adım geri kaydı!`);
        player.position = (player.position - 3 + this.board.length) % this.board.length;
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'penalty',
          title: '🍌 KAYGAN ZEMİN!',
          message: `${player.name} dengesini kaybedip 360° takla attı ve 3 adım geriye yuvarlandı! 😂`,
          animation: 'banana_slip',
          sound: 'slip'
        });
        this.broadcastState();
        this.deferFinishTurn(2200);
        break;
      }

      case 'jail': {
        player.jailedTurns = 1;
        this.addLog(`🔒 KODES! ${player.name} nezarete atıldı! 1 tur kilitli kalacak veya 5 altın kefalet ödeyecek!`);
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'penalty',
          title: '⛓️ KODES\'E TIKILDI!',
          message: `${player.name} tutuklandı! 1 tur parmaklıklar ardında kalacak veya 5 altın kefalet ödeyecek!`,
          animation: 'jail_slam',
          sound: 'jail'
        });
        this.broadcastState();
        this.deferFinishTurn(2200);
        break;
      }

      case 'tax': {
        const taxVal = Math.max(3, Math.floor(player.coins * 0.25));
        player.coins = Math.max(0, player.coins - taxVal);
        const poorest = this.players.filter(p => p.id !== player.id).sort((a, b) => a.coins - b.coins)[0];
        if (poorest) {
          poorest.coins += taxVal;
          this.addLog(`💸 SOYGUN! ${player.name} ${taxVal} altın vergi ödedi, altınlar ${poorest.name}'a dağıtıldı!`);
          this.broadcast('tile_event', {
            playerId: player.id,
            playerName: player.name,
            avatar: player.avatar,
            category: 'penalty',
            title: `💸 VERGİ BASKINI! (-${taxVal} Altın)`,
            message: `${player.name}'dan kesilen ${taxVal} altın en fakir oyuncu ${poorest.name}'a dağıtıldı!`,
            animation: 'tax_robbery',
            amount: -taxVal,
            sound: 'tax'
          });
        } else {
          this.addLog(`💸 VERGİ! ${player.name} ${taxVal} altın vergi ödedi!`);
          this.broadcast('tile_event', {
            playerId: player.id,
            playerName: player.name,
            avatar: player.avatar,
            category: 'penalty',
            title: `💸 VERGİ BASKINI! (-${taxVal} Altın)`,
            message: `${player.name} kasaya ${taxVal} altın ceza vergisi ödedi!`,
            animation: 'tax_robbery',
            amount: -taxVal,
            sound: 'tax'
          });
        }
        this.broadcastState();
        this.deferFinishTurn(2200);
        break;
      }

      case 'freeze': {
        // Status counters tick down at the end of the owner's turn (finishTurn).
        // Landing happens at the end of this turn, so 2 = "the whole next turn".
        player.frozenTurns = 2;
        this.addLog(`🧊 BUZ TUZAĞI! ${player.name} donduruldu! Bir tur boyunca eşya kullanamaz!`);
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'penalty',
          title: '🧊 BUZ KESTİ!',
          message: `${player.name} kutup soğuğunda dondu! Bir sonraki turunda hiçbir eşya kullanamaz!`,
          animation: 'freeze_frost',
          sound: 'freeze'
        });
        this.broadcastState();
        this.deferFinishTurn(2000);
        break;
      }

      case 'reverse': {
        player.reverseNext = true;
        this.addLog(`🔄 TERS YÖN LANETİ! ${player.name} sonraki zarında ters yöne gidecek!`);
        this.broadcast('tile_event', {
          playerId: player.id,
          playerName: player.name,
          avatar: player.avatar,
          category: 'penalty',
          title: '🔄 PUSULA ŞAŞTI!',
          message: `Lanet devrede! ${player.name} bir sonraki hamlesinde GERİYE doğru yürüyecek!`,
          animation: 'reverse_curse',
          sound: 'reverse'
        });
        this.broadcastState();
        this.deferFinishTurn(Math.max(350, Math.round(1600 / spd)));
        break;
      }

      case 'duel':
        this.triggerDuel(player);
        break;

      default:
        this.broadcastState();
        this.deferFinishTurn(Math.max(200, Math.round(700 / spd)));
        break;
    }
  }

  triggerMysteryEvent(player) {
    const spd = this.gameSpeed || 1;
    const events = [
      { name: 'Gökten Altın', fn: () => { player.coins += 12; this.addLog(`🎁 Şans Çarkı: ${player.name} gökten düşen sandıktan +12 Altın kaptı!`); } },
      { name: 'Çifte Şans', fn: () => { player.inventory.push('double_dice'); this.addLog(`🎁 Şans Çarkı: ${player.name} ücretsiz Çift Zar kazandı!`); } },
      { name: 'Kalkan Hediyesi', fn: () => { player.shieldActive = 3; /* granted at turn end: covers the next 2 turns */ this.addLog(`🎁 Şans Çarkı: ${player.name} 2 turluk koruma kalkanı kazandı!`); } },
      { name: 'Liderden Arakla', fn: () => {
        const others = this.players.filter(p => p.id !== player.id && p.coins > 0);
        if (others.length > 0) {
          const target = others.reduce((max, p) => p.coins > max.coins ? p : max, others[0]);
          const stolen = Math.min(6, target.coins);
          target.coins -= stolen;
          player.coins += stolen;
          this.addLog(`🎁 Şans Çarkı: ${player.name}, ${target.name}'dan ${stolen} altın aşırdı!`);
        } else {
          player.coins += 8;
          this.addLog(`🎁 Şans Çarkı: ${player.name} +8 Altın kazandı!`);
        }
      }},
      { name: 'Yıldız İndirimi', fn: () => { player.coins += 15; this.addLog(`🎁 Şans Çarkı: Yıldız yardım fonu! ${player.name} +15 Altın kazandı!`); } }
    ];

    const chosen = events[Math.floor(Math.random() * events.length)];
    this.broadcast('mystery_triggered', {
      playerId: player.id,
      playerName: player.name,
      eventName: chosen.name
    });

    this.deferForTurn(Math.max(350, Math.round(1400 / spd)), () => {
      chosen.fn();
      this.broadcastState();
      this.deferFinishTurn(Math.max(250, Math.round(1000 / spd)));
    });
  }

  triggerShop(player) {
    const spd = this.gameSpeed || 1;
    // If bot, auto buy useful item if afford
    if (player.isBot) {
      const costOf = (id) => (SHOP_ITEMS.find(i => i.id === id) || { cost: 999 }).cost;
      if (player.coins >= costOf('double_dice') + 3 && !player.inventory.includes('double_dice')) {
        player.coins -= costOf('double_dice');
        player.inventory.push('double_dice');
        this.addLog(`🏪 ${player.name} Dükkandan Çift Zar satın aldı!`);
      } else if (player.coins >= costOf('magnet') + 2 && !player.inventory.includes('magnet')) {
        player.coins -= costOf('magnet');
        player.inventory.push('magnet');
        this.addLog(`🏪 ${player.name} Dükkandan Altın Mıknatısı aldı!`);
      }
      this.broadcastState();
      this.deferFinishTurn(Math.max(200, Math.round(800 / spd)));
      return;
    }

    // If human, notify client to open shop modal; only this player may buy until the turn ends
    this.shopOpenFor = player.id;
    this.broadcast('open_shop', {
      playerId: player.id,
      items: SHOP_ITEMS
    });

    // Auto close shop after 15 seconds if player is idle (turn-guarded: never ends a later turn)
    this.deferFinishTurn(15000);
  }

  buyItem(sessionId, itemId) {
    const player = this.players.find(p => p.sessionId === sessionId);
    const item = SHOP_ITEMS.find(i => i.id === itemId);
    const current = this.getCurrentPlayer();
    if (!player || !item || this.state !== 'playing') return 'invalid';
    if (!current || current.id !== player.id || this.shopOpenFor !== player.id) return 'shop_closed';
    if (player.coins < item.cost) return 'no_coins';
    player.coins -= item.cost;
    player.inventory.push(item.id);
    this.addLog(`🛍️ ${player.name} ${item.name} satın aldı!`);
    this.broadcastState();
    return null;
  }

  closeShop(sessionId) {
    const current = this.getCurrentPlayer();
    if (current && current.sessionId === sessionId && this.shopOpenFor === current.id) {
      this.finishTurn();
    }
  }

  triggerDuel(player) {
    const spd = this.gameSpeed || 1;
    const rivals = this.players.filter(p => p.id !== player.id);
    if (rivals.length === 0) {
      this.finishTurn();
      return;
    }
    const rival = rivals[Math.floor(Math.random() * rivals.length)];
    const roll1 = Math.floor(Math.random() * 6) + 1;
    const roll2 = Math.floor(Math.random() * 6) + 1;

    let duelResult = '';
    if (roll1 >= roll2) {
      const loot = Math.min(5, rival.coins);
      rival.coins -= loot;
      player.coins += loot;
      duelResult = `${player.name} (${roll1}) vs ${rival.name} (${roll2}) -> ${player.name} kazandı ve ${loot} altın aldı!`;
    } else {
      const lost = Math.min(5, player.coins);
      player.coins -= lost;
      rival.coins += lost;
      duelResult = `${player.name} (${roll1}) vs ${rival.name} (${roll2}) -> ${rival.name} kazandı ve ${lost} altın aldı!`;
    }

    this.addLog(`⚔️ Düello! ${duelResult}`);
    this.broadcast('duel_result', { text: duelResult });
    this.broadcastState();
    this.deferFinishTurn(Math.max(300, Math.round(1200 / spd)));
  }

  useItem(sessionId, itemId, targetPlayerId = null) {
    const player = this.players.find(p => p.sessionId === sessionId);
    if (!player) return 'invalid';

    // Items can only be used on your own turn, before the dice are rolled
    const current = this.getCurrentPlayer();
    if (this.state !== 'playing' || !current || current.id !== player.id) return 'not_your_turn';
    if (this.isProcessingTurn) return 'turn_in_progress';

    if (player.frozenTurns > 0) {
      this.addLog(`🧊 ${player.name} donmuş durumda, eşya kullanamaz!`);
      this.broadcastState();
      return 'frozen';
    }

    const idx = player.inventory.indexOf(itemId);
    if (idx === -1) return 'no_item';

    let swapTarget = null;
    if (itemId === 'swap_wand') {
      swapTarget = this.players.find(p => p.id === targetPlayerId && p.id !== player.id)
        || this.players.find(p => p.id !== player.id);
      if (!swapTarget) return 'no_target';
    }

    player.inventory.splice(idx, 1);

    switch (itemId) {
      case 'double_dice':
        player.doubleDiceActive = true;
        this.addLog(`⚡ ${player.name} Çift Zar gücünü etkinleştirdi!`);
        break;

      case 'rocket':
        this.addLog(`🚀 ${player.name} Roket ateşledi ve 7 adım ileri fırladı!`);
        // The rocket replaces this turn's roll: lock the turn so dice can't also be rolled
        this.isProcessingTurn = true;
        this.movePlayerSteps(player, 7);
        return null;

      case 'shield':
        player.shieldActive = 2;
        this.addLog(`🛡️ ${player.name} Manyetik Kalkan kuşandı (2 tur koruma)!`);
        break;

      case 'magnet':
        let totalStolen = 0;
        this.players.forEach(other => {
          if (other.id !== player.id && other.shieldActive === 0) {
            const steal = Math.min(4, other.coins);
            other.coins -= steal;
            totalStolen += steal;
          }
        });
        player.coins += totalStolen;
        this.addLog(`🧲 ${player.name} Altın Mıknatısı kullandı ve toplam ${totalStolen} altın topladı!`);
        break;

      case 'banana':
        this.trapsOnBoard[player.position] = player.id;
        this.addLog(`🍌 ${player.name} ${player.position}. karoya muz kabuğu tuzağı bıraktı!`);
        break;

      case 'swap_wand': {
        const tempPos = player.position;
        player.position = swapTarget.position;
        swapTarget.position = tempPos;
        this.addLog(`🪄 ${player.name} Takas Asası ile ${swapTarget.name} ile konumlarını değiştirdi!`);
        break;
      }

      case 'ghost_cloak':
        player.ghostCloakActive = 2;
        this.addLog(`👻 ${player.name} Hayalet Pelerin kuşandı! 2 tur boyunca cezalardan ve tuzaklardan muaf!`);
        break;

      case 'lucky_die':
        const fixedVal = this.constructor.cleanInt(targetPlayerId, 1, 6, 6);
        player.fixedRollNext = fixedVal;
        this.addLog(`🎯 ${player.name} Şanslı Altın Zar ayarladı: Sıradaki zarı garanti [${fixedVal}] gelecek!`);
        break;
    }

    this.broadcastState();
    return null;
  }

  finishTurn() {
    if (this.destroyed) return;
    // Invalidate every timer that belongs to the turn that is ending
    this.turnSerial++;
    this.shopOpenFor = null;
    this.isProcessingTurn = false;
    const current = this.getCurrentPlayer();
    if (current) {
      if (current.shieldActive > 0) current.shieldActive--;
      if (current.ghostCloakActive > 0) current.ghostCloakActive--;
      if (current.frozenTurns > 0) current.frozenTurns--;
    }
    if (this.state !== 'playing') return;

    this.turnIndex++;
    if (this.turnIndex >= this.players.length) {
      // Round Complete!
      this.turnIndex = 0;
      this.currentRound++;

      if (this.currentRound > this.totalRounds) {
        this.endGame();
        return;
      }

      // Trigger End-of-Round Mini Game!
      this.startRoundMiniGame();
      return;
    }

    this.broadcastState();
    this.checkNextTurnAction();
  }

  handleTradeOffer(fromSessionId, offer) {
    if (!offer || typeof offer !== 'object') return;
    if (this.state !== 'playing') return;
    const sender = this.players.find(p => p.sessionId === fromSessionId);
    const receiver = this.players.find(p => p.id === offer.toPlayerId);
    if (!sender || !receiver || sender.id === receiver.id) return;

    // Only non-negative whole coin amounts and real item ids are tradable
    const offerCoins = this.constructor.cleanInt(offer.offerCoins, 0, 9999, 0);
    const requestCoins = this.constructor.cleanInt(offer.requestCoins, 0, 9999, 0);
    const isItem = (id) => typeof id === 'string' && SHOP_ITEMS.some(i => i.id === id);
    const offerItem = isItem(offer.offerItem) ? offer.offerItem : null;
    const requestItem = isItem(offer.requestItem) ? offer.requestItem : null;
    if (!offerCoins && !requestCoins && !offerItem && !requestItem) return;

    if (sender.coins < offerCoins) return;
    if (offerItem && !sender.inventory.includes(offerItem)) return;

    // Drop any older offer between the same two players so pending offers can't pile up
    Object.keys(this.pendingTrades).forEach(id => {
      const t = this.pendingTrades[id];
      if (t.senderId === sender.id && t.receiverId === receiver.id) delete this.pendingTrades[id];
    });

    const tradeId = 'tr_' + Math.random().toString(36).substr(2, 10);
    this.pendingTrades[tradeId] = {
      id: tradeId,
      senderId: sender.id,
      receiverId: receiver.id,
      offerCoins,
      offerItem,
      requestCoins,
      requestItem
    };
    offer = { offerItem, requestItem };

    if (receiver.isBot) {
      // Bot evaluates trade offer
      setTimeout(() => {
        const valOffer = offerCoins + (offer.offerItem ? 8 : 0);
        const valReq = requestCoins + (offer.requestItem ? 8 : 0);
        const accepts = (valOffer >= valReq && receiver.coins >= requestCoins);
        this.resolveTrade(tradeId, accepts);
      }, 1300);
    } else {
      if (receiver.ws && receiver.ws.readyState === WebSocket.OPEN) {
        receiver.ws.send(JSON.stringify({
          type: 'incoming_trade_offer',
          tradeId,
          senderName: sender.name,
          senderAvatar: sender.avatar,
          offerCoins,
          offerItem: offer.offerItem,
          requestCoins,
          requestItem: offer.requestItem
        }));
      }
    }
  }

  // responderSessionId: the session answering the offer. Only the receiver may answer;
  // internal callers (bot decisions) pass null.
  resolveTrade(tradeId, accepted, responderSessionId = null) {
    const trade = this.pendingTrades[tradeId];
    if (!trade) return;

    const sender = this.players.find(p => p.id === trade.senderId);
    const receiver = this.players.find(p => p.id === trade.receiverId);
    if (responderSessionId !== null && (!receiver || receiver.sessionId !== responderSessionId)) return;
    delete this.pendingTrades[tradeId];
    accepted = accepted === true;
    if (!sender || !receiver) return;

    if (!accepted) {
      this.addLog(`❌ ${receiver.name}, ${sender.name}'ın takas teklifini reddetti.`);
      this.broadcast('trade_rejected', { senderName: sender.name, receiverName: receiver.name });
      return;
    }

    // Verify balances
    if (sender.coins < trade.offerCoins || receiver.coins < trade.requestCoins) return;
    if (trade.offerItem && !sender.inventory.includes(trade.offerItem)) return;
    if (trade.requestItem && !receiver.inventory.includes(trade.requestItem)) return;

    // Execute swap
    sender.coins -= trade.offerCoins;
    receiver.coins += trade.offerCoins;
    receiver.coins -= trade.requestCoins;
    sender.coins += trade.requestCoins;

    if (trade.offerItem) {
      const idx = sender.inventory.indexOf(trade.offerItem);
      if (idx !== -1) sender.inventory.splice(idx, 1);
      receiver.inventory.push(trade.offerItem);
    }
    if (trade.requestItem) {
      const idx = receiver.inventory.indexOf(trade.requestItem);
      if (idx !== -1) receiver.inventory.splice(idx, 1);
      sender.inventory.push(trade.requestItem);
    }

    this.addLog(`🤝 TAKAS GERÇEKLEŞTİ! ${sender.name} ile ${receiver.name} başarıyla takas yaptı!`);
    this.broadcast('trade_completed', {
      senderName: sender.name,
      receiverName: receiver.name
    });
    this.broadcastState();
  }

  sendQuickChat(sessionId, text) {
    const player = this.players.find(p => p.sessionId === sessionId);
    if (!player) return;
    const cleanText = this.constructor.cleanText(text, 80, '');
    if (!cleanText) return;

    this.broadcast('player_chat', {
      playerId: player.id,
      playerName: player.name,
      text: cleanText
    });
  }

  startRoundMiniGame() {
    this.state = 'minigame';
    const games = [
      { id: 'coin_catch', title: 'Gökten Altın Yağmuru', desc: 'Düşen altınları sepete topla, bombalardan kaç!', duration: 15 },
      { id: 'reflex_pop', title: 'Hızlı Parmak / Refleks', desc: 'Yeşil sinyal geldiğinde ilk tıklayan kazanır!', duration: 10 },
      { id: 'balloon_mash', title: 'Lego Balon Şişirme', desc: 'En hızlı tıkla, balonunu ilk patlatan sen ol!', duration: 12 },
      { id: 'lucky_chests', title: 'Hazine Sandığı Kumarı', desc: '3 gizemli sandıktan birini seç! Ya büyük ödül ya da tuzak!', duration: 11 },
      { id: 'memory_matrix', title: 'Parti Hafızası & Hızlı Renkler', desc: 'Yanan renk dizisini hafızanda tut ve hatasız tekrarla!', duration: 14 },
      { id: 'timing_hammer', title: 'Güç Çekici & Hassas Zamanlama', desc: 'Tam ortadaki 100 puanlık altın zili tuttur!', duration: 12 }
    ];

    const chosenGame = games[Math.floor(Math.random() * games.length)];
    this.minigame = {
      ...chosenGame,
      scores: {},
      finished: false,
      stakes: {
        first: '+15 Altın & Bonus Eşya',
        second: '+8 Altın',
        third: '+3 Altın',
        last: '-5 Altın Ceza'
      }
    };

    this.players.forEach(p => {
      this.minigame.scores[p.id] = 0;
    });
    // Each player may report exactly one score per mini-game
    this.minigameSubmitted = new Set();

    this.addLog(`🎮 ${this.currentRound - 1}. Tur bitti! Mini-Oyun Başlıyor: "${chosenGame.title}"!`);

    this.broadcast('minigame_start', {
      minigame: this.minigame,
      round: this.currentRound - 1
    });

    // Simulate bot scores
    this.players.filter(p => p.isBot).forEach(bot => {
      setTimeout(() => {
        if (this.minigame && !this.minigame.finished) {
          const botScore = Math.floor(Math.random() * 22) + 10;
          this.minigame.scores[bot.id] = botScore;
          this.broadcast('minigame_score_update', { playerId: bot.id, score: botScore });
        }
      }, Math.random() * 5000 + 3000);
    });

    // Auto finish minigame after duration + 2s
    clearTimeout(this.minigameTimeout);
    this.minigameTimeout = setTimeout(() => {
      if (!this.destroyed) this.resolveMiniGame();
    }, (chosenGame.duration + 3) * 1000);
  }

  handleMiniGameScore(sessionId, score) {
    if (this.state !== 'minigame' || !this.minigame || this.minigame.finished) return;
    const player = this.players.find(p => p.sessionId === sessionId);
    if (!player || player.isBot) return;
    if (this.minigameSubmitted && this.minigameSubmitted.has(player.id)) return;
    if (this.minigameSubmitted) this.minigameSubmitted.add(player.id);

    // Scores are still client-reported; clamp them to the range the mini-games can produce
    score = this.constructor.cleanInt(score, 0, 150, 0);
    this.minigame.scores[player.id] = score;
    this.broadcast('minigame_score_update', { playerId: player.id, score: score });
  }

  resolveMiniGame() {
    if (this.state !== 'minigame' || !this.minigame || this.minigame.finished) return;
    this.minigame.finished = true;

    // Rank players by score
    const ranked = [...this.players].sort((a, b) => {
      const sa = this.minigame.scores[a.id] || 0;
      const sb = this.minigame.scores[b.id] || 0;
      return sb - sa;
    });

    let firstItemWon = null;
    // Award high-stakes bonuses & penalties
    if (ranked[0]) {
      ranked[0].coins += 15;
      const bonusItems = ['rocket', 'shield', 'double_dice'];
      firstItemWon = bonusItems[Math.floor(Math.random() * bonusItems.length)];
      if (!ranked[0].inventory) ranked[0].inventory = [];
      ranked[0].inventory.push(firstItemWon);
      this.addLog(`🏆 Mini-Oyun 1.si: ${ranked[0].name} (+15 Altın & 1x ${firstItemWon})!`);
    }
    if (ranked[1]) {
      ranked[1].coins += 8;
      this.addLog(`🥈 Mini-Oyun 2.si: ${ranked[1].name} (+8 Altın)!`);
    }
    if (ranked[2]) {
      ranked[2].coins += 3;
      this.addLog(`🥉 Mini-Oyun 3.sü: ${ranked[2].name} (+3 Altın)!`);
    }
    // Last place penalty (if at least 3 players)
    if (ranked.length >= 3) {
      const last = ranked[ranked.length - 1];
      const penalty = Math.min(5, last.coins);
      last.coins = Math.max(0, last.coins - penalty);
      this.addLog(`💀 Mini-Oyun Sonuncusu: ${last.name} (-${penalty} Altın Ceza)!`);
    }

    this.broadcast('minigame_end', {
      scores: this.minigame.scores,
      bonusItemWon: firstItemWon,
      ranked: ranked.map(p => ({
        id: p.id,
        name: p.name,
        avatar: p.avatar,
        score: this.minigame.scores[p.id] || 0,
        coins: p.coins
      }))
    });

    this.minigame = null;
    this.state = 'playing';

    setTimeout(() => {
      this.broadcastState();
      this.checkNextTurnAction();
    }, 1800);
  }

  endGame() {
    this.state = 'gameover';

    // Calculate final scores: 1 Star = 1000 pts, 1 Coin = 10 pts
    const leaderboard = [...this.players].map(p => ({
      id: p.id,
      sessionId: p.sessionId, // Critical for client XP attribution
      name: p.name,
      avatar: p.avatar,
      hat: p.hat,
      color: p.color,
      stars: p.stars,
      coins: p.coins,
      totalScore: (p.stars * 1000) + (p.coins * 10),
      xpEarned: Math.floor(150 + (p.stars * 80) + (p.coins * 2))
    })).sort((a, b) => b.totalScore - a.totalScore);

    this.addLog(`🎉 PARTİ TAMAMLANDI! Şampiyon: 👑 ${leaderboard[0].name}!`);

    this.broadcast('game_over', {
      leaderboard: leaderboard,
      winner: leaderboard[0]
    });
  }

  sendEmote(sessionId, emoteId) {
    const player = this.players.find(p => p.sessionId === sessionId);
    if (!player) return;
    emoteId = this.constructor.cleanSlug(emoteId, null);
    if (!emoteId) return;

    this.broadcast('player_emote', {
      playerId: player.id,
      playerName: player.name,
      emoteId
    });

    // Sometimes bots react too!
    if (Math.random() > 0.4) {
      const bots = this.players.filter(p => p.isBot);
      if (bots.length > 0) {
        const randomBot = bots[Math.floor(Math.random() * bots.length)];
        const botEmotes = ['laugh', 'cool', 'party', 'fire', 'shock'];
        setTimeout(() => {
          this.broadcast('player_emote', {
            playerId: randomBot.id,
            playerName: randomBot.name,
            emoteId: botEmotes[Math.floor(Math.random() * botEmotes.length)]
          });
        }, 1200);
      }
    }
  }
}

/* ==========================================================================
   WEBSOCKET CONNECTION ROUTING
   ========================================================================== */

const ROOM_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const ROOM_IDLE_TTL_MS = 10 * 60 * 1000; // drop rooms 10 min after the last human leaves
const MAX_ROOMS = 500;
const RATE_WINDOW_MS = 5000;
const RATE_MAX_MESSAGES = 40;
const ALLOWED_SPEEDS = [1, 2, 4];

// Room codes are always generated by the server so nobody can overwrite an existing room
function generateRoomCode() {
  for (let attempt = 0; attempt < 50; attempt++) {
    const bytes = crypto.randomBytes(6);
    let code = '';
    for (let i = 0; i < 6; i++) code += ROOM_CODE_ALPHABET[bytes[i] % ROOM_CODE_ALPHABET.length];
    if (!ROOMS.has(code)) return code;
  }
  return null;
}

function cleanSessionId(value) {
  return (typeof value === 'string' && /^[A-Za-z0-9_-]{8,64}$/.test(value)) ? value : null;
}

function newSessionId() {
  return 's_' + crypto.randomBytes(12).toString('hex');
}

// Never serialize the live socket (circular) or anything but the player's own data
function safePlayer(player) {
  if (!player) return null;
  const { ws: _ws, ...rest } = player;
  return rest;
}

function scheduleRoomCleanup(room) {
  clearTimeout(room.cleanupTimer);
  room.cleanupTimer = setTimeout(() => {
    if (!room.hasConnectedHumans()) {
      room.destroy();
      ROOMS.delete(room.id);
    }
  }, ROOM_IDLE_TTL_MS);
}

const ERROR_MESSAGES = {
  not_your_turn: 'Eşyaları yalnızca kendi sıranda, zar atmadan önce kullanabilirsin!',
  turn_in_progress: 'Hamlen devam ediyor, şu an eşya kullanamazsın!',
  frozen: 'Donmuş durumdasın, bu tur eşya kullanamazsın!',
  no_item: 'Bu eşya çantanda yok!',
  no_target: 'Takas edilecek rakip bulunamadı!',
  shop_closed: 'Dükkân şu anda senin için açık değil!',
  no_coins: 'Yeterli altının yok!',
  invalid: 'Geçersiz istek!'
};

wss.on('connection', (ws) => {
  let boundSessionId = null;
  let boundRoomId = null;
  let rateWindowStart = Date.now();
  let rateCount = 0;

  const send = (payload) => {
    if (ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify(payload));
  };
  const sendError = (code) => send({ type: 'error', message: ERROR_MESSAGES[code] || code });
  const getRoom = () => (boundRoomId ? ROOMS.get(boundRoomId) || null : null);

  // Release this socket's seat (lobby: leave, in-game: a bot takes over)
  const detach = () => {
    const room = getRoom();
    if (room && boundSessionId) {
      const player = room.players.find(p => p.sessionId === boundSessionId);
      // Only act if this socket still owns the seat (a newer tab may have reconnected)
      if (player && player.ws === ws) {
        room.handleDisconnect(boundSessionId);
        room.broadcastState();
      }
      if (!room.hasConnectedHumans()) scheduleRoomCleanup(room);
    }
    boundRoomId = null;
  };

  ws.on('message', (raw) => {
    const now = Date.now();
    if (now - rateWindowStart > RATE_WINDOW_MS) {
      rateWindowStart = now;
      rateCount = 0;
    }
    if (++rateCount > RATE_MAX_MESSAGES) return; // flood protection

    let data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      return;
    }
    if (!data || typeof data !== 'object' || typeof data.type !== 'string') return;

    try {
      const room = getRoom();
      const isHost = !!room && room.isHost(boundSessionId);

      switch (data.type) {
        case 'create_room': {
          if (ROOMS.size >= MAX_ROOMS) {
            sendError('Sunucu şu anda dolu, lütfen biraz sonra tekrar dene.');
            return;
          }
          const roomId = generateRoomCode();
          if (!roomId) {
            sendError('Oda kodu üretilemedi, tekrar dene.');
            return;
          }
          detach();
          const sessionId = cleanSessionId(data.sessionId) || newSessionId();
          const newRoom = new Room(roomId, sessionId, data.playerName, {
            totalRounds: data.rounds,
            mapTheme: data.mapTheme
          });
          ROOMS.set(roomId, newRoom);

          const player = newRoom.addPlayer(sessionId, data.playerName, data.avatar, data.diceSkin,
            data.level, data.title, ws, data.hat, data.color);
          boundSessionId = sessionId;
          boundRoomId = roomId;

          send({ type: 'room_created', roomId, sessionId, player: safePlayer(player) });
          newRoom.broadcastState();
          break;
        }

        case 'join_room': {
          const roomId = String(data.roomId || '').trim().toUpperCase().slice(0, 12);
          const target = ROOMS.get(roomId);
          if (!target || target.destroyed) {
            sendError(`"${roomId.replace(/[^A-Z0-9]/g, '')}" kodlu oda bulunamadı!`);
            return;
          }

          const sessionId = cleanSessionId(data.sessionId) || newSessionId();
          const returning = target.players.some(p => p.sessionId === sessionId);
          if (!returning && target.state !== 'lobby') {
            sendError('Bu odada oyun başladı, yeni oyuncu katılamaz!');
            return;
          }

          if (boundRoomId !== roomId || boundSessionId !== sessionId) detach();
          const player = target.addPlayer(sessionId, data.playerName, data.avatar, data.diceSkin,
            data.level, data.title, ws, data.hat, data.color);
          if (!player) {
            sendError('Oda dolu (Maksimum 6 oyuncu)!');
            return;
          }
          clearTimeout(target.cleanupTimer);
          boundSessionId = sessionId;
          boundRoomId = roomId;

          send({ type: 'room_joined', roomId, sessionId, player: safePlayer(player) });
          target.broadcastState();
          // A returning player whose turn it is needs to see the turn prompt again
          if (target.state === 'playing') target.checkNextTurnAction();
          break;
        }

        case 'update_profile': {
          if (room) {
            room.updatePlayerProfile(boundSessionId, data);
            room.broadcastState();
          }
          break;
        }

        case 'add_bot': {
          if (room && isHost && room.state === 'lobby') {
            room.addBot();
            room.broadcastState();
          }
          break;
        }

        case 'start_game': {
          if (room && isHost && room.state === 'lobby') {
            room.startGame();
          }
          break;
        }

        case 'roll_dice': {
          if (room) room.rollDice(boundSessionId);
          break;
        }

        case 'set_game_speed': {
          const speed = parseInt(data.speed, 10);
          if (room && isHost && ALLOWED_SPEEDS.includes(speed)) {
            room.gameSpeed = speed;
          }
          break;
        }

        case 'buy_item': {
          if (!room) break;
          const err = room.buyItem(boundSessionId, data.itemId);
          if (err) sendError(err);
          break;
        }

        case 'use_item': {
          if (!room) break;
          const err = room.useItem(boundSessionId, data.itemId, data.targetPlayerId);
          if (err) sendError(err);
          break;
        }

        case 'close_shop': {
          if (room) room.closeShop(boundSessionId);
          break;
        }

        case 'minigame_score': {
          if (room) room.handleMiniGameScore(boundSessionId, data.score);
          break;
        }

        case 'send_emote': {
          if (room) room.sendEmote(boundSessionId, data.emoteId);
          break;
        }

        case 'switch_map': {
          if (room && isHost && room.state === 'lobby') {
            room.mapTheme = Room.normalizeTheme(data.mapTheme);
            room.board = generateBoardForTheme(room.mapTheme);
            room.addLog(`🗺️ Harita seçildi: ${room.mapTheme.toUpperCase()}`);
            room.broadcastState();
          }
          break;
        }

        case 'send_trade_offer': {
          if (room) room.handleTradeOffer(boundSessionId, data.offer);
          break;
        }

        case 'respond_trade_offer': {
          if (room && typeof data.tradeId === 'string') {
            room.resolveTrade(data.tradeId, data.accepted === true, boundSessionId);
          }
          break;
        }

        case 'send_quick_chat': {
          if (room) room.sendQuickChat(boundSessionId, data.text);
          break;
        }

        case 'leave_room': {
          detach();
          break;
        }
      }
    } catch (err) {
      console.error('WS Message Error:', err);
    }
  });

  ws.on('close', () => {
    detach();
  });
});

// Start listening on 0.0.0.0 (all interfaces so LAN works!)
server.listen(PORT, '0.0.0.0', () => {
  const localIps = getLocalIpAddresses();
  console.log(`====================================================`);
  console.log(`🎉 PARTİ ADASI: WONDER BOARD SUNUCUSU BAŞLATILDI!`);
  console.log(`💻 Yerel Erişim:  http://localhost:${PORT}`);
  if (localIps.length > 0) {
    localIps.forEach(ip => {
      console.log(`📱 Aynı Ağdan / Mobilden: http://${ip}:${PORT}`);
    });
  }
  console.log(`🌐 İnternete yüklediğinde tüm dünya arkadaşlarınla oynayabilir.`);
  console.log(`====================================================`);
});
