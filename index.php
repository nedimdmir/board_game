<?php
/**
 * PartyCraft: Wonder Board - All-In-One Edition
 * 
 * 🎮 TEK DOSYA SUNUCU + OYUN SÜRÜMÜ (All-in-One Single File Edition)
 * Bu tek dosyayı (index.php) PHP destekleyen herhangi bir web hostinge yükleyip hemen oynayabilirsiniz.
 * Harici veritabanı, npm, composer veya ek dosya gerekmez!
 * 
 * - Tarayıcıdan açıldığında: Tam HTML5/CSS/Vanilla JS oyun arayüzünü sunar.
 * - Çok oyunculu oda, zar, hamle ve mini oyun isteklerinde: JSON dosya tabanlı PHP arka ucunu çalıştırır.
 */

$isApiRequest = (
    isset($_GET['action']) || 
    isset($_POST['action']) || 
    (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') || 
    (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS')
);

if ($isApiRequest) {
/**
 * PartyCraft: Wonder Board - Lightweight Multi-Player PHP Backend
 * 
 * Zero external libraries, zero database required.
 * Stores room state in local JSON files inside 'rooms/' with atomic file locking.
 * Supports up to 4 players, automatic bot substitution, reconnection, and authoritative game state.
 */

// Error handling & headers
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
// The game client always calls this API from the same origin, so no CORS headers are sent:
// other websites must not be able to drive rooms on behalf of visitors.
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ensure data storage directory exists and is secured
$roomsDir = __DIR__ . DIRECTORY_SEPARATOR . 'rooms';
if (!is_dir($roomsDir)) {
    @mkdir($roomsDir, 0755, true);
    // Defense in depth for Apache. Room files themselves are stored as *.json.php with an
    // exit guard, so they stay unreadable on Nginx/IIS/LiteSpeed too (see ROOM_FILE_GUARD).
    @file_put_contents($roomsDir . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($roomsDir . DIRECTORY_SEPARATOR . 'index.html', "<!-- Protected -->");
}

$storageWritable = is_writable($roomsDir);

// Helper: Parse JSON input (bodies larger than 16 KB are ignored)
function getRequestData() {
    $raw = file_get_contents('php://input', false, null, 0, 16384);
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return $_REQUEST;
}

// Helper: Response helper
function sendJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($msg, $code = 400) {
    sendJson(['type' => 'error', 'message' => $msg], $code);
}

// Garbage Collector: Remove rooms older than 3 hours (5% probability)
if (mt_rand(1, 20) === 1 && is_dir($roomsDir)) {
    $now = time();
    $files = array_merge(
        @glob($roomsDir . DIRECTORY_SEPARATOR . '*.json.php') ?: [],
        @glob($roomsDir . DIRECTORY_SEPARATOR . '*.json') ?: [] // legacy unguarded files
    );
    if ($files) {
        foreach ($files as $f) {
            if ($now - @filemtime($f) > 10800) { // 3 hours
                @unlink($f);
            }
        }
    }
}

// Board Tiles Generator
function generateBoardForTheme($theme = 'istanbul') {
    if ($theme === 'cyber') $theme = 'tokyo';
    if ($theme === 'candy') $theme = 'paris';
    if ($theme === 'tropics') $theme = 'istanbul';

    if ($theme === 'tokyo') {
        return [
            ['id' => 0, 'type' => 'start', 'title' => 'Shibuya Meydanı', 'desc' => '+10 Altın Başlangıç Bonusu!', 'icon' => 'flag', 'color' => '#10B981'],
            ['id' => 1, 'type' => 'coin', 'amount' => 4, 'title' => 'Akihabara Tech', 'desc' => '+4 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 2, 'type' => 'mystery', 'title' => 'Gacha Kapsülü', 'desc' => 'Gizemli anime sürprizi!', 'icon' => 'sparkles', 'color' => '#EC4899'],
            ['id' => 3, 'type' => 'coin', 'amount' => 5, 'title' => 'Shinjuku Neon', 'desc' => '+5 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 4, 'type' => 'freeze', 'title' => 'EMP Siber Şok', 'desc' => 'Sistem dondu! 1 tur eşya kullanamazsın!', 'icon' => 'zap', 'color' => '#0284C7'],
            ['id' => 5, 'type' => 'shop', 'title' => 'Harajuku Market', 'desc' => 'Fütüristik eşyalar satın al!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 6, 'type' => 'coin', 'amount' => 6, 'title' => 'Ginza Lüksü', 'desc' => '+6 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 7, 'type' => 'star', 'title' => 'Tokyo Skytree', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 8, 'type' => 'warp', 'target' => 20, 'title' => 'Shinkansen Hızlı Tren', 'desc' => '20. karoya ışınlan!', 'icon' => 'zap', 'color' => '#06B6D4'],
            ['id' => 9, 'type' => 'coin', 'amount' => 4, 'title' => 'Roppongi Gece', 'desc' => '+4 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 10, 'type' => 'jail', 'title' => 'Siber Kodes', 'desc' => '1 tur kilitli kal veya 5 altın kefalet öde!', 'icon' => 'shield', 'color' => '#DC2626'],
            ['id' => 11, 'type' => 'trap', 'penalty' => 5, 'title' => 'Neon Aşırı Yükleme', 'desc' => '-5 Altın hasarı!', 'icon' => 'flame', 'color' => '#EF4444'],
            ['id' => 12, 'type' => 'mystery', 'title' => 'Şans Kedisi Maneki', 'desc' => 'Şans ruletini çevir!', 'icon' => 'sparkles', 'color' => '#EC4899'],
            ['id' => 13, 'type' => 'bank', 'title' => 'Tokyo Merkez Bankası', 'desc' => 'Biriken havuz altınlarını topla!', 'icon' => 'coins', 'color' => '#10B981'],
            ['id' => 14, 'type' => 'shop', 'title' => 'Akiba Dükkanı', 'desc' => 'Siber eşyalar!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 15, 'type' => 'star', 'title' => 'Tokyo Kulesi', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 16, 'type' => 'coin', 'amount' => 4, 'title' => 'Ueno Parkı', 'desc' => '+4 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 17, 'type' => 'slip', 'steps' => -3, 'title' => 'Islak Ramen Zemini', 'desc' => '3 adım geri kaydın!', 'icon' => 'undo-2', 'color' => '#EAB308'],
            ['id' => 18, 'type' => 'mystery', 'title' => 'Torii Çarkı', 'desc' => 'Kutsal tapınak ödülü!', 'icon' => 'sparkles', 'color' => '#EC4899'],
            ['id' => 19, 'type' => 'coin', 'amount' => 5, 'title' => 'Asakusa Feneri', 'desc' => '+5 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 20, 'type' => 'duel', 'title' => 'Samuray Düellosu', 'desc' => 'Rakipten altın kap!', 'icon' => 'swords', 'color' => '#EC4899'],
            ['id' => 21, 'type' => 'reverse', 'title' => 'Zaman Bükülmesi', 'desc' => 'Sonraki zarda ters yöne yürü!', 'icon' => 'undo-2', 'color' => '#9333EA'],
            ['id' => 22, 'type' => 'coin', 'amount' => 6, 'title' => 'Tsukiji Balık Pazarı', 'desc' => '+6 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 23, 'type' => 'star', 'title' => 'Fuji Dağı Kaidesi', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 24, 'type' => 'tax', 'title' => 'Tokyo Belediye Vergisi', 'desc' => '%25 Altın cezası en fakir oyuncuya gider!', 'icon' => 'coins', 'color' => '#E11D48'],
            ['id' => 25, 'type' => 'mystery', 'title' => 'Sakura Sandığı', 'desc' => 'Şansını sına!', 'icon' => 'sparkles', 'color' => '#EC4899'],
            ['id' => 26, 'type' => 'shop', 'title' => 'Shibuya Alışveriş', 'desc' => 'Nadir taktik eşyaları!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 27, 'type' => 'coin', 'amount' => 8, 'title' => 'İmparatorluk Sarayı', 'desc' => '+8 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 28, 'type' => 'trap', 'penalty' => 5, 'title' => 'Yakuza Pususu', 'desc' => '-5 Altın kaybettin!', 'icon' => 'flame', 'color' => '#EF4444'],
            ['id' => 29, 'type' => 'duel', 'title' => 'Sumo Arenası', 'desc' => 'Liderden altın arakla!', 'icon' => 'swords', 'color' => '#EC4899'],
            ['id' => 30, 'type' => 'coin', 'amount' => 5, 'title' => 'Omotesando Caddesi', 'desc' => '+5 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 31, 'type' => 'star', 'title' => 'Meiji Tapınağı', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24']
        ];
    }

    if ($theme === 'paris') {
        return [
            ['id' => 0, 'type' => 'start', 'title' => 'Eyfel Kulesi', 'desc' => '+10 Altın Başlangıç Bonusu!', 'icon' => 'flag', 'color' => '#10B981'],
            ['id' => 1, 'type' => 'coin', 'amount' => 4, 'title' => 'Champs-Élysées', 'desc' => '+4 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 2, 'type' => 'mystery', 'title' => 'Louvre Tablosu', 'desc' => 'Mona Lisa gülümsedi!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
            ['id' => 3, 'type' => 'coin', 'amount' => 5, 'title' => 'Kruvasan Fırını', 'desc' => '+5 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 4, 'type' => 'trap', 'penalty' => 4, 'title' => 'Parke Taşı Çukuru', 'desc' => '-4 Altın kaybettin!', 'icon' => 'flame', 'color' => '#EF4444'],
            ['id' => 5, 'type' => 'shop', 'title' => 'Fransız Butiği', 'desc' => 'Lüks parti eşyaları al!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 6, 'type' => 'coin', 'amount' => 6, 'title' => 'Notre-Dame Avlusu', 'desc' => '+6 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 7, 'type' => 'star', 'title' => 'Zafer Takı', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 8, 'type' => 'warp', 'target' => 18, 'title' => 'Sen Nehri Teknesi', 'desc' => '18. karoya yüz!', 'icon' => 'zap', 'color' => '#06B6D4'],
            ['id' => 9, 'type' => 'coin', 'amount' => 4, 'title' => 'Montmartre Tepesi', 'desc' => '+4 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 10, 'type' => 'jail', 'title' => 'Bastille Kalesi', 'desc' => '1 tur kilitli kal veya 5 altın kefalet öde!', 'icon' => 'shield', 'color' => '#DC2626'],
            ['id' => 11, 'type' => 'tax', 'title' => 'Lüks Moda Vergisi', 'desc' => '%25 Altın cezası en fakir oyuncuya dağıtılır!', 'icon' => 'coins', 'color' => '#E11D48'],
            ['id' => 12, 'type' => 'mystery', 'title' => 'Cam Piramit', 'desc' => 'Rulet çarkını çevir!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
            ['id' => 13, 'type' => 'bank', 'title' => 'Banque de France', 'desc' => 'Kasadaki tüm altınları süpür!', 'icon' => 'coins', 'color' => '#10B981'],
            ['id' => 14, 'type' => 'shop', 'title' => 'Parfüm Marketi', 'desc' => 'Taktik eşyaları!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 15, 'type' => 'star', 'title' => 'Sacre-Coeur', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 16, 'type' => 'coin', 'amount' => 4, 'title' => 'Latin Mahallesi', 'desc' => '+4 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 17, 'type' => 'slip', 'steps' => -3, 'title' => 'Eriyen Dondurma', 'desc' => '3 adım geri kaydın!', 'icon' => 'undo-2', 'color' => '#EAB308'],
            ['id' => 18, 'type' => 'mystery', 'title' => 'Moulin Rouge Çarkı', 'desc' => 'Büyük ödüller!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
            ['id' => 19, 'type' => 'coin', 'amount' => 5, 'title' => 'Lüksemburg Bahçesi', 'desc' => '+5 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 20, 'type' => 'duel', 'title' => 'Eskrim Meydanı', 'desc' => 'Rakipten altın kap!', 'icon' => 'swords', 'color' => '#EC4899'],
            ['id' => 21, 'type' => 'reverse', 'title' => 'Sen Nehri Akıntısı', 'desc' => 'Sonraki zarda ters yöne yürü!', 'icon' => 'undo-2', 'color' => '#9333EA'],
            ['id' => 22, 'type' => 'coin', 'amount' => 6, 'title' => 'Opera Garnier', 'desc' => '+6 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 23, 'type' => 'star', 'title' => 'Pantheon Kaidesi', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 24, 'type' => 'freeze', 'title' => 'Paris Kar Fırtınası', 'desc' => '1 tur eşya kullanımın engellendi!', 'icon' => 'zap', 'color' => '#0284C7'],
            ['id' => 25, 'type' => 'mystery', 'title' => 'Sanatçı Sandığı', 'desc' => 'Şansını sına!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
            ['id' => 26, 'type' => 'shop', 'title' => 'Fransız Butiği', 'desc' => 'Eşya kuşan!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 27, 'type' => 'coin', 'amount' => 8, 'title' => 'Vendome Meydanı', 'desc' => '+8 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 28, 'type' => 'trap', 'penalty' => 5, 'title' => 'Metro Arızası', 'desc' => '-5 Altın!', 'icon' => 'flame', 'color' => '#EF4444'],
            ['id' => 29, 'type' => 'duel', 'title' => 'Şövalye Düellosu', 'desc' => 'Liderden altın arakla!', 'icon' => 'swords', 'color' => '#EC4899'],
            ['id' => 30, 'type' => 'coin', 'amount' => 5, 'title' => 'Saint-Germain', 'desc' => '+5 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 31, 'type' => 'star', 'title' => 'Louvre Sarayı', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24']
        ];
    }

    if ($theme === 'newyork') {
        return [
            ['id' => 0, 'type' => 'start', 'title' => 'Times Square', 'desc' => '+10 Altın Başlangıç Bonusu!', 'icon' => 'flag', 'color' => '#10B981'],
            ['id' => 1, 'type' => 'coin', 'amount' => 4, 'title' => 'Broadway Işıkları', 'desc' => '+4 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 2, 'type' => 'mystery', 'title' => 'Sarı Taksi Şansı', 'desc' => 'Sürpriz yolculuk!', 'icon' => 'sparkles', 'color' => '#F59E0B'],
            ['id' => 3, 'type' => 'coin', 'amount' => 5, 'title' => '5th Avenue Alışverişi', 'desc' => '+5 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 4, 'type' => 'trap', 'penalty' => 4, 'title' => 'Buhar Rogarı', 'desc' => '-4 Altın kaybettin!', 'icon' => 'flame', 'color' => '#EF4444'],
            ['id' => 5, 'type' => 'shop', 'title' => 'Manhattan Market', 'desc' => 'Güçlü eşyalar satın al!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 6, 'type' => 'coin', 'amount' => 6, 'title' => 'Wall Street Borsası', 'desc' => '+6 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 7, 'type' => 'star', 'title' => 'Empire State', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 8, 'type' => 'warp', 'target' => 20, 'title' => 'Subway Ekspres', 'desc' => '20. karoya fırla!', 'icon' => 'zap', 'color' => '#06B6D4'],
            ['id' => 9, 'type' => 'coin', 'amount' => 4, 'title' => 'SoHo Sanat', 'desc' => '+4 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 10, 'type' => 'jail', 'title' => 'Rikers Kodesi', 'desc' => '1 tur kilitli kal veya 5 altın kefalet öde!', 'icon' => 'shield', 'color' => '#DC2626'],
            ['id' => 11, 'type' => 'tax', 'title' => 'Wall Street Vergisi', 'desc' => '%25 Altın cezası en fakir oyuncuya dağıtılır!', 'icon' => 'coins', 'color' => '#E11D48'],
            ['id' => 12, 'type' => 'mystery', 'title' => 'Gizemli Çatı Katı', 'desc' => 'Rulet çarkını çevir!', 'icon' => 'sparkles', 'color' => '#F59E0B'],
            ['id' => 13, 'type' => 'bank', 'title' => 'Federal Reserve Bankası', 'desc' => 'Kasadaki tüm altınları topla!', 'icon' => 'coins', 'color' => '#10B981'],
            ['id' => 14, 'type' => 'shop', 'title' => 'Brooklyn Deposu', 'desc' => 'Taktik eşyaları!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 15, 'type' => 'star', 'title' => 'Özgürlük Heykeli', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 16, 'type' => 'coin', 'amount' => 4, 'title' => 'Central Park', 'desc' => '+4 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 17, 'type' => 'slip', 'steps' => -3, 'title' => 'Muzlu Donut Zemini', 'desc' => '3 adım geri kaydın!', 'icon' => 'undo-2', 'color' => '#EAB308'],
            ['id' => 18, 'type' => 'mystery', 'title' => 'Grand Central Saati', 'desc' => 'Büyük ödüller!', 'icon' => 'sparkles', 'color' => '#F59E0B'],
            ['id' => 19, 'type' => 'coin', 'amount' => 5, 'title' => 'DUMBO Manzarası', 'desc' => '+5 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 20, 'type' => 'duel', 'title' => 'Boks Ringi', 'desc' => 'Rakipten altın kap!', 'icon' => 'swords', 'color' => '#EC4899'],
            ['id' => 21, 'type' => 'freeze', 'title' => 'Blizzard Kar Fırtınası', 'desc' => '1 tur eşya kullanımın engellendi!', 'icon' => 'zap', 'color' => '#0284C7'],
            ['id' => 22, 'type' => 'coin', 'amount' => 6, 'title' => 'Chrysler Binası', 'desc' => '+6 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 23, 'type' => 'star', 'title' => 'Brooklyn Köprüsü', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
            ['id' => 24, 'type' => 'reverse', 'title' => 'Trafik Tıkanıklığı', 'desc' => 'Ters yöne gitmek zorundasın!', 'icon' => 'undo-2', 'color' => '#9333EA'],
            ['id' => 25, 'type' => 'mystery', 'title' => 'Broadway Sandığı', 'desc' => 'Şansını sına!', 'icon' => 'sparkles', 'color' => '#F59E0B'],
            ['id' => 26, 'type' => 'shop', 'title' => 'Manhattan Market', 'desc' => 'Eşya kuşan!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
            ['id' => 27, 'type' => 'coin', 'amount' => 8, 'title' => 'Federal Reserve Altını', 'desc' => '+8 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 28, 'type' => 'trap', 'penalty' => 5, 'title' => 'Park Cezası', 'desc' => '-5 Altın!', 'icon' => 'flame', 'color' => '#EF4444'],
            ['id' => 29, 'type' => 'duel', 'title' => 'Wall Street Düellosu', 'desc' => 'Liderden altın arakla!', 'icon' => 'swords', 'color' => '#EC4899'],
            ['id' => 30, 'type' => 'coin', 'amount' => 5, 'title' => 'Greenwich Village', 'desc' => '+5 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
            ['id' => 31, 'type' => 'star', 'title' => 'One World Observatory', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24']
        ];
    }

    // Default: 'istanbul'
    return [
        ['id' => 0, 'type' => 'start', 'title' => 'Eminönü İskelesi', 'desc' => '+10 Altın Başlangıç Bonusu!', 'icon' => 'flag', 'color' => '#10B981'],
        ['id' => 1, 'type' => 'coin', 'amount' => 4, 'title' => 'Mısır Çarşısı', 'desc' => '+4 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 2, 'type' => 'mystery', 'title' => 'Kapalıçarşı Sandığı', 'desc' => 'Tarihi bir hazine sürprizi!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
        ['id' => 3, 'type' => 'coin', 'amount' => 5, 'title' => 'Sultanahmet Meydanı', 'desc' => '+5 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 4, 'type' => 'trap', 'penalty' => 4, 'title' => 'Tarihi Arnavutkaldırımı', 'desc' => '-4 Altın düşürdün!', 'icon' => 'flame', 'color' => '#EF4444'],
        ['id' => 5, 'type' => 'shop', 'title' => 'Kapalıçarşı Bedesteni', 'desc' => 'Güçlü eşyalar satın al!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
        ['id' => 6, 'type' => 'coin', 'amount' => 6, 'title' => 'Karaköy Rıhtımı', 'desc' => '+6 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 7, 'type' => 'star', 'title' => 'Galata Kulesi', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
        ['id' => 8, 'type' => 'warp', 'target' => 16, 'title' => 'Şehir Hatları Vapuru', 'desc' => '16. karoya yüz!', 'icon' => 'zap', 'color' => '#06B6D4'],
        ['id' => 9, 'type' => 'coin', 'amount' => 4, 'title' => 'İstiklal Caddesi', 'desc' => '+4 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 10, 'type' => 'duel', 'title' => 'Kırkpınar Er Meydanı', 'desc' => 'Rakipten altın kap!', 'icon' => 'swords', 'color' => '#EC4899'],
        ['id' => 11, 'type' => 'tax', 'title' => 'Boğaz Köprüsü Gişesi', 'desc' => '%25 Altın cezası en fakir oyuncuya dağıtılır!', 'icon' => 'coins', 'color' => '#E11D48'],
        ['id' => 12, 'type' => 'mystery', 'title' => 'Yerebatan Sarnıcı', 'desc' => 'Rulet çarkını çevir!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
        ['id' => 13, 'type' => 'bank', 'title' => 'Topkapı Hazine Kasası', 'desc' => 'Kasadaki tüm altınları tek seferde kap!', 'icon' => 'coins', 'color' => '#10B981'],
        ['id' => 14, 'type' => 'shop', 'title' => 'Tahtakale Pazarı', 'desc' => 'Nadir taktik eşyaları!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
        ['id' => 15, 'type' => 'star', 'title' => 'Kız Kulesi Kaidesi', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
        ['id' => 16, 'type' => 'coin', 'amount' => 4, 'title' => 'Üsküdar Sahili', 'desc' => '+4 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 17, 'type' => 'jail', 'title' => 'Yedikule Zindanları', 'desc' => '1 tur kilitli kal veya 5 altın kefalet öde!', 'icon' => 'shield', 'color' => '#DC2626'],
        ['id' => 18, 'type' => 'mystery', 'title' => 'Çamlıca Tepesi', 'desc' => 'Büyük ödüller veya sürprizler!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
        ['id' => 19, 'type' => 'coin', 'amount' => 5, 'title' => 'Kadıköy Boğa Meydanı', 'desc' => '+5 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 20, 'type' => 'duel', 'title' => 'Boğaziçi Arenası', 'desc' => 'Rakiplerle düello!', 'icon' => 'swords', 'color' => '#EC4899'],
        ['id' => 21, 'type' => 'freeze', 'title' => 'Karadeniz Kar Fırtınası', 'desc' => 'Buz fırtınası! 1 tur eşya kullanamazsın!', 'icon' => 'zap', 'color' => '#0284C7'],
        ['id' => 22, 'type' => 'coin', 'amount' => 6, 'title' => 'Dolmabahçe Sarayı', 'desc' => '+6 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 23, 'type' => 'star', 'title' => 'Ortaköy Camii Kaidesi', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24'],
        ['id' => 24, 'type' => 'reverse', 'title' => 'Boğaz Ters Akıntısı', 'desc' => 'Pusula bozuldu! Sonraki zarda geriye yürüyeceksin!', 'icon' => 'undo-2', 'color' => '#9333EA'],
        ['id' => 25, 'type' => 'mystery', 'title' => 'Balat Renkli Sandık', 'desc' => 'Şansını sına!', 'icon' => 'sparkles', 'color' => '#8B5CF6'],
        ['id' => 26, 'type' => 'shop', 'title' => 'Beşiktaş Çarşısı', 'desc' => 'Zafer için eşya kuşan!', 'icon' => 'shopping-bag', 'color' => '#F59E0B'],
        ['id' => 27, 'type' => 'coin', 'amount' => 8, 'title' => 'Boğaziçi Yalıları', 'desc' => '+8 Altın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 28, 'type' => 'trap', 'penalty' => 4, 'title' => 'Trafik Yoğunluğu', 'desc' => '-4 Altın kaybettin!', 'icon' => 'flame', 'color' => '#EF4444'],
        ['id' => 29, 'type' => 'duel', 'title' => 'Haliç Hesaplaşması', 'desc' => 'Liderden altın arakla!', 'icon' => 'swords', 'color' => '#EC4899'],
        ['id' => 30, 'type' => 'coin', 'amount' => 5, 'title' => 'Taksim Meydanı', 'desc' => '+5 Altın kazandın!', 'icon' => 'coins', 'color' => '#3B82F6'],
        ['id' => 31, 'type' => 'star', 'title' => 'Ayasofya Kaidesi', 'desc' => '20 Altına 1 Altın Yıldız!', 'icon' => 'star', 'color' => '#FBBF24']
    ];
}

$BOT_TEMPLATES = [
    ['name' => 'Lego Usta', 'avatar' => 'builder', 'title' => 'Mimar', 'color' => '#3B82F6'],
    ['name' => 'Yeşil Dino', 'avatar' => 'dino', 'title' => 'Parti Canavarı', 'color' => '#10B981'],
    ['name' => 'Siber Kedi', 'avatar' => 'cat', 'title' => 'Hızlı Patiler', 'color' => '#EC4899'],
    ['name' => 'Gandi Bot', 'avatar' => 'wizard', 'title' => 'Büyücü', 'color' => '#8B5CF6'],
    ['name' => 'Robo-X', 'avatar' => 'robot', 'title' => 'Hesap Uzmanı', 'color' => '#06B6D4'],
    ['name' => 'Dedektif', 'avatar' => 'detective', 'title' => 'Gizem Avcısı', 'color' => '#F59E0B']
];

$SHOP_ITEMS = [
    ['id' => 'double_dice', 'name' => 'Çift Zar', 'cost' => 7, 'desc' => 'Bir sonraki turda 2 zar birden atarsın (2-12 adım).', 'icon' => 'dices'],
    ['id' => 'rocket', 'name' => 'Süper Roket', 'cost' => 12, 'desc' => 'Hedefine doğru direkt 7 adım uçar!', 'icon' => 'rocket'],
    ['id' => 'shield', 'name' => 'Manyetik Kalkan', 'cost' => 8, 'desc' => 'Tuzaklara ve altın çalma hamlelerine karşı 2 tur korur.', 'icon' => 'shield'],
    ['id' => 'magnet', 'name' => 'Altın Mıknatısı', 'cost' => 10, 'desc' => "Bütün rakiplerden 4'er altın çeker!", 'icon' => 'magnet'],
    ['id' => 'banana', 'name' => 'Muz Kabuğu', 'cost' => 5, 'desc' => 'Bulunduğun karoya tuzak kur; üstüne basan 3 adım geri kayar!', 'icon' => 'banana'],
    ['id' => 'swap_wand', 'name' => 'Takas Asası', 'cost' => 15, 'desc' => 'İstediğin bir rakiple haritadaki yerlerini takas et!', 'icon' => 'refresh-cw'],
    ['id' => 'ghost_cloak', 'name' => 'Hayalet Pelerin', 'cost' => 9, 'desc' => '2 tur boyunca hiçbir tuzaktan veya cezadan etkilenmezsin!', 'icon' => 'shield'],
    ['id' => 'lucky_die', 'name' => 'Şanslı Altın Zar', 'cost' => 14, 'desc' => 'Sıradaki zarda 1-6 arasında istediğin sayıyı sen seçersin!', 'icon' => 'dices']
];

// File Locking Room Manager
// Room files are PHP files whose first line exits immediately. If a web server serves the
// rooms/ folder directly (Nginx ignores .htaccess), a request runs that guard and returns
// nothing instead of leaking every player's session id.
define('ROOM_FILE_GUARD', "<?php http_response_code(404); exit; ?>\n");

function getRoomFilePath($roomId) {
    global $roomsDir;
    if (!is_string($roomId)) return null;
    $cleanId = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($roomId)));
    if (strlen($cleanId) < 4 || strlen($cleanId) > 12) return null;
    return $roomsDir . DIRECTORY_SEPARATOR . $cleanId . '.json.php';
}

function loadRoom($roomId, &$fp = null, $lockType = LOCK_SH) {
    $fp = null;
    $path = getRoomFilePath($roomId);
    if (!$path || !file_exists($path)) return null;

    $fp = fopen($path, 'c+');
    if (!$fp) return null;

    if (!flock($fp, $lockType)) {
        fclose($fp);
        $fp = null;
        return null;
    }

    clearstatcache(true, $path);
    $size = filesize($path);
    $content = $size > 0 ? fread($fp, $size) : '';
    if (strpos($content, ROOM_FILE_GUARD) === 0) {
        $content = substr($content, strlen(ROOM_FILE_GUARD));
    }
    $room = $content !== '' ? json_decode($content, true) : null;
    if (!is_array($room)) {
        releaseRoom($fp);
        return null;
    }
    return $room;
}

function writeRoomFile($fp, $room) {
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, ROOM_FILE_GUARD . json_encode($room, JSON_UNESCAPED_UNICODE));
    fflush($fp);
}

function saveRoomAndUnlock(&$room, &$fp) {
    if (!$fp) return false;
    writeRoomFile($fp, $room);
    releaseRoom($fp);
    return true;
}

function releaseRoom(&$fp) {
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    $fp = null;
}

// Release the room lock, then answer with an error
function failLocked(&$fp, $msg, $code = 400) {
    releaseRoom($fp);
    sendError($msg, $code);
}

// -----------------------------------------------------------------------------
// INPUT SANITIZING
// -----------------------------------------------------------------------------
function cleanText($value, $maxLen, $fallback = '') {
    if (!is_string($value) && !is_numeric($value)) return $fallback;
    $value = preg_replace('/[<>"\'`&\\\\]/u', '', (string)$value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$value);
    $value = trim((string)$value);
    if ($value === '') return $fallback;
    return mb_substr($value, 0, $maxLen);
}

function cleanSlug($value, $fallback) {
    return (is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $value)) ? $value : $fallback;
}

function cleanColor($value, $fallback) {
    return (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) ? $value : $fallback;
}

function cleanInt($value, $min, $max, $fallback) {
    if (!is_numeric($value)) return $fallback;
    return max($min, min($max, intval($value)));
}

function cleanSessionId($value) {
    return (is_string($value) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $value)) ? $value : '';
}

function newSessionId() {
    return 's_' . bin2hex(random_bytes(12));
}

function normalizeTheme($theme) {
    $aliases = ['cyber' => 'tokyo', 'candy' => 'paris', 'tropics' => 'istanbul'];
    if (is_string($theme) && isset($aliases[$theme])) $theme = $aliases[$theme];
    return in_array($theme, ['istanbul', 'tokyo', 'paris', 'newyork'], true) ? $theme : 'istanbul';
}

// -----------------------------------------------------------------------------
// ROOM HELPERS
// -----------------------------------------------------------------------------
function findPlayerIndex($room, $sessionId) {
    if ($sessionId === '' || empty($room['players'])) return -1;
    foreach ($room['players'] as $idx => $p) {
        if (isset($p['sessionId']) && $p['sessionId'] === $sessionId) return $idx;
    }
    return -1;
}

// Session ids are reconnect secrets: a viewer only ever receives its own.
function publicPlayers($players, $viewerSessionId) {
    $out = [];
    foreach ($players as $p) {
        if (!isset($p['sessionId']) || $p['sessionId'] !== $viewerSessionId) unset($p['sessionId']);
        unset($p['lastSeen']);
        $out[] = $p;
    }
    return $out;
}

function publicRoom($room, $viewerSessionId) {
    $hostId = null;
    foreach ($room['players'] as $p) {
        if ($p['sessionId'] === $room['hostSessionId']) { $hostId = $p['id']; break; }
    }
    $isHost = $viewerSessionId !== '' && $viewerSessionId === $room['hostSessionId'];
    unset($room['password'], $room['hostSessionId']);
    $room['hostPlayerId'] = $hostId;
    $room['isHost'] = $isHost;
    $room['players'] = publicPlayers($room['players'], $viewerSessionId);
    return $room;
}

function minigamePool() {
    return [
        ['id' => 'coin_catch', 'title' => 'Gökten Altın Yağmuru', 'desc' => 'Düşen altınları sepete topla, bombalardan kaç!', 'duration' => 15],
        ['id' => 'reflex_pop', 'title' => 'Hızlı Parmak / Refleks', 'desc' => 'Yeşil sinyal geldiğinde ilk tıklayan kazanır!', 'duration' => 10],
        ['id' => 'balloon_mash', 'title' => 'Lego Balon Şişirme', 'desc' => 'En hızlı tıkla, balonunu ilk patlatan sen ol!', 'duration' => 12],
        ['id' => 'lucky_chests', 'title' => 'Hazine Sandığı Kumarı', 'desc' => '3 gizemli sandıktan birini seç! Ya büyük ödül ya da tuzak!', 'duration' => 11],
        ['id' => 'memory_matrix', 'title' => 'Parti Hafızası & Hızlı Renkler', 'desc' => 'Yanan renk dizisini hafızanda tut ve hatasız tekrarla!', 'duration' => 14],
        ['id' => 'timing_hammer', 'title' => 'Güç Çekici & Hassas Zamanlama', 'desc' => 'Tam ortadaki 100 puanlık altın zili tuttur!', 'duration' => 12]
    ];
}

// Pass the turn to the next player; handles round wrap, end-of-round mini-game and game over.
function advanceTurn(&$room) {
    $count = count($room['players']);
    $room['shop'] = null;
    $room['turnIndex'] = $count > 0 ? ($room['turnIndex'] + 1) : 0;
    if ($count > 0 && $room['turnIndex'] >= $count) {
        $room['turnIndex'] = 0;
        $room['currentRound']++;
        if ($room['currentRound'] > $room['totalRounds']) {
            $room['state'] = 'gameover';
            addRoomLog($room, "🏆 PARTİ TAMAMLANDI! Tebrikler kazananlar!");
        } else {
            $pool = minigamePool();
            $chosen = $pool[array_rand($pool)];
            // Remember which round the mini-game belongs to and who already reported a score
            $chosen['round'] = $room['currentRound'] - 1;
            $chosen['startedAt'] = time();
            $chosen['scored'] = [];
            $room['minigame'] = $chosen;
            addRoomLog($room, "🎮 " . ($room['currentRound'] - 1) . ". Tur bitti! Mini-Oyun: {$chosen['title']}");
        }
    }
    $room['turnStartedAt'] = time();
}

// Status effects tick down once per own roll
function tickStatusEffects(&$player) {
    foreach (['shieldActive', 'ghostCloakActive'] as $key) {
        if (!empty($player[$key])) $player[$key] = max(0, intval($player[$key]) - 1);
    }
}

function makeBot($template) {
    $botHats = ['crown', 'wizard', 'pirate', 'cat_ears', 'party_hat', 'visor', 'detective', 'viking'];
    return [
        'id' => 'bot_' . bin2hex(random_bytes(4)),
        'sessionId' => 'bot_' . bin2hex(random_bytes(12)),
        'name' => '[BOT] ' . $template['name'],
        'avatar' => $template['avatar'],
        'hat' => $botHats[mt_rand(0, count($botHats) - 1)],
        'diceSkin' => 'neon',
        'level' => mt_rand(2, 8),
        'title' => $template['title'],
        'color' => $template['color'],
        'position' => 0,
        'coins' => 15,
        'stars' => 0,
        'inventory' => ['double_dice'],
        'shieldActive' => 0,
        'jailedTurns' => 0,
        'frozenTurns' => 0,
        'reverseNext' => false,
        'doubleDiceActive' => false,
        'isBot' => true,
        'disconnected' => false,
        'lastSeen' => time()
    ];
}

// Read sanitized profile fields shared by create_room / join_room
function profileFromRequest($data, $defaultName) {
    return [
        'name' => cleanText(isset($data['playerName']) ? $data['playerName'] : '', 16, $defaultName),
        'avatar' => cleanSlug(isset($data['avatar']) ? $data['avatar'] : null, 'builder'),
        'hat' => cleanSlug(isset($data['hat']) ? $data['hat'] : null, 'none'),
        'color' => cleanColor(isset($data['color']) ? $data['color'] : null, null),
        'diceSkin' => cleanSlug(isset($data['diceSkin']) ? $data['diceSkin'] : null, 'default'),
        'level' => cleanInt(isset($data['level']) ? $data['level'] : 1, 1, 100, 1),
        'title' => cleanText(isset($data['title']) ? $data['title'] : '', 24, 'Acemi Gezgin')
    ];
}

function addRoomLog(&$room, $text) {
    if (!isset($room['log']) || !is_array($room['log'])) $room['log'] = [];
    $room['log'][] = [
        'id' => substr(md5(microtime(true) . mt_rand()), 0, 6),
        'time' => date('H:i:s'),
        'text' => $text
    ];
    if (count($room['log']) > 30) {
        array_shift($room['log']);
    }
}

// -----------------------------------------------------------------------------
// ROUTER & ACTIONS
// -----------------------------------------------------------------------------

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$data = getRequestData();
if (empty($action) && isset($data['action'])) {
    $action = $data['action'];
}
$action = is_string($action) ? $action : '';
$roomIdParam = (isset($data['roomId']) && is_string($data['roomId'])) ? strtoupper(trim($data['roomId'])) : '';
$sessionId = cleanSessionId(isset($data['sessionId']) ? $data['sessionId'] : '');

function starTiles($board) {
    $out = [];
    foreach ($board as $t) {
        if ($t['type'] === 'star') $out[] = $t['id'];
    }
    return $out ?: [7, 15, 23, 31];
}

// Full state_update message as the client expects it, redacted for one viewer
function stateUpdatePayload($room, $viewerSessionId) {
    $pub = publicRoom($room, $viewerSessionId);
    return [
        'type' => 'state_update',
        'roomId' => $room['id'],
        'state' => $room['state'],
        'mapTheme' => $room['mapTheme'],
        'totalRounds' => $room['totalRounds'],
        'currentRound' => $room['currentRound'],
        'turnIndex' => $room['turnIndex'],
        'activeStarTile' => $room['activeStarTile'],
        'trapsOnBoard' => $room['trapsOnBoard'],
        'board' => $room['board'],
        'players' => $pub['players'],
        'log' => $room['log'],
        'lastAction' => isset($room['lastAction']) ? $room['lastAction'] : null,
        'minigame' => isset($room['minigame']) ? $room['minigame'] : null,
        'hostPlayerId' => $pub['hostPlayerId'],
        'isHost' => $pub['isHost']
    ];
}

// Load a room with an exclusive lock and require the caller to be one of its players
function loadRoomAsMember($roomId, $sessionId, &$fp, &$playerIdx) {
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı', 404);
    $playerIdx = findPlayerIndex($room, $sessionId);
    if ($playerIdx === -1) failLocked($fp, 'Bu odada oyuncu değilsin!', 403);
    return $room;
}

function requireHostInLobby(&$room, $sessionId, &$fp) {
    if ($room['hostSessionId'] !== $sessionId) failLocked($fp, 'Bu işlemi yalnızca oda sahibi yapabilir!', 403);
    if ($room['state'] !== 'lobby') failLocked($fp, 'Oyun başladıktan sonra bu işlem yapılamaz!');
}

// Health Check
if ($action === 'health' || $action === '') {
    sendJson([
        'status' => 'ok',
        'php' => true,
        'storage' => $storageWritable,
        'time' => time(),
        'message' => 'PartyCraft: Wonder Board Online Engine Aktif!'
    ]);
}

// 1. Create Room
if ($action === 'create_room') {
    if (!$storageWritable) {
        sendError('Sunucu depolama klasörüne yazılamıyor. Lütfen "rooms" klasör izinlerini kontrol edin.');
    }

    // Generate a unique 6-character room code. fopen mode 'x' fails when the file
    // already exists, so a new room can never overwrite an existing one.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $fp = null;
    $roomId = '';
    for ($attempt = 0; $attempt < 20 && !$fp; $attempt++) {
        $roomId = '';
        for ($i = 0; $i < 6; $i++) {
            $roomId .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $fp = @fopen(getRoomFilePath($roomId), 'x');
    }
    if (!$fp || !flock($fp, LOCK_EX)) {
        sendError('Oda dosyası oluşturulamadı.');
    }

    $theme = normalizeTheme(isset($data['mapTheme']) ? $data['mapTheme'] : 'istanbul');
    $rounds = cleanInt(isset($data['rounds']) ? $data['rounds'] : 10, 5, 30, 10);
    $rawPass = (isset($data['password']) && is_string($data['password'])) ? trim($data['password']) : '';
    $password = $rawPass !== '' ? password_hash(mb_substr($rawPass, 0, 64), PASSWORD_DEFAULT) : null;

    if ($sessionId === '') $sessionId = newSessionId();
    $prof = profileFromRequest($data, 'Oyuncu 1');

    $hostPlayer = [
        'id' => 'p_' . bin2hex(random_bytes(4)),
        'sessionId' => $sessionId,
        'name' => $prof['name'],
        'avatar' => $prof['avatar'],
        'hat' => $prof['hat'],
        'diceSkin' => $prof['diceSkin'],
        'level' => $prof['level'],
        'title' => $prof['title'],
        'color' => $prof['color'] ?: '#3B82F6',
        'position' => 0,
        'coins' => 15,
        'stars' => 0,
        'inventory' => [],
        'shieldActive' => 0,
        'jailedTurns' => 0,
        'frozenTurns' => 0,
        'reverseNext' => false,
        'doubleDiceActive' => false,
        'isBot' => false,
        'disconnected' => false,
        'lastSeen' => time()
    ];

    $board = generateBoardForTheme($theme);
    $stars = starTiles($board);

    $room = [
        'id' => $roomId,
        'createdAt' => time(),
        'updatedAt' => time(),
        'hostSessionId' => $sessionId,
        'password' => $password,
        'state' => 'lobby',
        'mapTheme' => $theme,
        'totalRounds' => $rounds,
        'currentRound' => 1,
        'turnIndex' => 0,
        'activeStarTile' => $stars[array_rand($stars)],
        'trapsOnBoard' => [],
        'board' => $board,
        'players' => [$hostPlayer],
        'log' => [],
        'lastAction' => null,
        'shop' => null
    ];

    addRoomLog($room, "🏰 {$hostPlayer['name']} odayı kurdu! Kod: {$roomId}");

    writeRoomFile($fp, $room);
    releaseRoom($fp);

    sendJson([
        'type' => 'room_created',
        'roomId' => $roomId,
        'sessionId' => $sessionId,
        'player' => $hostPlayer,
        'state' => stateUpdatePayload($room, $sessionId)
    ]);
}

// 2. Join Room
if ($action === 'join_room') {
    $fp = null;
    $room = loadRoom($roomIdParam, $fp, LOCK_EX);

    if (!$room) {
        sendError('Oda bulunamadı veya süresi doldu!');
    }

    $existingIndex = findPlayerIndex($room, $sessionId);

    // Returning players prove themselves with their session id; newcomers need the password
    if ($existingIndex === -1 && !empty($room['password'])) {
        $givenPass = (isset($data['password']) && is_string($data['password'])) ? trim($data['password']) : '';
        if (!password_verify(mb_substr($givenPass, 0, 64), $room['password'])) {
            failLocked($fp, 'Oda şifresi hatalı!');
        }
    }

    if ($sessionId === '') $sessionId = newSessionId();

    if ($existingIndex !== -1) {
        // Reconnecting existing player!
        $existing = &$room['players'][$existingIndex];
        $existing['disconnected'] = false;
        $existing['lastSeen'] = time();
        if (!empty($existing['wasHuman'])) {
            // Take the seat back from the bot that covered for this player
            $existing['isBot'] = false;
            unset($existing['wasHuman']);
        }
        $existing['name'] = cleanText(isset($data['playerName']) ? $data['playerName'] : '', 16, preg_replace('/ \(Bot\)$/u', '', $existing['name']));
        $existing['avatar'] = cleanSlug(isset($data['avatar']) ? $data['avatar'] : null, $existing['avatar']);
        $existing['hat'] = cleanSlug(isset($data['hat']) ? $data['hat'] : null, $existing['hat']);
        $existing['color'] = cleanColor(isset($data['color']) ? $data['color'] : null, $existing['color']);

        addRoomLog($room, "🔄 {$existing['name']} odaya tekrar bağlandı!");
        $player = $existing;
        unset($existing);
    } else {
        // New Player Joining
        if ($room['state'] !== 'lobby') {
            failLocked($fp, 'Bu oda şu anda oynanıyor, yeni oyuncu katılamaz!');
        }

        if (count($room['players']) >= 4) {
            failLocked($fp, 'Oda dolu! (Maksimum 4 oyuncu)');
        }

        $colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4'];
        $prof = profileFromRequest($data, 'Oyuncu ' . (count($room['players']) + 1));

        $player = [
            'id' => 'p_' . bin2hex(random_bytes(4)),
            'sessionId' => $sessionId,
            'name' => $prof['name'],
            'avatar' => $prof['avatar'],
            'hat' => $prof['hat'],
            'diceSkin' => $prof['diceSkin'],
            'level' => $prof['level'],
            'title' => $prof['title'],
            'color' => $prof['color'] ?: $colors[count($room['players']) % count($colors)],
            'position' => 0,
            'coins' => 15,
            'stars' => 0,
            'inventory' => [],
            'shieldActive' => 0,
            'jailedTurns' => 0,
            'frozenTurns' => 0,
            'reverseNext' => false,
            'doubleDiceActive' => false,
            'isBot' => false,
            'disconnected' => false,
            'lastSeen' => time()
        ];

        $room['players'][] = $player;
        addRoomLog($room, "👋 {$player['name']} partiye katıldı!");
    }

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);

    sendJson([
        'type' => 'room_joined',
        'roomId' => $room['id'],
        'sessionId' => $sessionId,
        'player' => $player,
        'state' => stateUpdatePayload($room, $sessionId)
    ]);
}

// 3. Get State (Periodic Polling & Heartbeat)
if ($action === 'get_state') {
    $fp = null;
    $room = loadRoom($roomIdParam, $fp, LOCK_EX);
    if (!$room) {
        sendError('Oda bulunamadı', 404);
    }

    $now = time();
    $stateChanged = false;

    // Heartbeat update for caller
    foreach ($room['players'] as &$p) {
        if ($sessionId !== '' && $p['sessionId'] === $sessionId) {
            $p['lastSeen'] = $now;
            if ($p['disconnected']) {
                $p['disconnected'] = false;
                $stateChanged = true;
            }
        } else if (!$p['isBot']) {
            // Check disconnect for others if absent for 20s
            if (($now - (isset($p['lastSeen']) ? $p['lastSeen'] : $now)) > 20) {
                if (!$p['disconnected']) {
                    $p['disconnected'] = true;
                    $stateChanged = true;
                }
            }
        }
    }
    unset($p);

    // Auto-advance bot turn or disconnected player turn after 2 seconds of inactivity
    if ($room['state'] === 'playing' && !empty($room['players'])) {
        $room['turnIndex'] = $room['turnIndex'] % count($room['players']);
        $activePlayer = &$room['players'][$room['turnIndex']];
        $isBot = !empty($activePlayer['isBot']);
        $isAfk = !empty($activePlayer['disconnected']);

        if ($isBot || $isAfk) {
            $turnStart = isset($room['turnStartedAt']) ? $room['turnStartedAt'] : 0;
            if ($now - $turnStart >= 2) {
                if (!empty($activePlayer['frozenTurns'])) $activePlayer['frozenTurns'] = max(0, intval($activePlayer['frozenTurns']) - 1);
                if (!empty($activePlayer['jailedTurns'])) {
                    $activePlayer['jailedTurns'] = max(0, intval($activePlayer['jailedTurns']) - 1);
                    addRoomLog($room, "⛓️ {$activePlayer['name']} Kodes cezasında, turu pas geçti!");
                } else {
                    // Roll die
                    $die = mt_rand(1, 6);
                    $oldPos = $activePlayer['position'];
                    $newPos = ($oldPos + $die) % count($room['board']);
                    $activePlayer['position'] = $newPos;

                    addRoomLog($room, "🤖 {$activePlayer['name']} zar attı: [{$die}] ve {$newPos}. karoya ilerledi.");

                    // Process tile
                    $tile = $room['board'][$newPos];
                    if ($tile['type'] === 'coin') {
                        $amt = isset($tile['amount']) ? $tile['amount'] : 4;
                        $activePlayer['coins'] += $amt;
                    } else if ($tile['type'] === 'star' && $activePlayer['coins'] >= 20) {
                        $activePlayer['coins'] -= 20;
                        $activePlayer['stars'] += 1;
                        addRoomLog($room, "⭐ {$activePlayer['name']} 20 altına bir Yıldız satın aldı!");
                        $stars = starTiles($room['board']);
                        $room['activeStarTile'] = $stars[array_rand($stars)];
                    } else if ($tile['type'] === 'trap' && empty($activePlayer['shieldActive']) && empty($activePlayer['ghostCloakActive'])) {
                        $pen = isset($tile['penalty']) ? $tile['penalty'] : 4;
                        $activePlayer['coins'] = max(0, $activePlayer['coins'] - $pen);
                    }
                    tickStatusEffects($activePlayer);
                }

                unset($activePlayer);
                advanceTurn($room);
                $stateChanged = true;
            }
        }
        unset($activePlayer);
    }

    if ($stateChanged) {
        $room['updatedAt'] = $now;
    }

    saveRoomAndUnlock($room, $fp);
    sendJson(stateUpdatePayload($room, $sessionId));
}

// 4. Start Game (host only, from the lobby)
if ($action === 'start_game') {
    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);
    requireHostInLobby($room, $sessionId, $fp);

    // Auto-fill with bots up to 4 players so the game is always fun and full
    while (count($room['players']) < 4) {
        $room['players'][] = makeBot($BOT_TEMPLATES[count($room['players']) % count($BOT_TEMPLATES)]);
    }

    $stars = starTiles($room['board']);
    $room['state'] = 'playing';
    $room['currentRound'] = 1;
    $room['turnIndex'] = 0;
    $room['turnStartedAt'] = time();
    $room['activeStarTile'] = $stars[array_rand($stars)];
    $room['shop'] = null;

    addRoomLog($room, "🚀 Parti başladı! Toplam {$room['totalRounds']} tur oynanacak. Yıldız kaidesi {$room['activeStarTile']}. karoda!");

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);

    $payload = stateUpdatePayload($room, $sessionId);
    sendJson(array_merge($payload, ['room' => $payload]));
}

// 5. Add Bot (host only, from the lobby)
if ($action === 'add_bot') {
    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);
    requireHostInLobby($room, $sessionId, $fp);

    if (count($room['players']) < 4) {
        $bot = makeBot($BOT_TEMPLATES[count($room['players']) % count($BOT_TEMPLATES)]);
        $room['players'][] = $bot;
        addRoomLog($room, "🤖 {$bot['name']} odaya eklendi!");
        $room['updatedAt'] = time();
    }

    saveRoomAndUnlock($room, $fp);
    sendJson(stateUpdatePayload($room, $sessionId));
}

// 6. Switch Map (host only, from the lobby)
if ($action === 'switch_map') {
    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);
    requireHostInLobby($room, $sessionId, $fp);

    $mapTheme = normalizeTheme(isset($data['mapTheme']) ? $data['mapTheme'] : 'istanbul');
    $room['mapTheme'] = $mapTheme;
    $room['board'] = generateBoardForTheme($mapTheme);
    addRoomLog($room, "🗺️ Harita seçildi: " . strtoupper($mapTheme));

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);
    sendJson(stateUpdatePayload($room, $sessionId));
}

// 7. Roll Dice
if ($action === 'roll_dice') {
    $fp = null;
    $room = loadRoom($roomIdParam, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');
    if ($room['state'] !== 'playing' || empty($room['players'])) {
        failLocked($fp, 'Oyun şu anda aktif değil!');
    }

    $room['turnIndex'] = $room['turnIndex'] % count($room['players']);
    $turnIdx = $room['turnIndex'];
    if ($sessionId === '' || $room['players'][$turnIdx]['sessionId'] !== $sessionId) {
        failLocked($fp, 'Sıra sende değil!');
    }

    $room['shop'] = null;
    $player = &$room['players'][$turnIdx];

    // A freeze lasts until the frozen player's next roll
    if (!empty($player['frozenTurns'])) {
        $player['frozenTurns'] = max(0, intval($player['frozenTurns']) - 1);
    }

    // Check jail
    if (!empty($player['jailedTurns'])) {
        if ($player['coins'] >= 5) {
            $player['coins'] -= 5;
            $player['jailedTurns'] = 0;
            addRoomLog($room, "🔑 {$player['name']} 5 altın kefalet ödeyerek serbest kaldı!");
        } else {
            $player['jailedTurns']--;
            addRoomLog($room, "⛓️ {$player['name']} Kodes cezasında, turu pas geçti!");
            unset($player);
            advanceTurn($room);
            $room['updatedAt'] = time();
            saveRoomAndUnlock($room, $fp);
            sendJson(['type' => 'turn_skipped', 'state' => stateUpdatePayload($room, $sessionId)]);
        }
    }

    $wasFixed = !empty($player['fixedRollNext']);
    $die1 = $wasFixed ? cleanInt($player['fixedRollNext'], 1, 6, 6) : mt_rand(1, 6);
    unset($player['fixedRollNext']);
    $die2 = null;
    $totalRoll = $die1;

    if (!empty($player['doubleDiceActive'])) {
        $die2 = mt_rand(1, 6);
        $totalRoll = $die1 + $die2;
        $player['doubleDiceActive'] = false;
        addRoomLog($room, "🎲🎲 {$player['name']} ÇİFT ZAR attı: [{$die1}] + [{$die2}] = {$totalRoll} adım!");
    } else {
        if ($wasFixed) {
            addRoomLog($room, "🎯 {$player['name']} Şanslı Altın Zarını attı: [{$die1}]!");
        } else {
            addRoomLog($room, "🎲 {$player['name']} zar attı: [{$die1}]!");
        }
    }

    $isReverse = !empty($player['reverseNext']);
    $player['reverseNext'] = false;
    $effectiveSteps = $isReverse ? -$totalRoll : $totalRoll;

    $oldPos = $player['position'];
    $boardLen = count($room['board']);
    $newPos = ($oldPos + $effectiveSteps + $boardLen) % $boardLen;
    $player['position'] = $newPos;

    // Passing start
    if (!$isReverse && ($oldPos + $totalRoll >= $boardLen)) {
        $player['coins'] += 10;
        addRoomLog($room, "💰 {$player['name']} Başlangıçtan geçti ve +10 Altın kazandı!");
    }

    // Landed tile action
    $tile = $room['board'][$newPos];
    $protected = !empty($player['shieldActive']) || !empty($player['ghostCloakActive']);
    $openShop = false;

    if ($tile['type'] === 'coin') {
        $amt = isset($tile['amount']) ? $tile['amount'] : 4;
        $player['coins'] += $amt;
        addRoomLog($room, "🪙 {$player['name']}, {$tile['title']} karosundan +{$amt} Altın topladı!");
    } else if ($tile['type'] === 'star') {
        if ($player['coins'] >= 20) {
            $player['coins'] -= 20;
            $player['stars'] += 1;
            addRoomLog($room, "⭐ TEBRİKLER! {$player['name']} 20 Altına 1 YILDIZ satın aldı!");
            $stars = starTiles($room['board']);
            $room['activeStarTile'] = $stars[array_rand($stars)];
        } else {
            addRoomLog($room, "⭐ {$player['name']} Yıldız kaidesine geldi ama 20 altını yoktu!");
        }
    } else if ($tile['type'] === 'trap') {
        if ($protected) {
            addRoomLog($room, "🛡️ {$player['name']} koruması sayesinde tuzaktan etkilenmedi!");
        } else {
            $pen = isset($tile['penalty']) ? $tile['penalty'] : 4;
            $player['coins'] = max(0, $player['coins'] - $pen);
            addRoomLog($room, "🔥 TUZAK! {$player['name']} {$pen} altın kaybetti!");
        }
    } else if ($tile['type'] === 'jail' && !$protected) {
        $player['jailedTurns'] = 1;
        addRoomLog($room, "🔒 {$player['name']} Kodese düştü!");
    } else if ($tile['type'] === 'freeze' && !$protected) {
        $player['frozenTurns'] = 1;
        addRoomLog($room, "🧊 {$player['name']} donduruldu!");
    } else if ($tile['type'] === 'reverse') {
        $player['reverseNext'] = true;
        addRoomLog($room, "🔄 {$player['name']} bir sonraki zarda geriye doğru yürüyecek!");
    } else if ($tile['type'] === 'shop' && empty($player['isBot'])) {
        $openShop = true;
    }

    tickStatusEffects($player);

    $room['lastAction'] = [
        'type' => 'dice_rolled',
        'playerId' => $player['id'],
        'playerName' => $player['name'],
        'die1' => $die1,
        'die2' => $die2,
        'totalRoll' => $totalRoll,
        'targetTile' => $newPos
    ];
    $shopPlayerId = $player['id'];
    unset($player);

    // Advance Turn
    advanceTurn($room);
    if ($openShop) {
        // The shop stays open for this player until the next roll, at most 30 seconds
        $room['shop'] = ['playerId' => $shopPlayerId, 'until' => time() + 30];
    }
    $room['updatedAt'] = time();

    saveRoomAndUnlock($room, $fp);

    sendJson([
        'type' => 'dice_rolled',
        'playerId' => $shopPlayerId,
        'playerName' => $room['lastAction']['playerName'],
        'die1' => $die1,
        'die2' => $die2,
        'totalRoll' => $totalRoll,
        'newPosition' => $newPos,
        'shopItems' => $openShop ? $SHOP_ITEMS : null,
        'state' => stateUpdatePayload($room, $sessionId)
    ]);
}

// 8. Buy Item (only right after landing on a shop tile)
if ($action === 'buy_item') {
    $itemId = (isset($data['itemId']) && is_string($data['itemId'])) ? $data['itemId'] : '';

    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);
    if ($room['state'] !== 'playing') failLocked($fp, 'Oyun şu anda aktif değil!');

    $shop = isset($room['shop']) ? $room['shop'] : null;
    if (!$shop || $shop['playerId'] !== $room['players'][$playerIdx]['id'] || time() > $shop['until']) {
        failLocked($fp, 'Dükkân şu anda senin için açık değil!');
    }

    $item = null;
    foreach ($SHOP_ITEMS as $it) {
        if ($it['id'] === $itemId) { $item = $it; break; }
    }
    if (!$item) failLocked($fp, 'Geçersiz eşya!');

    $p = &$room['players'][$playerIdx];
    if ($p['coins'] < $item['cost']) failLocked($fp, 'Yeterli altının yok!');
    $p['coins'] -= $item['cost'];
    $p['inventory'][] = $item['id'];
    addRoomLog($room, "🛍️ {$p['name']} {$item['name']} satın aldı!");
    unset($p);

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);
    $payload = stateUpdatePayload($room, $sessionId);
    sendJson(array_merge($payload, ['room' => $payload]));
}

// 9. Send Emote & Quick Chat
if ($action === 'send_emote' || $action === 'send_quick_chat') {
    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);
    $player = $room['players'][$playerIdx];

    if ($action === 'send_emote') {
        $emoteId = cleanSlug(isset($data['emoteId']) ? $data['emoteId'] : null, 'gg');
        $room['lastAction'] = ['type' => 'player_emote', 'playerId' => $player['id'], 'emoteId' => $emoteId];
    } else {
        $text = cleanText(isset($data['text']) ? $data['text'] : '', 80, '');
        if ($text === '') failLocked($fp, 'Boş mesaj gönderilemez.');
        $room['lastAction'] = ['type' => 'player_chat', 'playerId' => $player['id'], 'playerName' => $player['name'], 'text' => $text];
        addRoomLog($room, "💬 {$player['name']}: {$text}");
    }
    $room['updatedAt'] = time();

    saveRoomAndUnlock($room, $fp);
    sendJson(['type' => 'success']);
}

// 10b. Leave Room (Any Player Anytime)
if ($action === 'leave_room') {
    $fp = null;
    $room = loadRoom($roomIdParam, $fp, LOCK_EX);
    $idx = $room ? findPlayerIndex($room, $sessionId) : -1;
    if ($room && $idx !== -1) {
        $leftName = $room['players'][$idx]['name'];
        if ($room['state'] === 'lobby') {
            array_splice($room['players'], $idx, 1);
            if ($room['hostSessionId'] === $sessionId) {
                // Hand the host role to the next human player
                $room['hostSessionId'] = '';
                foreach ($room['players'] as $p) {
                    if (empty($p['isBot'])) { $room['hostSessionId'] = $p['sessionId']; break; }
                }
            }
        } else {
            // In active game: convert to smart bot so match continues smoothly
            $room['players'][$idx]['isBot'] = true;
            $room['players'][$idx]['wasHuman'] = true;
            if (!preg_match('/ \(Bot\)$/u', $room['players'][$idx]['name'])) {
                $room['players'][$idx]['name'] .= ' (Bot)';
            }
        }
        addRoomLog($room, '🚪 ' . $leftName . ' partiden ayrıldı.');
        $room['updatedAt'] = time();
        saveRoomAndUnlock($room, $fp);
    }
    releaseRoom($fp);
    sendJson(['type' => 'success', 'message' => 'left']);
}

// 10. Kick Player (Host only, lobby only)
if ($action === 'kick_player') {
    $targetId = (isset($data['targetPlayerId']) && is_string($data['targetPlayerId'])) ? $data['targetPlayerId'] : '';

    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);
    requireHostInLobby($room, $sessionId, $fp);
    if ($room['players'][$playerIdx]['id'] === $targetId) failLocked($fp, 'Kendini odadan çıkaramazsın!');

    $kickedName = '';
    $newPlayers = [];
    foreach ($room['players'] as $p) {
        if ($p['id'] === $targetId) {
            $kickedName = $p['name'];
        } else {
            $newPlayers[] = $p;
        }
    }

    if (!empty($kickedName)) {
        $room['players'] = $newPlayers;
        addRoomLog($room, "🚪 Oda sahibi, {$kickedName} adlı oyuncuyu odadan çıkardı.");
        $room['updatedAt'] = time();
    }

    saveRoomAndUnlock($room, $fp);
    sendJson(stateUpdatePayload($room, $sessionId));
}

// 11. Use Item (own turn only, before rolling)
if ($action === 'use_item') {
    $itemId = (isset($data['itemId']) && is_string($data['itemId'])) ? $data['itemId'] : '';
    $targetId = isset($data['targetPlayerId']) ? $data['targetPlayerId'] : null;

    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);
    if ($room['state'] !== 'playing') failLocked($fp, 'Oyun şu anda aktif değil!');
    if (($room['turnIndex'] % count($room['players'])) !== $playerIdx) {
        failLocked($fp, 'Eşyaları yalnızca kendi sıranda kullanabilirsin!');
    }

    $player = &$room['players'][$playerIdx];
    if (!empty($player['frozenTurns'])) {
        unset($player);
        failLocked($fp, 'Donmuş durumdasın, bu tur eşya kullanamazsın!');
    }
    $invIdx = array_search($itemId, $player['inventory'], true);
    if ($invIdx === false) {
        unset($player);
        failLocked($fp, 'Bu eşya çantanda yok!');
    }

    // Resolve the swap target before consuming the item
    $targetIdx = -1;
    if ($itemId === 'swap_wand') {
        foreach ($room['players'] as $i => $opp) {
            if ($i === $playerIdx) continue;
            if ((is_string($targetId) && $opp['id'] === $targetId) || ($targetIdx === -1 && !$targetId)) {
                $targetIdx = $i;
                if ($targetId) break;
            }
        }
        if ($targetIdx === -1) {
            unset($player);
            failLocked($fp, 'Takas edilecek rakip bulunamadı!');
        }
    }

    array_splice($player['inventory'], $invIdx, 1);

    if ($itemId === 'double_dice') {
        $player['doubleDiceActive'] = true;
        addRoomLog($room, "🎲🎲 {$player['name']} Çift Zar kullandı!");
    } else if ($itemId === 'rocket') {
        $player['position'] = ($player['position'] + 7) % count($room['board']);
        addRoomLog($room, "🚀 {$player['name']} Süper Roketle 7 adım ileri fırladı!");
    } else if ($itemId === 'shield') {
        $player['shieldActive'] = 2;
        addRoomLog($room, "🛡️ {$player['name']} Manyetik Kalkan kuşandı!");
    } else if ($itemId === 'magnet') {
        $stolenTotal = 0;
        foreach ($room['players'] as $i => $opp) {
            if ($i !== $playerIdx && empty($opp['shieldActive'])) {
                $amt = min(4, $opp['coins']);
                $room['players'][$i]['coins'] -= $amt;
                $stolenTotal += $amt;
            }
        }
        $player['coins'] += $stolenTotal;
        addRoomLog($room, "🧲 {$player['name']} Altın Mıknatısıyla rakiplerden {$stolenTotal} altın çekti!");
    } else if ($itemId === 'banana') {
        $room['trapsOnBoard'][] = ['tileIndex' => $player['position'], 'ownerId' => $player['id']];
        addRoomLog($room, "🍌 {$player['name']} {$player['position']}. karoya Muz Kabuğu tuzağı kurdu!");
    } else if ($itemId === 'swap_wand') {
        $tmp = $player['position'];
        $player['position'] = $room['players'][$targetIdx]['position'];
        $room['players'][$targetIdx]['position'] = $tmp;
        addRoomLog($room, "🪄 {$player['name']} Takas Asası ile {$room['players'][$targetIdx]['name']} ile yer değiştirdi!");
    } else if ($itemId === 'ghost_cloak') {
        $player['ghostCloakActive'] = 2;
        addRoomLog($room, "👻 {$player['name']} Hayalet Pelerin kuşandı! 2 tur cezalardan muaf!");
    } else if ($itemId === 'lucky_die') {
        $player['fixedRollNext'] = cleanInt($targetId, 1, 6, 6);
        addRoomLog($room, "🎯 {$player['name']} Şanslı Altın Zar ayarladı [{$player['fixedRollNext']}]!");
    }
    unset($player);

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);
    $payload = stateUpdatePayload($room, $sessionId);
    sendJson(array_merge($payload, ['room' => $payload]));
}

// 12. Close Shop
if ($action === 'close_shop') {
    $fp = null;
    $room = loadRoom($roomIdParam, $fp, LOCK_EX);
    $idx = $room ? findPlayerIndex($room, $sessionId) : -1;
    if ($room && $idx !== -1 && !empty($room['shop']) && $room['shop']['playerId'] === $room['players'][$idx]['id']) {
        $room['shop'] = null;
        $room['updatedAt'] = time();
        saveRoomAndUnlock($room, $fp);
    }
    releaseRoom($fp);
    sendJson(['type' => 'success']);
}

// 13. Minigame Score (one report per player per mini-game, only while it runs)
if ($action === 'minigame_score') {
    $fp = null;
    $playerIdx = -1;
    $room = loadRoomAsMember($roomIdParam, $sessionId, $fp, $playerIdx);

    $mg = isset($room['minigame']) ? $room['minigame'] : null;
    if (!$mg || !isset($mg['startedAt'], $mg['duration'])) {
        failLocked($fp, 'Şu anda aktif bir mini oyun yok.');
    }
    if (time() > $mg['startedAt'] + $mg['duration'] + 30) {
        failLocked($fp, 'Mini oyunun süresi doldu.');
    }
    $playerId = $room['players'][$playerIdx]['id'];
    $scored = isset($mg['scored']) && is_array($mg['scored']) ? $mg['scored'] : [];
    if (in_array($playerId, $scored, true)) {
        failLocked($fp, 'Bu mini oyun için skorun zaten kaydedildi.');
    }

    // Scores are still reported by the client; clamp them to what the mini-games can produce
    $score = cleanInt(isset($data['score']) ? $data['score'] : 0, 0, 150, 0);
    $reward = min(15, max(3, intval($score / 2)));
    $room['players'][$playerIdx]['coins'] += $reward;
    $scored[] = $playerId;
    $room['minigame']['scored'] = $scored;
    addRoomLog($room, "🎮 {$room['players'][$playerIdx]['name']} mini oyundan {$score} puan aldı ve +{$reward} Altın kazandı!");

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);
    sendJson(['type' => 'success']);
}

// Unknown Action Fallback
sendError('Geçersiz işlem isteği.');

    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Parti Dünyası — Tarayıcıda parti masa oyunu</title>
  <meta name="description" content="İstanbul, Tokyo, Paris ve New York'ta geçen ücretsiz parti masa oyunu. Zar at, altın topla, yıldızları kap; arkadaşlarınla veya botlarla, kurulumsuz ve internetsiz oyna.">
  <meta name="keywords" content="parti oyunu, masa oyunu, tarayıcı oyunu, çok oyunculu, zar oyunu, mini oyun, board game, party game, browser game">
  <link rel="canonical" href="https://nedimdmir.github.io/board_game/">

  <!-- Link previews (WhatsApp, X/Twitter, Discord, Telegram…) -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Parti Dünyası">
  <meta property="og:title" content="Parti Dünyası — Tarayıcıda parti masa oyunu">
  <meta property="og:description" content="İstanbul, Tokyo, Paris ve New York tahtalarında zar at, yıldızları kap, mini oyunlarda kazan. Ücretsiz, kurulumsuz.">
  <meta property="og:url" content="https://nedimdmir.github.io/board_game/">
  <meta property="og:image" content="https://nedimdmir.github.io/board_game/assets/screenshots/istanbul.jpg">
  <meta property="og:image:width" content="1280">
  <meta property="og:image:height" content="800">
  <meta property="og:locale" content="tr_TR">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Parti Dünyası — Tarayıcıda parti masa oyunu">
  <meta name="twitter:description" content="Dört şehir, dört tahta, sekiz eşya, altı mini oyun. Arkadaşlarınla veya botlarla oyna.">
  <meta name="twitter:image" content="https://nedimdmir.github.io/board_game/assets/screenshots/istanbul.jpg">

  <meta name="theme-color" content="#1E1530">

  <!-- Stylesheets (fonts are bundled locally in premium.css, so the game works offline) -->
  <style>
/* =================== BOARD CSS =================== */
/* ==========================================================================
   PartyCraft: Wonder Board - Board & Special Effects Styling
   ========================================================================== */

/* ----------------------------------------------------
   3D TUMBLING DICE ROLLING OVERLAY
   ---------------------------------------------------- */
.dice-roll-modal {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.dice-cubes-container {
  display: flex;
  gap: 24px;
  perspective: 600px;
}

.dice-cube {
  width: 72px;
  height: 72px;
  background: white;
  border-radius: 14px;
  border: 3px solid #CBD5E1;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15), inset 0 2px 4px rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  font-weight: 900;
  color: #1E293B;
  transition: transform 0.2s ease;
}

.dice-cube.rolling {
  animation: diceTumble 0.9s cubic-bezier(0.25, 1, 0.5, 1);
}

.dice-cube.landed {
  transform: scale(1.1) rotate(0deg);
  border-color: #3B82F6;
  color: #1D4ED8;
  box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
}

@keyframes diceTumble {
  0% { transform: translateY(-40px) rotateX(0deg) rotateY(0deg) scale(0.8); }
  25% { transform: translateY(15px) rotateX(180deg) rotateY(90deg) scale(1.15); }
  50% { transform: translateY(-15px) rotateX(360deg) rotateY(270deg) scale(0.95); }
  75% { transform: translateY(5px) rotateX(540deg) rotateY(450deg) scale(1.05); }
  100% { transform: translateY(0) rotateX(720deg) rotateY(720deg) scale(1); }
}

.dice-result-text {
  font-size: 20px;
  font-weight: 800;
  color: #1E293B;
  text-align: center;
}

/* Mystery Roulette styles consolidated in style.css */

/* ----------------------------------------------------
   MINI-GAMES STYLING
   ---------------------------------------------------- */
.minigame-card {
  background: white;
  border-radius: 20px;
  padding: 24px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  border: 3px solid #F1F5F9;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.minigame-header h2 {
  font-size: 20px;
  font-weight: 900;
  color: #1E293B;
}

.minigame-header p {
  font-size: 13px;
  color: #64748B;
  margin-top: 4px;
}

.minigame-stats {
  display: flex;
  justify-content: space-between;
  margin-top: 10px;
}

.badge-time,
.badge-score {
  font-weight: 800;
  font-size: 14px;
  padding: 6px 14px;
  border-radius: 10px;
  background: #F1F5F9;
  color: #1E293B;
}

.badge-score {
  background: #FEF9C3;
  color: #854D0E;
}

/* Mini-Game 1: Coin Catch Arena */
.coin-catch-arena {
  width: 100%;
  height: 260px;
  background: linear-gradient(180deg, #E0F2FE 0%, #BAE6FD 100%);
  border-radius: 14px;
  position: relative;
  overflow: hidden;
  border: 2px solid #7DD3FC;
}

.catch-basket {
  position: absolute;
  bottom: 12px;
  font-size: 34px;
  user-select: none;
  pointer-events: none;
  transition: transform 0.1s ease;
}

.falling-item {
  position: absolute;
  font-size: 26px;
  user-select: none;
  pointer-events: none;
}

.touch-controls-row {
  display: flex;
  gap: 12px;
}

.touch-btn {
  flex: 1;
  padding: 14px;
  border: none;
  border-radius: 12px;
  background: #3B82F6;
  color: white;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 4px 0 #1D4ED8;
}

.touch-btn:active {
  transform: translateY(3px);
  box-shadow: 0 1px 0 #1D4ED8;
}

/* Mini-Game 2: Reflex Duel Pad */
.reflex-pad {
  height: 200px;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.reflex-pad.wait-state {
  background: #FEE2E2;
  border: 3px dashed #EF4444;
}

.reflex-pad.ready-state {
  background: #DCFCE7;
  border: 4px solid #10B981;
  animation: pulseReady 0.4s infinite alternate;
}

@keyframes pulseReady {
  from { transform: scale(0.98); }
  to { transform: scale(1.02); }
}

.reflex-pad.success-state {
  background: #FEF08A;
  border: 4px solid #EAB308;
}

.reflex-pad.error-state {
  background: #F87171;
  border: 4px solid #B91C1C;
  color: white;
}

.reflex-icon {
  font-size: 48px;
}

.reflex-msg {
  font-size: 22px;
  font-weight: 900;
}

.reflex-sub {
  font-size: 12px;
  color: #64748B;
}

/* Mini-Game 3: Balloon Mash Arena */
.balloon-arena {
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lego-balloon {
  font-size: 54px;
  transition: transform 0.08s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.mash-btn {
  width: 100%;
  padding: 16px;
  font-size: 18px;
  font-weight: 900;
  color: white;
  background: linear-gradient(135deg, #EC4899, #DB2777);
  border: none;
  border-radius: 14px;
  box-shadow: 0 5px 0 #9D174D;
  cursor: pointer;
}

.mash-btn:active {
  transform: translateY(4px);
  box-shadow: 0 1px 0 #9D174D;
}


/* =================== STYLE CSS =================== */
/* ==========================================================================
   PartyCraft: Wonder Board - Modern UI/UX Design System
   Inspired by Lego Party, Mario Party & ui-ux-pro-max principles
   ========================================================================== */

:root {
  --font-main: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --bg-gradient: linear-gradient(135deg, #EFF6FF 0%, #F5F3FF 50%, #FFF1F2 100%);
  --surface-card: #FFFFFF;
  --surface-card-trans: rgba(255, 255, 255, 0.92);
  --border-color: #E2E8F0;
  
  --lego-red: #EF4444;
  --lego-blue: #3B82F6;
  --lego-yellow: #FBBF24;
  --lego-green: #10B981;
  --lego-purple: #8B5CF6;
  --lego-pink: #EC4899;
  --text-dark: #0F172A;
  --text-muted: #64748B;
  
  --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
  --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.08);
  --shadow-clay: 0 8px 0px rgba(0, 0, 0, 0.12), 0 16px 24px rgba(0, 0, 0, 0.08);
  --radius-lg: 22px;
  --radius-md: 16px;
  --radius-sm: 10px;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

body {
  font-family: var(--font-main);
  background: var(--bg-gradient);
  color: var(--text-dark);
  min-height: 100vh;
  overflow-x: hidden;
  position: relative;
}

/* Screen visibility utility */
.screen {
  width: 100%;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: opacity 0.3s ease;
}

.hidden {
  display: none !important;
}

/* ==========================================================================
   HOME SCREEN
   ========================================================================== */

.home-container {
  max-width: 960px;
  margin: 0 auto;
  padding: 24px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 28px;
}

.home-hero {
  text-align: center;
  position: relative;
}

.hero-badge {
  display: inline-block;
  background: #FEF08A;
  color: #854D0E;
  font-weight: 800;
  font-size: 13px;
  padding: 6px 16px;
  border-radius: 20px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(234, 179, 8, 0.25);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.hero-title {
  font-size: clamp(34px, 7vw, 56px);
  font-weight: 900;
  color: #1E293B;
  line-height: 1.1;
  text-shadow: 0 3px 0 #CBD5E1;
}

.hero-title span {
  color: var(--lego-red);
}

.hero-subtitle {
  font-size: 17px;
  color: var(--text-muted);
  margin-top: 8px;
}

/* ==========================================================================
   INTERACTIVE CHARACTER STUDIO (Home Screen)
   ========================================================================== */

.char-studio-card {
  background: var(--surface-card);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: 0 16px 36px -10px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(226, 232, 240, 0.8), inset 0 2px 4px rgba(255, 255, 255, 0.9);
  border: 3px solid #E2E8F0;
  width: 100%;
  max-width: 720px;
  box-sizing: border-box;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 18px;
  position: relative;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.studio-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  box-sizing: border-box;
  border-bottom: 2px dashed #E2E8F0;
  padding-bottom: 12px;
}

.studio-badge {
  font-size: 13px;
  font-weight: 900;
  letter-spacing: 0.5px;
  color: #4338CA;
  background: #EEF2FF;
  padding: 5px 14px;
  border-radius: 12px;
  box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.8), 0 2px 4px rgba(67, 56, 202, 0.08);
}

.studio-main-row {
  display: flex;
  gap: 24px;
  align-items: flex-start;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  overflow: hidden;
}

@media (max-width: 640px) {
  .studio-main-row {
    flex-direction: column;
    align-items: center;
  }
}

.studio-preview-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  min-width: 120px;
  flex-shrink: 0;
}

.studio-avatar-glow {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #FFFFFF, #F1F5F9);
  border: 4px solid var(--lego-blue);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  box-shadow: 0 8px 24px rgba(59, 130, 246, 0.28), inset 0 2px 6px rgba(0, 0, 0, 0.08);
  transition: border-color 0.25s, box-shadow 0.25s;
}

.studio-avatar-box {
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.12));
}

.studio-hat-badge {
  position: absolute;
  top: -6px;
  right: -4px;
  width: 32px;
  height: 32px;
  background: #FFFFFF;
  border: 2px solid #CBD5E1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.18);
  animation: pulseHat 2s infinite ease-in-out;
}

@keyframes pulseHat {
  0%, 100% { transform: scale(1) rotate(0deg); }
  50% { transform: scale(1.1) rotate(5deg); }
}

.studio-xp-pill {
  font-size: 11px;
  font-weight: 800;
  color: #64748B;
  background: #F1F5F9;
  padding: 3px 10px;
  border-radius: 10px;
  border: 1px solid #E2E8F0;
}

.studio-controls-col {
  flex: 1 1 0;
  min-width: 0;
  max-width: 100%;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-sizing: border-box;
  overflow: hidden;
}

.studio-input-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.studio-label {
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #64748B;
  display: flex;
  align-items: center;
  gap: 4px;
}

.studio-text-input {
  width: 100%;
  max-width: 280px;
  box-sizing: border-box;
  padding: 10px 14px;
  font-family: var(--font-main);
  font-size: 15px;
  font-weight: 800;
  color: #1E293B;
  background: #F8FAFC;
  border: 2px solid #CBD5E1;
  border-radius: var(--radius-sm);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

.studio-text-input:focus {
  background: #FFFFFF;
  border-color: #3B82F6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.studio-chips-carousel {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  max-width: 100%;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  padding: 2px 0 4px 0;
}

.studio-chips-carousel::-webkit-scrollbar {
  display: none;
}

.studio-chip-btn {
  padding: 6px 12px;
  border-radius: 12px;
  border: 2px solid #E2E8F0;
  background: #FFFFFF;
  font-family: var(--font-main);
  font-size: 12px;
  font-weight: 800;
  color: #475569;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  flex-shrink: 0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}

.studio-chip-btn:hover {
  border-color: #94A3B8;
  background: #F8FAFC;
  transform: translateY(-1px);
}

.studio-chip-btn.active {
  border-color: #3B82F6;
  background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
  color: #1D4ED8;
  box-shadow: 0 3px 8px rgba(59, 130, 246, 0.25);
  transform: translateY(-1px);
}

.studio-colors-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  max-width: 100%;
  width: 100%;
  box-sizing: border-box;
  padding: 4px 0 6px 0;
}

.studio-color-circle {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  cursor: pointer;
  border: 3px solid #FFFFFF;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
  transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.18s;
  flex-shrink: 0;
}

.studio-color-circle:hover {
  transform: scale(1.18);
}

.studio-color-circle.active {
  transform: scale(1.25);
  box-shadow: 0 0 0 3px #1E293B, 0 4px 8px rgba(0,0,0,0.3);
}

/* Profile Summary Clay Card */
.profile-card {
  background: var(--surface-card);
  border-radius: var(--radius-lg);
  padding: 20px 24px;
  box-shadow: var(--shadow-clay);
  border: 3px solid #F1F5F9;
  width: 100%;
  max-width: 580px;
  display: flex;
  align-items: center;
  gap: 20px;
  position: relative;
}

.profile-avatar-circle {
  width: 74px;
  height: 74px;
  border-radius: 50%;
  background: #F8FAFC;
  border: 3px solid var(--lego-blue);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
  overflow: hidden;
}

.profile-details {
  flex: 1;
}

.profile-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.profile-name {
  font-size: 20px;
  font-weight: 800;
  color: var(--text-dark);
}

.profile-level-badge {
  background: var(--lego-purple);
  color: white;
  font-weight: 800;
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 12px;
}

.profile-title-text {
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 600;
  margin-top: 2px;
}

.xp-bar-container {
  margin-top: 10px;
}

.xp-bar-bg {
  width: 100%;
  height: 12px;
  background: #E2E8F0;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.15);
}

.xp-bar-fill {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #FBBF24, #F59E0B);
  border-radius: 6px;
  transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.xp-label-row {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 700;
  margin-top: 4px;
}

/* Home Action Grid (Top 2 Cards) */
.home-actions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  width: 100%;
  max-width: 720px;
}

@media (max-width: 640px) {
  .home-actions-grid {
    grid-template-columns: 1fr;
  }
}

/* Secondary Actions Grid (Botlarla Oyna, Nasıl Oynanır, Ayarlar) */
.home-secondary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  width: 100%;
  max-width: 720px;
}

@media (max-width: 640px) {
  .home-secondary-grid {
    grid-template-columns: 1fr;
  }
}

.action-card-btn {
  background: var(--surface-card);
  border-radius: var(--radius-md);
  padding: 20px 16px;
  box-shadow: var(--shadow-clay);
  border: 3px solid #E2E8F0;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  transition: all 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
  box-sizing: border-box;
}

.action-card-btn:hover {
  transform: translateY(-4px);
  border-color: var(--lego-blue);
}

.action-card-btn:active {
  transform: translateY(2px);
  box-shadow: 0 3px 0 rgba(0, 0, 0, 0.12);
}

.action-card-btn.primary {
  background: linear-gradient(145deg, #3B82F6, #2563EB);
  color: white;
  border-color: #1D4ED8;
}

.action-card-btn.emerald {
  background: linear-gradient(145deg, #10B981, #059669);
  color: white;
  border-color: #047857;
}

.action-card-btn.primary .action-title,
.action-card-btn.primary .action-desc,
.action-card-btn.emerald .action-title,
.action-card-btn.emerald .action-desc {
  color: white;
}

.action-icon {
  font-size: 34px;
}

.action-title {
  font-size: 17px;
  font-weight: 800;
}

.action-desc {
  font-size: 12px;
  color: var(--text-muted);
  line-height: 1.4;
}

/* Server Status Indicator Pill */
.server-status-pill {
  font-size: 12px;
  font-weight: 800;
  padding: 6px 16px;
  border-radius: 20px;
  background: #F1F5F9;
  color: #475569;
  border: 1px solid #CBD5E1;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: -6px;
  margin-bottom: 4px;
  transition: all 0.2s ease;
}

.server-status-pill.online {
  background: #ECFDF5;
  color: #065F46;
  border-color: #A7F3D0;
}

.server-status-pill.offline {
  background: #FEF3C7;
  color: #92400E;
  border-color: #FDE68A;
}

/* Lobby Share Link Bar */
.lobby-share-url {
  width: 100%;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 700;
  color: #1E293B;
  background: #F8FAFC;
  border: 2px dashed #CBD5E1;
  border-radius: 8px;
  margin-top: 10px;
  box-sizing: border-box;
  text-align: center;
  font-family: monospace;
}

.btn-kick-player {
  background: #FEE2E2;
  color: #DC2626;
  border: 1px solid #FCA5A5;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  font-size: 13px;
  font-weight: 900;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  margin-left: auto;
  transition: all 0.15s;
}

.btn-kick-player:hover {
  background: #EF4444;
  color: white;
  transform: scale(1.1);
}

/* Settings Toggle Rows */
.settings-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: 16px 0;
}

.settings-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: var(--radius-sm);
}

.settings-item-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.settings-item-title {
  font-size: 14px;
  font-weight: 800;
  color: #1E293B;
}

.settings-item-sub {
  font-size: 12px;
  color: #64748B;
}

.switch-toggle {
  position: relative;
  display: inline-block;
  width: 46px;
  height: 26px;
}

.switch-toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider-round {
  position: absolute;
  cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #CBD5E1;
  transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
  border-radius: 26px;
}

.slider-round:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
  border-radius: 50%;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

input:checked + .slider-round {
  background-color: #10B981;
}

input:checked + .slider-round:before {
  transform: translateX(20px);
}

/* Rules / How To Play Modal Styles */
.rules-container {
  max-height: 420px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-right: 4px;
  margin: 14px 0;
  text-align: left;
}

.rules-card {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 12px;
  padding: 12px 14px;
}

.rules-card-title {
  font-size: 14px;
  font-weight: 800;
  color: #1E293B;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}

.rules-card-body {
  font-size: 12px;
  line-height: 1.5;
  color: #475569;
}

/* Join Room Card */
.join-room-card {
  background: var(--surface-card);
  border-radius: var(--radius-md);
  padding: 18px 22px;
  box-shadow: var(--shadow-clay);
  border: 3px solid #E2E8F0;
  width: 100%;
  max-width: 480px;
  display: flex;
  gap: 12px;
}

.join-room-input {
  flex: 1;
  padding: 12px 16px;
  font-size: 16px;
  font-weight: 800;
  text-transform: uppercase;
  border: 2px solid #CBD5E1;
  border-radius: var(--radius-sm);
  outline: none;
  font-family: var(--font-main);
  text-align: center;
  letter-spacing: 2px;
}

.join-room-input:focus {
  border-color: var(--lego-blue);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.btn-pill {
  padding: 12px 24px;
  font-weight: 800;
  font-size: 15px;
  border-radius: var(--radius-sm);
  border: none;
  cursor: pointer;
  background: var(--lego-green);
  color: white;
  box-shadow: 0 4px 0 #059669;
  transition: transform 0.1s ease, box-shadow 0.1s ease;
  font-family: var(--font-main);
}

.btn-pill:active {
  transform: translateY(3px);
  box-shadow: 0 1px 0 #059669;
}

/* Floating Audio / Wardrobe Home Buttons */
.home-float-bar {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.btn-icon-round {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: 2px solid #CBD5E1;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  transition: all 0.15s ease;
}

.btn-icon-round:active {
  transform: scale(0.92);
}

.btn-icon-round.active {
  background: var(--lego-blue);
  color: white;
  border-color: #1D4ED8;
}

/* ==========================================================================
   LOBBY SCREEN
   ========================================================================== */

.lobby-container {
  max-width: 840px;
  margin: 0 auto;
  padding: 24px 16px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.lobby-header-card {
  background: var(--surface-card);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-clay);
  border: 3px solid #F1F5F9;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.room-code-tag {
  font-size: 32px;
  font-weight: 900;
  color: var(--lego-blue);
  letter-spacing: 3px;
}

.lobby-qr-box {
  width: 110px;
  height: 110px;
  border-radius: 12px;
  background: #F8FAFC;
  padding: 6px;
  border: 2px dashed #CBD5E1;
}

.lobby-qr-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.lobby-players-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
}

.lobby-player-card {
  background: var(--surface-card);
  border-radius: var(--radius-md);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: var(--shadow-sm);
  border: 2px solid #E2E8F0;
}

.lobby-player-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #F1F5F9;
  overflow: hidden;
}

.lobby-player-info {
  flex: 1;
}

.lobby-player-name {
  font-weight: 800;
  font-size: 16px;
}

.lobby-player-title {
  font-size: 12px;
  color: var(--text-muted);
}

.lobby-player-badge {
  font-size: 11px;
  font-weight: 700;
  background: #E0E7FF;
  color: #3730A3;
  padding: 3px 8px;
  border-radius: 8px;
}

.lobby-controls-bar {
  display: flex;
  gap: 14px;
  justify-content: flex-end;
}

/* ==========================================================================
   GAME SCREEN
   ========================================================================== */

.game-screen {
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #0B0F19;
}

/* Top Game HUD Bar */
.game-hud-top {
  height: 52px;
  background: rgba(15, 23, 42, 0.88);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  z-index: 10;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
  color: #FFFFFF;
}

.hud-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hud-round-pill {
  background: rgba(245, 158, 11, 0.2);
  color: #FBBF24;
  border: 1px solid rgba(245, 158, 11, 0.5);
  font-weight: 800;
  font-size: 13px;
  padding: 5px 12px;
  border-radius: 12px;
}

.hud-active-turn-box {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 800;
  color: #F8FAFC;
}

.hud-turn-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: 2px solid #38BDF8;
  background: #1E293B;
}
  background: #F8FAFC;
}

.hud-right-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-hud-leave {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #FEE2E2;
  color: #DC2626;
  border: 1.5px solid #FCA5A5;
  border-radius: 20px;
  padding: 6px 14px;
  font-family: var(--font-main);
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 2px 0 #F87171;
  transition: all 0.15s ease;
}

.btn-hud-leave:hover {
  background: #EF4444;
  color: #FFFFFF;
  border-color: #DC2626;
  transform: translateY(-1px);
}

.btn-hud-leave:active {
  transform: translateY(1px);
  box-shadow: none;
}

/* ==========================================================================
   TURN NOTIFICATION BANNER (Top Screen Unmissable Alert)
   ========================================================================== */

.hud-turn-alert-banner {
  display: none !important;
}

/* 4 Cities Selection Grid in Modal */
.map-selection-grid.four-cities {
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.map-card-box .map-sub {
  font-size: 11px;
  color: #64748B;
  font-weight: 600;
  margin-top: 2px;
}

/* Players HUD Carousel (Floating Glass Capsules over canvas) */
.players-hud-carousel {
  position: absolute;
  top: 58px;
  left: 14px;
  display: flex;
  gap: 8px;
  padding: 4px 0;
  overflow-x: auto;
  background: transparent;
  border-bottom: none;
  scrollbar-width: none;
  z-index: 8;
  pointer-events: auto;
}

.players-hud-carousel::-webkit-scrollbar {
  display: none;
}

.player-hud-card {
  flex-shrink: 0;
  background: rgba(15, 23, 42, 0.82);
  backdrop-filter: blur(14px);
  border-radius: 20px;
  padding: 5px 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1.5px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
  color: #FFFFFF;
  transition: all 0.2s ease;
}

.player-hud-card.active-turn {
  border-color: #F59E0B;
  background: rgba(30, 41, 59, 0.95);
  box-shadow: 0 0 16px rgba(245, 158, 11, 0.45);
  transform: translateY(-2px);
}

.player-hud-card .player-hud-name {
  color: #F8FAFC;
  font-weight: 700;
  font-size: 12px;
}

.player-hud-card .hud-stat-pill {
  color: #FDE047;
  font-weight: 800;
  font-size: 12px;
}

.hud-avatar-box {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  position: relative;
}

.hud-shield-badge,
.hud-bot-badge {
  position: absolute;
  bottom: -4px;
  right: -4px;
  font-size: 11px;
}

.hud-pname {
  font-size: 13px;
  font-weight: 800;
}

.hud-scores {
  display: flex;
  gap: 8px;
  font-size: 12px;
  font-weight: 700;
}

.hud-star {
  color: #D97706;
}

.hud-coin {
  color: #2563EB;
}

/* Board Arena Canvas */
.board-arena-container {
  flex: 1;
  position: relative;
  overflow: hidden;
  background: #0B0F19;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
}

.board-arena-container:active {
  cursor: grabbing;
}

#board-canvas {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

/* Bottom Floating Mobile Control Dock */
.game-bottom-dock {
  position: absolute;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  width: calc(100% - 32px);
  max-width: 660px;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(18px);
  border-radius: 36px;
  padding: 8px 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.14);
  display: flex;
  flex-direction: column;
  gap: 0;
  z-index: 20;
}

.dock-status-bar {
  display: none !important;
}

.dock-buttons-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.dock-secondary-btn.speed-btn {
  background: rgba(245, 158, 11, 0.15);
  color: #FBBF24;
  border-color: rgba(245, 158, 11, 0.4);
  font-weight: 900;
  min-width: 68px;
}

.dock-secondary-btn.speed-btn:hover {
  background: rgba(245, 158, 11, 0.3);
  border-color: #F59E0B;
}

/* Big Juicy "ZAR AT" Roll Button */
.btn-roll-dice {
  flex: 2;
  padding: 14px 20px;
  font-size: 18px;
  font-weight: 900;
  color: white;
  background: linear-gradient(135deg, #EF4444, #DC2626);
  border: none;
  border-radius: var(--radius-md);
  box-shadow: 0 5px 0 #991B1B, 0 8px 16px rgba(239, 68, 68, 0.35);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.15s ease;
}

.btn-roll-dice:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.btn-roll-dice:not(:disabled):active {
  transform: translateY(4px);
  box-shadow: 0 1px 0 #991B1B;
}

.pulse-turn {
  animation: turnPulse 1.4s infinite alternate ease-in-out;
}

@keyframes turnPulse {
  0% { transform: scale(1); box-shadow: 0 5px 0 #991B1B, 0 8px 16px rgba(239, 68, 68, 0.35); }
  100% { transform: scale(1.04); box-shadow: 0 5px 0 #991B1B, 0 14px 24px rgba(239, 68, 68, 0.55); }
}

.dock-secondary-btn {
  flex: 1;
  padding: 14px 10px;
  border-radius: var(--radius-md);
  border: 2px solid #E2E8F0;
  background: white;
  font-size: 13px;
  font-weight: 800;
  color: #1E293B;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  position: relative;
}

.dock-secondary-btn:active {
  transform: translateY(2px);
}

.btn-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  background: var(--lego-red);
  color: white;
  font-size: 11px;
  font-weight: 800;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Event Feed HUD (Corner Ticker) */
.hud-event-feed {
  position: absolute;
  top: 130px;
  right: 16px;
  width: 260px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  pointer-events: none;
  z-index: 15;
}

.hud-log-item {
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(6px);
  color: white;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.3;
  animation: slideInRight 0.3s ease;
}

.log-time {
  color: #94A3B8;
  font-size: 10px;
  margin-right: 4px;
}

@keyframes slideInRight {
  from { opacity: 0; transform: translateX(20px); }
  to { opacity: 1; transform: translateX(0); }
}

/* ==========================================================================
   MODALS & OVERLAYS
   ========================================================================== */

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 16px;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-card {
  background: var(--surface-card);
  border-radius: var(--radius-lg);
  padding: 24px;
  width: 100%;
  max-width: 520px;
  box-shadow: var(--shadow-clay);
  border: 3px solid #F1F5F9;
  max-height: 90vh;
  overflow-y: auto;
}

/* ==========================================================================
   3D SINGLE DIE ROLLER MODAL
   ========================================================================== */
.dice-roll-modal {
  max-width: 360px;
  text-align: center;
  background: radial-gradient(circle at 50% 20%, #FFFFFF, #F8FAFC);
  border: 4px solid #E2E8F0;
  box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.9);
  padding: 28px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
}

.dice-roller-title {
  font-size: 13px;
  font-weight: 900;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  color: #4338CA;
  background: #EEF2FF;
  padding: 6px 16px;
  border-radius: 14px;
}

.dice-single-wrapper {
  position: relative;
  width: 110px;
  height: 110px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.single-dice-box {
  width: 86px;
  height: 86px;
  background: #FFFFFF;
  border-radius: 20px;
  border: 3px solid #CBD5E1;
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.18), inset 0 2px 4px rgba(255, 255, 255, 0.9), inset 0 -4px 6px rgba(0, 0, 0, 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  transition: transform 0.2s ease;
}

.single-dice-box.rolling {
  animation: rollTumble 0.5s infinite ease-in-out;
}

.single-dice-box.landed {
  animation: dicePopLand 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes rollTumble {
  0% { transform: rotate(0deg) scale(0.9) translateY(0); }
  25% { transform: rotate(-25deg) scale(1.08) translateY(-14px); }
  50% { transform: rotate(20deg) scale(0.95) translateY(-6px); }
  75% { transform: rotate(-15deg) scale(1.05) translateY(-12px); }
  100% { transform: rotate(0deg) scale(0.9) translateY(0); }
}

@keyframes dicePopLand {
  0% { transform: scale(0.8) translateY(-10px); }
  60% { transform: scale(1.22) translateY(2px); }
  100% { transform: scale(1) translateY(0); }
}

.dice-pip-face {
  width: 62px;
  height: 62px;
  position: relative;
}

.pip {
  position: absolute;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 35%, #DC2626, #991B1B);
  box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.6), 0 2px 4px rgba(0, 0, 0, 0.25);
}

.pip-center { top: 24px; left: 24px; }
.pip-top-left { top: 4px; left: 4px; }
.pip-top-right { top: 4px; right: 4px; }
.pip-mid-left { top: 24px; left: 4px; }
.pip-mid-right { top: 24px; right: 4px; }
.pip-bottom-left { bottom: 4px; left: 4px; }
.pip-bottom-right { bottom: 4px; right: 4px; }

.dice-result-badge {
  font-size: 17px;
  font-weight: 900;
  color: #1E293B;
  background: #F1F5F9;
  border: 2px solid #CBD5E1;
  padding: 8px 20px;
  border-radius: 16px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.modal-title {
  font-size: 20px;
  font-weight: 800;
}

.modal-close-btn {
  background: #F1F5F9;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Wardrobe Customization Modal */
.wardrobe-tabs-section {
  margin-bottom: 18px;
}

.wardrobe-section-label {
  font-size: 14px;
  font-weight: 800;
  color: #475569;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.wardrobe-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 10px;
}

.wardrobe-item {
  border: 2px solid #E2E8F0;
  border-radius: 12px;
  padding: 10px 6px;
  text-align: center;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
}

.wardrobe-item.selected {
  border-color: var(--lego-blue);
  background: #EFF6FF;
  box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
}

.wardrobe-item.locked {
  opacity: 0.45;
  filter: grayscale(0.8);
  cursor: not-allowed;
}

.wardrobe-avatar-preview {
  width: 44px;
  height: 44px;
}

.wardrobe-dice-preview {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.wardrobe-item-name {
  font-size: 11px;
  font-weight: 700;
  color: #1E293B;
}

.wardrobe-item-tag {
  font-size: 10px;
  font-weight: 800;
  color: var(--lego-blue);
}

/* Shop Modal Items */
.shop-items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.shop-item-card {
  background: #F8FAFC;
  border: 2px solid #E2E8F0;
  border-radius: 14px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  box-sizing: border-box;
  overflow: hidden;
}

.shop-item-card.disabled {
  opacity: 0.5;
}

.shop-item-icon {
  font-size: 28px;
  color: var(--lego-yellow);
}

.shop-item-name {
  font-weight: 800;
  font-size: 13.5px;
  line-height: 1.25;
  color: #1E293B;
  word-break: break-word;
  max-width: 100%;
}

.shop-item-desc {
  font-size: 11px;
  color: #64748B;
  line-height: 1.35;
  word-break: break-word;
  max-width: 100%;
}

.shop-buy-btn {
  width: 100%;
  padding: 8px 10px;
  background: var(--lego-yellow);
  color: #78350F;
  border: none;
  border-radius: 8px;
  font-weight: 800;
  font-size: 12px;
  cursor: pointer;
  box-shadow: 0 2px 0 #D97706;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-sizing: border-box;
}

.shop-buy-btn:disabled {
  background: #CBD5E1;
  color: #64748B;
  box-shadow: none;
  cursor: not-allowed;
}

/* Inventory Modal */
.inventory-items-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.inventory-item-card {
  background: #F8FAFC;
  border: 2px solid #E2E8F0;
  border-radius: 14px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-sizing: border-box;
  overflow: hidden;
}

.inv-icon {
  font-size: 28px;
  flex-shrink: 0;
}

.inv-info {
  flex: 1;
  min-width: 0; /* Prevents text overflowing flex container */
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.inv-name {
  font-weight: 800;
  font-size: 14px;
  color: #1E293B;
  word-break: break-word;
  line-height: 1.25;
}

.inv-desc {
  font-size: 11.5px;
  color: #64748B;
  line-height: 1.35;
  word-break: break-word;
}

.inv-use-btn {
  padding: 8px 18px;
  background: var(--lego-green);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 800;
  font-size: 12.5px;
  cursor: pointer;
  box-shadow: 0 2px 0 #047857;
  flex-shrink: 0;
  white-space: nowrap;
}
  cursor: pointer;
  box-shadow: 0 2px 0 #059669;
}

.inv-use-btn:disabled {
  background: #CBD5E1;
  cursor: not-allowed;
  box-shadow: none;
}

/* Emote Radial / Grid Picker */
.emotes-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.emote-bubble-btn {
  background: #F8FAFC;
  border: 2px solid #E2E8F0;
  border-radius: 14px;
  padding: 12px 6px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.emote-bubble-btn:active {
  transform: scale(0.9);
}

.em-char {
  font-size: 28px;
}

.em-label {
  font-size: 11px;
  font-weight: 700;
  color: #475569;
}

/* Floating Emoji Reactions */
.floating-emote-bubble {
  position: fixed;
  bottom: 120px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 54px;
  z-index: 150;
  animation: floatUpFade 2.2s forwards cubic-bezier(0.2, 0.8, 0.2, 1);
  pointer-events: none;
}

@keyframes floatUpFade {
  0% { opacity: 0; transform: translate(-50%, 20px) scale(0.5); }
  20% { opacity: 1; transform: translate(-50%, -20px) scale(1.2); }
  70% { opacity: 1; transform: translate(-50%, -80px) scale(1); }
  100% { opacity: 0; transform: translate(-50%, -140px) scale(0.8); }
}

/* Victory Podium Modal */
.podium-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 16px;
}

.podium-rank-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  border-radius: 14px;
  background: #F8FAFC;
  border: 2px solid #E2E8F0;
}

.podium-rank-card.rank-1 {
  background: #FEF9C3;
  border-color: #EAB308;
  box-shadow: 0 4px 12px rgba(234, 179, 8, 0.25);
}

.podium-badge {
  font-weight: 900;
  font-size: 13px;
  width: 90px;
}

.podium-avatar {
  width: 44px;
  height: 44px;
}

.podium-pname {
  font-weight: 800;
  font-size: 15px;
  flex: 1;
}

.podium-score {
  font-weight: 700;
  font-size: 13px;
  color: #475569;
}

/* Confetti particles */
.confetti-piece {
  position: fixed;
  width: 10px;
  height: 14px;
  top: -20px;
  z-index: 99;
  pointer-events: none;
  animation: confettiFall linear infinite;
}

@keyframes confettiFall {
  0% { transform: translateY(0) rotate(0deg); opacity: 1; }
  100% { transform: translateY(105vh) rotate(720deg); opacity: 0.2; }
}

/* Toast Notifications */
.toast-container {
  position: fixed;
  top: 76px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 200;
  pointer-events: none;
}

.toast {
  background: rgba(15, 23, 42, 0.9);
  color: white;
  padding: 10px 20px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
  box-shadow: var(--shadow-md);
  animation: toastPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  text-align: center;
}

.toast-success { background: #059669; }
.toast-error { background: #DC2626; }

@keyframes toastPop {
  from { transform: scale(0.8); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.toast.fade-out {
  opacity: 0;
  transform: translateY(-10px);
  transition: all 0.4s ease;
}

/* Status icons under player HUD */
.hud-badges-row {
  display: flex;
  gap: 3px;
  position: absolute;
  bottom: -6px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(15, 23, 42, 0.75);
  border-radius: 8px;
  padding: 1px 4px;
}

.status-icon {
  font-size: 11px;
}

/* Floating Camera Controls on Board Arena */
.floating-cam-controls {
  position: absolute;
  bottom: 95px;
  left: 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 12;
}

.cam-btn {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #FFFFFF;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 900;
  cursor: pointer;
  color: #1E293B;
  transition: transform 0.1s ease;
}

.cam-btn:active {
  transform: scale(0.9);
}

/* Floating Speech Bubble Pop for Quick Chat */
.speech-bubble-pop {
  position: absolute;
  top: -46px;
  left: 50%;
  transform: translateX(-50%);
  background: #1E293B;
  color: white;
  font-size: 12px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 14px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  white-space: nowrap;
  pointer-events: none;
  z-index: 30;
  animation: speechPop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.speech-bubble-pop::after {
  content: '';
  position: absolute;
  bottom: -6px;
  left: 50%;
  transform: translateX(-50%);
  border-width: 6px 6px 0;
  border-style: solid;
  border-color: #1E293B transparent transparent;
}

@keyframes speechPop {
  from { opacity: 0; transform: translate(-50%, 10px) scale(0.7); }
  to { opacity: 1; transform: translate(-50%, 0) scale(1); }
}

/* Tile Inspector Popover */
.tile-inspector-card {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.ti-title {
  font-size: 20px;
  font-weight: 900;
}

.ti-desc {
  font-size: 14px;
  color: #475569;
  line-height: 1.4;
}

.ti-steps-badge {
  display: inline-block;
  background: #EFF6FF;
  color: #2563EB;
  font-weight: 800;
  font-size: 13px;
  padding: 6px 14px;
  border-radius: 10px;
  border: 1px solid #BFDBFE;
}

.ti-players-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
  margin-top: 6px;
}

.ti-player-pill {
  padding: 4px 10px;
  border-radius: 10px;
  border: 2px solid;
  font-size: 12px;
  font-weight: 800;
  background: #F8FAFC;
}

/* Trade Modals */
.trade-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 12px;
}

.trade-col {
  background: #F8FAFC;
  padding: 14px;
  border-radius: 14px;
  border: 2px solid #E2E8F0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.trade-col-title {
  font-weight: 800;
  font-size: 13px;
  color: #1E293B;
  text-transform: uppercase;
}

.trade-input-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.trade-input-row label {
  font-size: 11px;
  font-weight: 700;
  color: #64748B;
}

.trade-select, .trade-number-input {
  padding: 8px 10px;
  border-radius: 8px;
  border: 1.5px solid #CBD5E1;
  font-family: var(--font-main);
  font-weight: 700;
  outline: none;
}

/* Incoming Trade Modal */
.incoming-trade-card {
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.inc-trade-boxes {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 10px;
  background: #F8FAFC;
  padding: 16px;
  border-radius: 14px;
  border: 2px solid #E2E8F0;
}

.inc-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.inc-box-label {
  font-size: 11px;
  font-weight: 800;
  color: #64748B;
  text-transform: uppercase;
}

.inc-box-val {
  font-size: 14px;
  font-weight: 900;
  color: #1E293B;
}

/* Map Selection in Create Room */
.map-selection-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 18px;
}

.map-card-label {
  cursor: pointer;
}

.map-card-label input {
  display: none;
}

.map-card-box {
  background: #F8FAFC;
  border: 2px solid #E2E8F0;
  border-radius: 12px;
  padding: 12px 6px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  transition: all 0.15s ease;
}

.map-card-label input:checked + .map-card-box {
  border-color: var(--lego-blue);
  background: #EFF6FF;
  box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2);
}

.map-icon {
  font-size: 26px;
}

.map-name {
  font-size: 12px;
  font-weight: 800;
  color: #1E293B;
}

/* Lobby Map Switcher */
.lobby-map-switcher {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.lobby-map-btn {
  padding: 6px 12px;
  border-radius: 8px;
  border: 2px solid #CBD5E1;
  background: white;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}

.lobby-map-btn.active {
  background: var(--lego-blue);
  color: white;
  border-color: #1D4ED8;
}

/* Quick Chat Presets */
.chat-presets-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.chat-preset-btn {
  padding: 12px 16px;
  border-radius: 12px;
  border: 2px solid #E2E8F0;
  background: #F8FAFC;
  font-size: 14px;
  font-weight: 800;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease;
}

.chat-preset-btn:hover {
  background: #EFF6FF;
  border-color: var(--lego-blue);
}

/* ==========================================================================
   ROUND PROGRESS TRACKER & FINAL ROUND BANNER (Item 12)
   ========================================================================== */

.hud-round-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: #FFFFFF;
  border: 2px solid #E2E8F0;
  border-radius: var(--radius-sm);
  padding: 6px 14px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  min-width: 140px;
}

.hud-round-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.hud-round-title {
  font-size: 13px;
  font-weight: 900;
  color: #1E293B;
}

.hud-round-sub {
  font-size: 11px;
  font-weight: 800;
  color: #64748B;
  background: #F1F5F9;
  padding: 2px 8px;
  border-radius: 8px;
}

.hud-round-sub.final-pulse {
  background: #FEF3C7;
  color: #B45309;
  animation: pulseGold 1.5s infinite;
}

@keyframes pulseGold {
  0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
  50% { transform: scale(1.05); box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
}

.hud-round-bar {
  width: 100%;
  height: 6px;
  background: #E2E8F0;
  border-radius: 4px;
  overflow: hidden;
}

.hud-round-fill {
  height: 100%;
  background: linear-gradient(90deg, #3B82F6, #10B981, #F59E0B);
  border-radius: 4px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.final-round-banner {
  background: linear-gradient(135deg, #F59E0B, #DC2626);
  border: 2px solid #FDE047;
  border-radius: 14px;
  padding: 10px 20px;
  text-align: center;
  color: #FFFFFF;
  margin: 8px auto 0 auto;
  max-width: 480px;
  box-shadow: 0 8px 24px rgba(220, 38, 38, 0.35);
  animation: finalBannerPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes finalBannerPop {
  0% { transform: scale(0.85); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

.final-banner-glow {
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 0.8px;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.final-banner-sub {
  font-size: 12px;
  font-weight: 700;
  opacity: 0.95;
  margin-top: 2px;
}

/* ==========================================================================
   PROMINENT TILE EVENT CARD OVERLAY & PENALTIES (Items 3, 7, 11)
   ========================================================================== */

.tile-event-overlay {
  position: fixed;
  inset: 0;
  z-index: 1800;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  pointer-events: none;
  animation: fadeIn 0.2s ease-out;
}

.tile-event-card {
  background: #FFFFFF;
  border-radius: 24px;
  padding: 24px 32px;
  max-width: 420px;
  width: 90%;
  text-align: center;
  box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.35);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  animation: popEventCard 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes popEventCard {
  0% { transform: scale(0.6) translateY(20px); opacity: 0; }
  100% { transform: scale(1) translateY(0); opacity: 1; }
}

.tile-event-card.penalty-mode {
  border: 4px solid #EF4444;
  background: linear-gradient(180deg, #FEF2F2, #FFFFFF);
  box-shadow: 0 24px 48px rgba(239, 68, 68, 0.32);
}

.tile-event-card.reward-mode {
  border: 4px solid #10B981;
  background: linear-gradient(180deg, #ECFDF5, #FFFFFF);
  box-shadow: 0 24px 48px rgba(16, 185, 129, 0.28);
}

.tile-event-card.neutral-mode {
  border: 4px solid #3B82F6;
  background: linear-gradient(180deg, #EFF6FF, #FFFFFF);
  box-shadow: 0 24px 48px rgba(59, 130, 246, 0.25);
}

.tile-event-avatar {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  background: #F8FAFC;
  border: 3px solid #CBD5E1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.tile-event-icon {
  font-size: 44px;
  line-height: 1;
  filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.15));
  animation: pulseIcon 1.2s infinite ease-in-out;
}

@keyframes pulseIcon {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.15); }
}

.tile-event-title {
  font-size: 20px;
  font-weight: 900;
  color: #1E293B;
  letter-spacing: -0.3px;
}

.tile-event-desc {
  font-size: 14px;
  font-weight: 700;
  color: #475569;
  line-height: 1.4;
}

/* Screen shake for comedic disaster / penalty impact */
.screen-shake-anim {
  animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}

@keyframes shake {
  10%, 90% { transform: translate3d(-3px, 0, 0); }
  20%, 80% { transform: translate3d(5px, 0, 0); }
  30%, 50%, 70% { transform: translate3d(-6px, 0, 0); }
  40%, 60% { transform: translate3d(6px, 0, 0); }
}

/* ==========================================================================
   INTERACTIVE WHEEL OF FORTUNE (Item 16)
   ========================================================================== */

.mystery-roulette-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  max-width: 400px;
  width: 100%;
  box-sizing: border-box;
  text-align: center;
  padding: 24px 20px;
}

.roulette-wheel-wrapper {
  position: relative;
  width: 260px;
  height: 260px;
  margin: 16px auto 10px auto;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.roulette-pointer-holder {
  position: absolute;
  top: -4px;
  left: 50%;
  transform: translateX(-50%);
  transform-origin: 14px 9px;
  width: 28px;
  height: 36px;
  z-index: 15;
  pointer-events: none;
  filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.35));
  transition: transform 0.05s ease;
}

.roulette-pointer-svg {
  display: block;
}

.roulette-arrow {
  position: absolute;
  top: -4px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 26px;
  z-index: 10;
  pointer-events: none;
}

.roulette-canvas-disc {
  border-radius: 50%;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25), inset 0 0 0 5px #FFFFFF, 0 0 0 2px #CBD5E1;
  display: block;
}

.roulette-center-stud {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 35%, #FFFFFF, #E2E8F0);
  border: 3px solid #F59E0B;
  box-shadow: 0 4px 10px rgba(0,0,0,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  pointer-events: none;
}

.mystery-result-box {
  background: #FEF3C7;
  color: #92400E;
  font-size: 15px;
  font-weight: 800;
  padding: 10px 20px;
  border-radius: 14px;
  border: 2px solid #FCD34D;
  width: 100%;
  box-sizing: border-box;
}

/* ==========================================================================
   LANGUAGE SELECTOR
   ========================================================================== */
.lang-selector-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-bottom: 6px;
}

.lang-btn, .lang-pill-btn {
  background: #F1F5F9;
  border: 1.5px solid #CBD5E1;
  color: #475569;
  font-family: var(--font-main);
  font-size: 13px;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 20px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.lang-btn:hover, .lang-pill-btn:hover {
  background: #E2E8F0;
  color: #1E293B;
  transform: translateY(-1px);
}

.lang-btn.active, .lang-pill-btn.active {
  background: #3B82F6;
  border-color: #2563EB;
  color: #FFFFFF;
  box-shadow: 0 3px 8px rgba(37, 99, 235, 0.35);
}

.settings-lang-toggle {
  display: flex;
  gap: 8px;
}
  border: 2px solid #FCD34D;
  width: 100%;
  box-sizing: border-box;
}

/* ==========================================================================
   MINI-GAME STAKES & NEW MINI-GAMES STYLING (Items 1, 14, 15)
   ========================================================================== */

.mg-stakes-badge {
  background: linear-gradient(135deg, #EEF2FF, #E0E7FF);
  border: 2px solid #818CF8;
  color: #3730A3;
  font-size: 12px;
  font-weight: 900;
  padding: 6px 14px;
  border-radius: 12px;
  margin-bottom: 8px;
  display: inline-block;
  box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
}

.lucky-chests-row {
  display: flex;
  gap: 16px;
  justify-content: center;
  margin: 20px 0;
  width: 100%;
}

.lucky-chest-box {
  flex: 1;
  background: #F8FAFC;
  border: 3px solid #E2E8F0;
  border-radius: 18px;
  padding: 16px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  touch-action: none;
  user-select: none;
  transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), border-color 0.2s, background 0.2s, box-shadow 0.2s;
}

.lucky-chest-box:hover {
  transform: translateY(-4px) scale(1.05);
  border-color: #F59E0B;
  background: #FFFBEB;
  box-shadow: 0 8px 20px rgba(245, 158, 11, 0.2);
}

.lucky-chest-box.selected {
  border-color: #10B981;
  background: #ECFDF5;
  transform: scale(1.08);
  box-shadow: 0 12px 28px rgba(16, 185, 129, 0.3);
}

.chest-icon {
  font-size: 48px;
  line-height: 1;
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
}

.chest-label {
  font-size: 12px;
  font-weight: 800;
  color: #1E293B;
}

.memory-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  max-width: 280px;
  margin: 16px auto;
}

.mem-pad {
  height: 90px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  cursor: pointer;
  touch-action: none;
  user-select: none;
  border: 3px solid rgba(0, 0, 0, 0.1);
  transition: transform 0.1s, filter 0.15s, box-shadow 0.15s;
}

.pad-red { background: #EF4444; box-shadow: 0 6px 0 #B91C1C; }
.pad-blue { background: #3B82F6; box-shadow: 0 6px 0 #1D4ED8; }
.pad-green { background: #10B981; box-shadow: 0 6px 0 #047857; }
.pad-yellow { background: #FBBF24; box-shadow: 0 6px 0 #D97706; }

.mem-pad:active, .mem-pad.flash {
  transform: translateY(4px);
  filter: brightness(1.35);
  box-shadow: 0 2px 0 rgba(0, 0, 0, 0.2);
}

.mem-status {
  font-size: 14px;
  font-weight: 800;
  color: #64748B;
  margin-top: 10px;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .home-actions-grid {
    grid-template-columns: 1fr;
  }
  .trade-form-grid {
    grid-template-columns: 1fr;
  }
  .map-selection-grid {
    grid-template-columns: 1fr;
  }
  .game-bottom-dock {
    bottom: 8px;
    width: calc(100% - 16px);
    padding: 10px 12px;
  }
  .btn-roll-dice {
    font-size: 16px;
    padding: 12px 14px;
  }
  .lucky-chests-row {
    gap: 8px;
  }
  .lucky-chest-box {
    padding: 12px 6px;
  }
  .chest-icon {
    font-size: 38px;
  }
}

/* ==========================================================================
   LUCKY DIE & SWAP WAND INTERACTIVE MODALS
   ========================================================================== */
.lucky-die-btn {
  background: #F8FAFC;
  border: 2px solid #E2E8F0;
  border-radius: 14px;
  padding: 14px 10px;
  font-size: 22px;
  font-weight: 900;
  color: #1E293B;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  transition: all 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.lucky-die-btn:hover {
  background: #FEF3C7;
  border-color: #F59E0B;
  color: #B45309;
  transform: translateY(-3px) scale(1.05);
  box-shadow: 0 8px 16px -2px rgba(245, 158, 11, 0.25);
}
.lucky-die-btn span.die-dot {
  font-size: 11px;
  font-weight: 800;
  color: #64748B;
}

.swap-wand-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #F8FAFC;
  border: 2px solid #E2E8F0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.18s ease;
}
.swap-wand-card:hover {
  background: #EFF6FF;
  border-color: #3B82F6;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}
.swap-wand-avatar {
  font-size: 24px;
  margin-right: 10px;
}
.swap-wand-info {
  flex: 1;
  text-align: left;
  min-width: 0;
}
.swap-wand-name {
  font-weight: 800;
  font-size: 14px;
  color: #1E293B;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.swap-wand-sub {
  font-size: 11px;
  color: #64748B;
}
.swap-wand-action-btn {
  background: #8B5CF6;
  color: #FFFFFF;
  border: none;
  font-size: 12px;
  font-weight: 800;
  padding: 6px 12px;
  border-radius: 8px;
  pointer-events: none;
  flex-shrink: 0;
}


/* =================== PREMIUM "GAME NIGHT TABLE" LAYER =================== */
/* ==========================================================================
   PARTİ DÜNYASI — "Game Night Table" premium layer
   Loaded after style.css and board.css. Concept: the whole game sits on a
   dark plum felt table; panels are heavy card stock, actions are lacquered
   tokens, money and stars are brass. One memorable object: the lacquered dice.
   ========================================================================== */

@font-face {
  font-family: 'Bricolage Grotesque';
  font-style: normal;
  font-display: swap;
  font-weight: 200 800;
  src: url('data:font/woff2;base64,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') format('woff2-variations');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
@font-face {
  font-family: 'Bricolage Grotesque';
  font-style: normal;
  font-display: swap;
  font-weight: 200 800;
  src: url('data:font/woff2;base64,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') format('woff2-variations');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
@font-face {
  font-family: 'Figtree';
  font-style: normal;
  font-display: swap;
  font-weight: 300 900;
  src: url('data:font/woff2;base64,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') format('woff2-variations');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
@font-face {
  font-family: 'Figtree';
  font-style: normal;
  font-display: swap;
  font-weight: 300 900;
  src: url('data:font/woff2;base64,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') format('woff2-variations');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

:root {
  /* Table */
  --felt: #1E1530;
  --felt-2: #2A1E42;
  --felt-3: #3B2C5C;
  --felt-line: rgba(255, 236, 210, 0.10);
  /* Materials */
  --brass: #E8B44A;
  --brass-deep: #A97A22;
  --brass-soft: #F6DA9A;
  --card: #FFF4DE;
  --card-2: #F7E6C4;
  --card-edge: #E2C996;
  --ink: #2B1D12;
  --ink-muted: #6E5A45;
  --lacquer: #E5484D;
  --lacquer-deep: #A8262B;
  --teal: #2BB3A3;
  --teal-deep: #17786D;
  --on-felt: #F6EEFF;
  --on-felt-muted: #B9A9D6;

  --font-display: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
  --font-ui: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;

  /* Re-map the original tokens so untouched components follow the new materials */
  --font-main: var(--font-ui);
  --bg-gradient: var(--felt);
  --surface-card: var(--card);
  --surface-card-trans: rgba(255, 244, 222, 0.96);
  --border-color: var(--card-edge);
  --text-dark: var(--ink);
  --text-muted: var(--ink-muted);
  --lego-red: var(--lacquer);
  --lego-yellow: var(--brass);
  --lego-green: var(--teal);

  /* Physical depth: a hard bottom edge (card thickness) + a soft table shadow */
  --edge-card: 0 3px 0 var(--card-edge), 0 18px 36px -12px rgba(8, 3, 18, 0.65);
  --edge-token: 0 4px 0 var(--lacquer-deep), 0 10px 22px -8px rgba(8, 3, 18, 0.7);
  --shadow-sm: 0 2px 0 rgba(0, 0, 0, 0.18), 0 8px 18px -10px rgba(8, 3, 18, 0.6);
  --shadow-md: var(--edge-card);
  --shadow-clay: var(--edge-card);
  --ease-out: cubic-bezier(0.22, 1, 0.36, 1);
}

/* --------------------------------------------------------------------------
   Table surface
   -------------------------------------------------------------------------- */
html, body {
  background: var(--felt);
  color: var(--on-felt);
  font-family: var(--font-ui);
}

body {
  /* Felt: soft vignette + a fine woven grain so the table never reads as flat UI */
  background-color: var(--felt);
  background-image:
    radial-gradient(120% 90% at 50% 0%, rgba(124, 84, 190, 0.22) 0%, transparent 60%),
    radial-gradient(80% 60% at 50% 120%, rgba(0, 0, 0, 0.45) 0%, transparent 70%),
    repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.012) 0 2px, transparent 2px 4px);
  background-attachment: fixed;
}

h1, h2, h3, .modal-title, .hero-title, .room-code-tag, .action-title {
  font-family: var(--font-display);
  letter-spacing: -0.01em;
}

::selection { background: var(--brass); color: var(--ink); }

:focus-visible {
  outline: 3px solid var(--brass);
  outline-offset: 3px;
  border-radius: 10px;
}

/* --------------------------------------------------------------------------
   Buttons: lacquered tokens with a real pressable edge
   -------------------------------------------------------------------------- */
.btn-pill,
.btn-pill[style] {
  font-family: var(--font-ui);
  font-weight: 800;
  letter-spacing: 0.01em;
  color: #FFF8EE !important;
  background: linear-gradient(180deg, #F0605F 0%, var(--lacquer) 55%, #D13A40 100%) !important;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), var(--edge-token) !important;
  border: none;
  border-radius: 14px;
  transition: transform 0.12s var(--ease-out), box-shadow 0.12s var(--ease-out), filter 0.15s;
}
.btn-pill:hover { filter: brightness(1.06); }
.btn-pill:active {
  transform: translateY(3px) scale(0.985);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 1px 0 var(--lacquer-deep), 0 4px 10px -6px rgba(8, 3, 18, 0.7) !important;
}

.btn-pill.btn-teal {
  background: linear-gradient(180deg, #3CC8B7 0%, var(--teal) 60%, #23998B 100%) !important;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 4px 0 var(--teal-deep), 0 10px 22px -8px rgba(8, 3, 18, 0.7) !important;
}
.btn-pill.btn-felt {
  color: var(--on-felt) !important;
  background: var(--felt-3) !important;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 4px 0 #160F25, 0 10px 22px -8px rgba(8, 3, 18, 0.7) !important;
}
.btn-pill.btn-brass {
  color: var(--ink) !important;
  background: linear-gradient(180deg, #F8D27A 0%, var(--brass) 60%, #D49B31 100%) !important;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 4px 0 var(--brass-deep), 0 10px 22px -8px rgba(8, 3, 18, 0.7) !important;
}

.btn-icon-round,
.cam-btn {
  background: var(--felt-2);
  color: var(--on-felt);
  border: 1px solid var(--felt-line);
  box-shadow: 0 3px 0 #120B1E;
}
.btn-icon-round:hover, .cam-btn:hover { background: var(--felt-3); }

/* --------------------------------------------------------------------------
   Home: hero on the felt, play actions first, character card beside it
   -------------------------------------------------------------------------- */
#screen-home { background: transparent; }

.home-container {
  box-sizing: border-box;
  width: 100%;
  max-width: 1120px;
  margin: 0 auto;
  padding: 28px 16px 48px;
}
.home-layout > * { min-width: 0; }

.lang-selector-bar { justify-content: flex-end; flex-wrap: wrap; }
.lang-btn {
  background: transparent !important;
  color: var(--on-felt-muted) !important;
  border: 1px solid var(--felt-line) !important;
  box-shadow: none !important;
}
.lang-btn.active {
  color: var(--ink) !important;
  background: var(--brass) !important;
  border-color: var(--brass) !important;
}

.home-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
  gap: 40px;
  align-items: start;
  margin-top: 18px;
}

.home-stage { display: flex; flex-direction: column; gap: 22px; padding-top: 18px; }

.home-hero { text-align: left; margin: 0; }
.hero-badge {
  display: inline-flex;
  background: transparent;
  color: var(--brass-soft);
  border: 1px solid rgba(232, 180, 74, 0.45);
  box-shadow: none;
  font-weight: 700;
  text-transform: none;
  letter-spacing: 0;
}
.hero-title {
  font-family: var(--font-display);
  font-size: clamp(52px, 8vw, 96px);
  line-height: 0.92;
  font-weight: 800;
  letter-spacing: -0.035em;
  color: var(--card);
  margin: 14px 0 12px;
  text-shadow: 0 2px 0 rgba(0, 0, 0, 0.25);
}
/* Title is one wordmark: no single accented word */
.hero-title span { color: inherit; background: none; -webkit-text-fill-color: currentColor; }
.hero-title::after {
  content: '';
  display: block;
  width: 88px;
  height: 6px;
  margin-top: 16px;
  border-radius: 3px;
  background: linear-gradient(90deg, var(--lacquer), var(--brass));
}
.hero-subtitle {
  color: var(--on-felt-muted);
  font-size: 18px;
  max-width: 34ch;
  line-height: 1.5;
}

/* Primary actions read as two large game tokens on the table */
.home-actions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin: 0;
}
.action-card-btn {
  text-align: left;
  align-items: flex-start;
  border-radius: 20px;
  border: none;
  background: var(--card);
  color: var(--ink);
  box-shadow: var(--edge-card);
  transition: transform 0.16s var(--ease-out), box-shadow 0.16s var(--ease-out);
}
.action-card-btn:hover { transform: translateY(-2px); }
.action-card-btn:active { transform: translateY(2px); box-shadow: 0 1px 0 var(--card-edge), 0 8px 16px -10px rgba(8, 3, 18, 0.6); }
.action-card-btn .action-title { color: var(--ink); font-size: 20px; }
.action-card-btn .action-desc { color: var(--ink-muted); }
.action-card-btn .action-icon { font-size: 26px; margin-bottom: 6px; }

.action-card-btn.primary {
  background: linear-gradient(165deg, #F2635F 0%, var(--lacquer) 45%, #C9343A 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 5px 0 var(--lacquer-deep), 0 22px 40px -16px rgba(229, 72, 77, 0.55);
}
.action-card-btn.emerald {
  background: linear-gradient(165deg, #3CC8B7 0%, var(--teal) 50%, #1F9284 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 5px 0 var(--teal-deep), 0 22px 40px -16px rgba(43, 179, 163, 0.45);
}
.action-card-btn.primary .action-title,
.action-card-btn.emerald .action-title { color: #FFF8EE; font-size: 22px; }
.action-card-btn.primary .action-desc,
.action-card-btn.emerald .action-desc { color: rgba(255, 248, 238, 0.85); }

/* Join a room sits right under play: same row, quiet */
.join-room-card {
  margin: 0;
  background: var(--felt-2);
  border: 1px solid var(--felt-line);
  box-shadow: none;
  border-radius: 16px;
  padding: 10px;
}
.join-room-input {
  background: var(--felt);
  color: var(--on-felt);
  border: 1px solid var(--felt-line);
  letter-spacing: 0.08em;
}
.join-room-input::placeholder { color: var(--on-felt-muted); letter-spacing: 0.02em; }

/* Secondary entries become a quiet row of links, not three more cards */
.home-secondary-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
}
.home-secondary-grid .action-card-btn {
  flex-direction: row;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: transparent;
  color: var(--on-felt);
  border: 1px solid var(--felt-line);
  box-shadow: none;
  border-radius: 999px;
}
.home-secondary-grid .action-card-btn:hover { background: var(--felt-2); transform: none; }
.home-secondary-grid .action-icon { font-size: 16px; margin: 0; }
.home-secondary-grid .action-title { font-size: 15px; color: var(--on-felt); font-family: var(--font-ui); font-weight: 700; }
.home-secondary-grid .action-desc { display: none; }

.server-status-pill {
  align-self: flex-start;
  background: transparent;
  color: var(--on-felt-muted);
  border: none;
  box-shadow: none;
  padding: 0;
  font-weight: 600;
}

.home-float-bar { position: static; justify-content: flex-start; margin: 0; }

/* Character card: a player mat in card stock */
.char-studio-card {
  margin: 0;
  background: var(--card);
  color: var(--ink);
  border: none;
  border-radius: 24px;
  box-shadow: var(--edge-card);
  position: relative;
}
.char-studio-card::before {
  /* A hairline inner frame, like a printed game card */
  content: '';
  position: absolute;
  inset: 8px;
  border: 1px solid rgba(169, 122, 34, 0.28);
  border-radius: 18px;
  pointer-events: none;
}
.studio-badge {
  background: transparent;
  color: var(--ink);
  font-family: var(--font-display);
  font-size: 18px;
  text-transform: none;
  letter-spacing: 0;
  padding: 0;
}
.profile-level-badge {
  background: var(--ink);
  color: var(--brass-soft);
}
.studio-label {
  color: var(--ink-muted);
  text-transform: none;
  letter-spacing: 0;
  font-weight: 700;
}
.studio-text-input {
  background: #FFFBF2;
  border: 1.5px solid var(--card-edge);
  color: var(--ink);
}
.studio-text-input:focus { border-color: var(--brass-deep); box-shadow: 0 0 0 4px rgba(232, 180, 74, 0.25); }
.studio-chip-btn {
  background: #FFFBF2;
  border: 1.5px solid var(--card-edge);
  color: var(--ink);
  box-shadow: none;
}
.studio-chip-btn.active {
  background: var(--ink);
  border-color: var(--ink);
  color: var(--card);
}
.studio-avatar-glow { background: radial-gradient(circle, rgba(232, 180, 74, 0.25), transparent 70%); }
.studio-xp-pill { background: var(--card-2); color: var(--ink); }
.profile-title-text { color: var(--ink); }

/* Quieter home: no badge, no status line; the character card opens on demand */
.hero-badge,
.server-status-pill { display: none !important; }
.studio-header { display: flex; align-items: center; gap: 10px; }
.studio-header .studio-badge { margin-right: auto; }
.studio-edit-toggle {
  border: 1.5px solid var(--card-edge);
  background: #FFFBF2;
  color: var(--ink);
  font: 700 13px var(--font-ui);
  padding: 6px 12px;
  border-radius: 999px;
  cursor: pointer;
}
.studio-edit-toggle:hover { background: var(--card-2); }
.char-studio-card:not(.is-editing) .studio-input-group:not(:first-child) { display: none; }
.char-studio-card:not(.is-editing) .studio-main-row { align-items: center; }

/* Lobby: the selected thumbnail already says which map; the link is behind "Copy link" */
.lobby-share-url,
.lobby-map-badge { display: none !important; }

@media (max-width: 900px) {
  .home-layout { grid-template-columns: 1fr; gap: 28px; }
  .home-stage { padding-top: 0; }
}
@media (max-width: 560px) {
  .home-actions-grid { grid-template-columns: 1fr; }
  .hero-title { font-size: 56px; }
}

/* --------------------------------------------------------------------------
   Lobby
   -------------------------------------------------------------------------- */
#screen-lobby { background: transparent; }
.lobby-container h3,
.lobby-section-title { color: var(--on-felt); font-family: var(--font-display); }
.lobby-header-card,
.lobby-player-card {
  background: var(--card);
  color: var(--ink);
  border: none;
  box-shadow: var(--edge-card);
}
.lobby-eyebrow { font-size: 13px; font-weight: 700; color: var(--ink-muted); }
.room-code-tag {
  color: var(--ink);
  font-size: 44px;
  font-weight: 800;
  letter-spacing: 0.12em;
}
.lobby-map-badge { font-weight: 700; font-size: 14px; color: var(--ink-muted); margin-top: 2px; }
.lobby-map-btn {
  background: #FFFBF2;
  border: 1.5px solid var(--card-edge);
  color: var(--ink);
}
.lobby-map-btn.active { background: var(--ink); color: var(--card); border-color: var(--ink); }
.lobby-share-url { background: #FFFBF2; border-color: var(--card-edge); color: var(--ink-muted); }
.lobby-qr-box { background: #FFFBF2; border-color: var(--card-edge); }
.lobby-qr-caption { font-size: 12px; font-weight: 700; color: var(--ink-muted); margin-top: 4px; text-align: center; }
.lobby-actions-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.lobby-player-name { color: var(--ink); }
.lobby-player-title { color: var(--ink-muted); }
.lobby-player-badge { background: var(--ink); color: var(--brass-soft); }
.btn-pill.btn-start { font-size: 17px; padding: 14px 28px; }

/* --------------------------------------------------------------------------
   In-game HUD: brass numbers on card stock, dark felt chrome
   -------------------------------------------------------------------------- */
.game-screen { background: var(--felt); }

.hud-round-card,
.player-hud-card,
.hud-active-turn-box,
.hud-round-pill,
#hud-map-name {
  background: rgba(30, 21, 48, 0.86) !important;
  color: var(--on-felt) !important;
  border: 1px solid var(--felt-line);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  box-shadow: 0 10px 24px -14px rgba(0, 0, 0, 0.8);
}
.hud-round-title, .hud-pname { color: var(--on-felt); font-family: var(--font-display); }
.hud-round-sub { color: var(--on-felt-muted); }
.hud-round-bar { background: rgba(255, 255, 255, 0.08); }
.hud-round-fill { background: linear-gradient(90deg, var(--lacquer), var(--brass)); }
.hud-scores { font-variant-numeric: tabular-nums; }
.hud-star, .hud-coin { color: var(--brass-soft); font-weight: 800; }
.player-hud-card.active-turn {
  border-color: var(--brass) !important;
  box-shadow: 0 0 0 2px rgba(232, 180, 74, 0.35), 0 14px 30px -14px rgba(232, 180, 74, 0.55);
}
.player-hud-card.is-me { box-shadow: inset 3px 0 0 var(--brass), 0 10px 24px -14px rgba(0, 0, 0, 0.8); }

/* Quiet HUD: one status card, slim player cards, nothing duplicated */
#hud-map-name,
.hud-active-turn-box,
.hud-round-sub,
.hud-badges-row,
.hud-event-feed,
#btn-hud-qr,
.dock-btn-label,
.dock-status-bar { display: none !important; }
/* Header is no longer a bar: just floating chips over the map */
.game-hud-top {
  background: transparent !important;
  border-bottom: none !important;
  box-shadow: none !important;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  height: 56px;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 12;
  pointer-events: none;
}
.game-hud-top > * { pointer-events: auto; }
.hud-round-card {
  flex-direction: row !important;
  align-items: center;
  gap: 10px !important;
  padding: 7px 14px !important;
  border-radius: 999px !important;
  min-width: 0 !important;
}
.hud-round-top { display: flex; align-items: center; }
.hud-round-card .hud-round-beads { margin-top: 0; flex-wrap: nowrap; }
.hud-city {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 15px;
  color: var(--card);
  line-height: 1;
}
.hud-round-title { font-family: var(--font-ui); font-size: 13px; font-weight: 600; color: var(--on-felt-muted); white-space: nowrap; }
.btn-roll-dice { white-space: nowrap; }
.hud-effects { margin-left: 4px; font-size: 11px; letter-spacing: 1px; }
.player-hud-card { padding-top: 6px; padding-bottom: 6px; }
.btn-hud-leave span:last-child { display: none; }
.floating-cam-controls { opacity: 0.55; transition: opacity 0.2s; }
.floating-cam-controls:hover, .floating-cam-controls:focus-within { opacity: 1; }
.cam-btn { width: 32px; height: 32px; font-size: 15px; }

.roll-kbd {
  margin-left: 6px;
  padding: 1px 6px;
  border-radius: 5px;
  font: 700 11px var(--font-ui);
  color: rgba(255, 248, 238, 0.85);
  background: rgba(0, 0, 0, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.25);
  vertical-align: 2px;
}
.btn-roll-dice.is-double { background: linear-gradient(180deg, #F8D27A 0%, var(--brass) 60%, #D49B31 100%); color: var(--ink); box-shadow: inset 0 1px 0 rgba(255,255,255,0.5), 0 5px 0 var(--brass-deep), 0 16px 30px -12px rgba(232, 180, 74, 0.6); }
.btn-roll-dice.is-double .roll-kbd { color: var(--ink); border-color: rgba(43, 29, 18, 0.3); }
.btn-roll-dice.is-fixed { background: linear-gradient(180deg, #3CC8B7 0%, var(--teal) 60%, #23998B 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.35), 0 5px 0 var(--teal-deep), 0 16px 30px -12px rgba(43, 179, 163, 0.6); }

/* One-line event ticker above the dock */
.fx-ticker {
  position: fixed;
  left: 50%;
  bottom: 104px;
  transform: translate(-50%, 8px);
  z-index: 70;
  max-width: min(560px, calc(100vw - 32px));
  padding: 8px 16px;
  border-radius: 999px;
  background: rgba(30, 21, 48, 0.88);
  border: 1px solid var(--felt-line);
  color: var(--on-felt);
  font: 600 13px var(--font-ui);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s var(--ease-out), transform 0.25s var(--ease-out);
}
.fx-ticker.is-visible { opacity: 1; transform: translate(-50%, 0); }

.btn-hud-leave {
  background: var(--felt-2);
  color: var(--on-felt);
  border: 1px solid var(--felt-line);
  box-shadow: 0 3px 0 #120B1E;
}

.game-bottom-dock {
  background: rgba(30, 21, 48, 0.88);
  border: 1px solid var(--felt-line);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow: 0 20px 40px -16px rgba(0, 0, 0, 0.85);
}
.dock-secondary-btn {
  background: var(--felt-2);
  color: var(--on-felt);
  border: 1px solid var(--felt-line);
  box-shadow: 0 3px 0 #120B1E;
}
.dock-secondary-btn:active { transform: translateY(2px); box-shadow: 0 1px 0 #120B1E; }
.btn-badge { background: var(--brass); color: var(--ink); }

.btn-roll-dice {
  font-family: var(--font-display);
  letter-spacing: 0.02em;
  color: #FFF8EE;
  background: linear-gradient(180deg, #F0605F 0%, var(--lacquer) 55%, #D13A40 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 5px 0 var(--lacquer-deep), 0 16px 30px -12px rgba(229, 72, 77, 0.6);
  transition: transform 0.12s var(--ease-out), box-shadow 0.12s var(--ease-out), filter 0.2s;
}
.btn-roll-dice:active {
  transform: translateY(4px);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 1px 0 var(--lacquer-deep), 0 6px 14px -8px rgba(229, 72, 77, 0.6);
}
.btn-roll-dice:disabled { filter: grayscale(0.85) brightness(0.6); }

.hud-event-feed .hud-log-item {
  background: rgba(30, 21, 48, 0.82);
  color: var(--on-felt);
  border: 1px solid var(--felt-line);
}
.log-time { color: var(--on-felt-muted); }

/* --------------------------------------------------------------------------
   Modals: cards dealt onto the table
   -------------------------------------------------------------------------- */
.modal-overlay {
  background: rgba(14, 8, 24, 0.62);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}
.modal-card {
  background: var(--card);
  color: var(--ink);
  border: none;
  border-radius: 24px;
  box-shadow: var(--edge-card);
}
.modal-title { color: var(--ink); }
.modal-close-btn { background: var(--card-2); color: var(--ink); }
.shop-item-card, .inventory-item-card, .wardrobe-item, .rules-card, .settings-item, .trade-col, .inc-box, .map-card-box {
  background: #FFFBF2;
  border-color: var(--card-edge);
  color: var(--ink);
}
.shop-buy-btn, .inv-use-btn {
  background: var(--ink);
  color: var(--card);
}

.toast {
  font-family: var(--font-ui);
  font-weight: 700;
  border-radius: 14px;
  box-shadow: 0 14px 30px -12px rgba(0, 0, 0, 0.7);
}

/* --------------------------------------------------------------------------
   The lacquered dice (3D) — the one memorable object
   -------------------------------------------------------------------------- */
.dice-roll-modal {
  background: transparent !important;
  box-shadow: none !important;
  overflow: visible;
  text-align: center;
}
.dice-roller-title {
  color: var(--card);
  background: none;
  font-family: var(--font-display);
  font-size: 22px;
  text-transform: none;
  letter-spacing: 0;
}
.dice-result-badge {
  display: inline-block;
  margin-top: 26px;
  background: var(--card);
  color: var(--ink);
  border-radius: 999px;
  padding: 10px 20px;
  font-weight: 800;
  box-shadow: var(--edge-card);
}

/* The legacy 2D die and the 3D stage toggle with the hidden attribute */
.dice-single-wrapper[hidden],
.dice3d-stage[hidden] { display: none !important; }

.dice3d-stage {
  display: flex;
  gap: 34px;
  justify-content: center;
  align-items: center;
  height: 170px;
  perspective: 900px;
  margin-top: 18px;
}
.dice3d-shadow-wrap { position: relative; width: 104px; height: 104px; }
.dice3d-shadow {
  position: absolute;
  left: 50%;
  bottom: -34px;
  width: 92px;
  height: 18px;
  margin-left: -46px;
  border-radius: 50%;
  background: radial-gradient(closest-side, rgba(0, 0, 0, 0.55), transparent);
}
.dice3d {
  position: absolute;
  inset: 0;
  transform-style: preserve-3d;
}
.dice3d-face {
  position: absolute;
  inset: 0;
  border-radius: 20px;
  background:
    radial-gradient(120% 120% at 30% 20%, #FF8A85 0%, var(--lacquer) 45%, #B92D33 100%);
  box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.18), inset 0 -6px 14px rgba(110, 10, 20, 0.45);
  display: grid;
  grid-template: repeat(3, 1fr) / repeat(3, 1fr);
  padding: 16px;
  backface-visibility: hidden;
}
.dice3d-face .pip3d {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  place-self: center;
  background: radial-gradient(circle at 35% 30%, #FFFDF6 0%, var(--card) 55%, #D9C49A 100%);
  box-shadow: inset 0 2px 2px rgba(0, 0, 0, 0.35);
}
.dice3d-face[data-face="1"] { transform: rotateY(0deg) translateZ(52px); }
.dice3d-face[data-face="6"] { transform: rotateY(180deg) translateZ(52px); }
.dice3d-face[data-face="3"] { transform: rotateY(90deg) translateZ(52px); }
.dice3d-face[data-face="4"] { transform: rotateY(-90deg) translateZ(52px); }
.dice3d-face[data-face="2"] { transform: rotateX(90deg) translateZ(52px); }
.dice3d-face[data-face="5"] { transform: rotateX(-90deg) translateZ(52px); }
.dice3d.is-brass .dice3d-face {
  background: radial-gradient(120% 120% at 30% 20%, #FFE7A8 0%, var(--brass) 45%, #B9862A 100%);
  box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.3), inset 0 -6px 14px rgba(110, 70, 10, 0.4);
}
.dice3d.is-brass .pip3d { background: radial-gradient(circle at 35% 30%, #6B4A2B, var(--ink)); }

/* --------------------------------------------------------------------------
   FX layer: floating numbers, turn sweep
   -------------------------------------------------------------------------- */
.fx-float {
  position: fixed;
  z-index: 9000;
  pointer-events: none;
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 22px;
  color: var(--brass-soft);
  text-shadow: 0 2px 0 rgba(0, 0, 0, 0.45);
  white-space: nowrap;
}
.fx-float.is-loss { color: #FF9A9A; }
.fx-float.is-star { color: #FFE38A; font-size: 26px; }

.fx-turn-sweep {
  position: fixed;
  left: 0;
  right: 0;
  top: 24%;
  z-index: 8500;
  pointer-events: none;
  display: flex;
  justify-content: center;
}
.fx-turn-sweep-band {
  padding: 10px 26px;
  border-radius: 999px;
  background: rgba(30, 21, 48, 0.9);
  border: 1px solid rgba(232, 180, 74, 0.55);
  box-shadow: 0 16px 40px -16px rgba(0, 0, 0, 0.8);
  font-family: var(--font-display);
  font-size: clamp(22px, 3.4vw, 30px);
  font-weight: 800;
  color: var(--card);
  letter-spacing: -0.01em;
}

/* City intro: a title card laid on the table when a match starts */
.fx-city-intro {
  position: fixed;
  inset: 0;
  z-index: 8800;
  display: grid;
  place-items: center;
  pointer-events: none;
  background: radial-gradient(60% 50% at 50% 50%, rgba(30, 21, 48, 0.78), rgba(30, 21, 48, 0.2) 70%, transparent);
}
.fx-city-intro-card { text-align: center; }
.fx-city-intro-kicker {
  font-family: var(--font-ui);
  font-weight: 600;
  font-size: 18px;
  color: var(--brass-soft);
}
.fx-city-intro-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(64px, 11vw, 140px);
  line-height: 0.95;
  letter-spacing: -0.03em;
  color: var(--card);
  text-shadow: 0 4px 0 rgba(0, 0, 0, 0.35), 0 24px 60px rgba(0, 0, 0, 0.5);
}
.fx-city-intro-rule {
  height: 6px;
  width: 120px;
  margin: 18px auto 0;
  border-radius: 3px;
  background: linear-gradient(90deg, var(--lacquer), var(--brass));
  transform-origin: center;
}

/* Player cards: rank medal + coins-to-next-star meter */
.player-hud-card { position: relative; }
.players-hud-carousel {
  padding-top: 9px;
  padding-left: 9px;
  box-sizing: border-box;
  max-width: calc(100vw - 28px);
  overflow-x: auto;
  scrollbar-width: none;
}
.game-hud-top { box-sizing: border-box; max-width: 100vw; }
.players-hud-carousel::-webkit-scrollbar { display: none; }

@media (max-width: 560px) {
  .game-bottom-dock { box-sizing: border-box; max-width: calc(100vw - 12px); }
  .dock-buttons-row { gap: 6px; overflow-x: auto; scrollbar-width: none; }
  .dock-buttons-row::-webkit-scrollbar { display: none; }
  .dock-btn-label { display: none; }
  .dock-secondary-btn { padding-left: 10px; padding-right: 10px; }
  .btn-roll-dice { font-size: 15px; padding-left: 14px; padding-right: 14px; }
  .hud-right-actions { gap: 6px; }
  #btn-hud-qr { display: none; }
  .roll-kbd { display: none; }
  .btn-hud-leave span:last-child { display: none; }
  .hud-event-feed { display: none; }
}
.hud-rank {
  position: absolute;
  top: -7px;
  left: -7px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-size: 11px;
  font-weight: 800;
  color: var(--ink);
  background: #CFC3B0;
  box-shadow: 0 2px 0 rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.6);
}
.hud-rank.rank-1 { background: linear-gradient(180deg, #FBE29A, var(--brass)); }
.hud-rank.rank-2 { background: linear-gradient(180deg, #F1F3F6, #B9C0CA); }
.hud-rank.rank-3 { background: linear-gradient(180deg, #F0C29A, #C07A45); }
/* Round beads */
.hud-round-beads {
  display: flex;
  gap: 4px;
  margin-top: 6px;
  flex-wrap: wrap;
  max-width: 220px;
}
.hud-round-beads i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.14);
}
.hud-round-beads i.done { background: var(--brass-deep); }
.hud-round-beads i.now { background: var(--brass); box-shadow: 0 0 0 3px rgba(232, 180, 74, 0.25); }
.hud-round-beads i.final { border-radius: 2px; transform: rotate(45deg); }
.hud-round-beads i.final:not(.done):not(.now) { background: rgba(229, 72, 77, 0.6); }
.hud-round-bar { display: none; }

/* City thumbnails on map pickers */
.map-preview-thumb {
  display: block;
  border-radius: 10px;
  box-shadow: 0 2px 0 rgba(0, 0, 0, 0.25);
  margin-bottom: 6px;
}
.lobby-map-switcher { flex-wrap: wrap; }
.lobby-map-btn.has-preview {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  padding: 6px 6px 8px;
  border-radius: 14px;
}
.lobby-map-btn.has-preview.active { box-shadow: 0 0 0 3px var(--brass); }
.map-card-box.has-preview { padding: 8px; }
.map-card-box.has-preview .map-icon { display: none; }
.map-card-label input:checked + .map-card-box { box-shadow: 0 0 0 3px var(--brass); }

.fx-confetti-canvas {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 9500;
}

/* --------------------------------------------------------------------------
   Mini-games: a bigger, framed stage
   -------------------------------------------------------------------------- */
#minigame-overlay .modal-card,
.minigame-card {
  width: min(720px, 94vw);
  max-width: none;
}
.coin-catch-arena, .balloon-arena, .reflex-pad {
  border-radius: 18px;
  border: 2px solid var(--card-edge);
}
.coin-catch-arena { height: 300px; background: linear-gradient(180deg, #2A1E42 0%, #3B2C5C 100%); }
.badge-score { background: var(--ink); color: var(--brass-soft); }
.touch-btn { background: var(--ink); color: var(--card); box-shadow: 0 4px 0 #120B07; }
.mg-stakes-badge { background: var(--card-2); color: var(--ink); }

/* --------------------------------------------------------------------------
   Respect reduced motion everywhere
   -------------------------------------------------------------------------- */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

  </style>
</head>
<body>

  <!-- ======================================================================
       1. HOME / START SCREEN (Redesigned with Live Character Customizer)
       ====================================================================== -->
  <div id="screen-home" class="screen">
    <div class="home-container">
      
      <!-- Top Language Selector Bar -->
      <div class="lang-selector-bar">
        <button type="button" class="lang-btn active" data-lang="tr">🇹🇷 TR</button>
        <button type="button" class="lang-btn" data-lang="en">🇬🇧 EN</button>
        <button type="button" class="lang-btn" data-lang="de">🇩🇪 DE</button>
        <button type="button" class="lang-btn" data-lang="es">🇪🇸 ES</button>
        <button type="button" class="lang-btn" data-lang="fr">🇫🇷 FR</button>
      </div>

      <div class="home-layout">
      <!-- Left: the table — title and the ways to start playing -->
      <section class="home-stage">
      <!-- Hero Header -->
      <header class="home-hero">
        <div class="hero-badge" data-i18n="hero_badge">🎲 Çok Oyunculu Dünya Turu Masa Oyunu</div>
        <h1 class="hero-title" data-i18n="hero_title">Parti <span>Dünyası</span></h1>
        <p class="hero-subtitle" data-i18n="hero_subtitle">İstanbul, Tokyo, Paris ve New York sokaklarında zar at, yarış ve eğlen!</p>
      </header>

      <!-- Main Play Action Cards -->
      <div class="home-actions-grid">
        <div class="action-card-btn primary" id="btn-quick-play" role="button" tabindex="0">
          <div class="action-icon">⚡</div>
          <div class="action-title" data-i18n="btn_quick_play">Hızlı Oyna</div>
          <div class="action-desc" data-i18n="btn_quick_play_desc">Beklemeden hemen 3 akıllı bot ile İstanbul haritasında partiye başla!</div>
        </div>

        <div class="action-card-btn emerald" id="btn-play-friends" role="button" tabindex="0">
          <div class="action-icon">🌐</div>
          <div class="action-title" data-i18n="btn_play_friends">Arkadaşlarınla Oyna</div>
          <div class="action-desc" data-i18n="btn_play_friends_desc">Tek tıkla oda kur, davet bağlantısı veya QR kod ile arkadaşlarını çağır!</div>
        </div>
      </div>

      <!-- Join Room Section -->
      <div class="join-room-card">
        <input type="text" id="input-room-code" class="join-room-input" placeholder="ODA KODU (ÖRN: ABCD)" data-i18n-placeholder="room_code_placeholder" maxlength="8">
        <button class="btn-pill" id="btn-join-room" data-i18n="btn_join_room">Odaya Katıl</button>
      </div>

      <!-- Secondary entries -->
      <div class="home-secondary-grid">
        <div class="action-card-btn" id="btn-play-bots" role="button" tabindex="0">
          <div class="action-icon">🤖</div>
          <div class="action-title" data-i18n="btn_play_bots">Botlarla Oyna</div>
          <div class="action-desc" data-i18n="btn_play_bots_desc">1 ila 3 bot seç ve antrenman yap!</div>
        </div>

        <div class="action-card-btn" id="btn-how-to-play" role="button" tabindex="0">
          <div class="action-icon">📖</div>
          <div class="action-title" data-i18n="btn_how_to_play">Nasıl Oynanır</div>
          <div class="action-desc" data-i18n="btn_how_to_play_desc">Oyun kuralları, karolar ve taktikler</div>
        </div>

        <div class="action-card-btn" id="btn-settings" role="button" tabindex="0">
          <div class="action-icon">⚙️</div>
          <div class="action-title" data-i18n="btn_settings">Ayarlar</div>
          <div class="action-desc" data-i18n="btn_settings_desc">Ses, müzik, dil ve grafik ayarları</div>
        </div>
      </div>

      <!-- Server Status Badge -->
      <div id="server-status-pill" class="server-status-pill">
        <span class="status-dot">●</span> <span id="server-status-text" data-i18n="server_status_checking">Sunucu Durumu: Bağlantı kontrol ediliyor...</span>
      </div>

      <!-- Auxiliary Bar (Wardrobe / Music) -->
      <div class="home-float-bar">
        <button class="btn-icon-round" id="btn-open-wardrobe" title="Tüm Gardırop & Ödüller" aria-label="Gardırop">
          <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </button>
        <button class="btn-icon-round" id="btn-home-music" title="Müzik Aç/Kapat" aria-label="Müzik">
          <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="22" x2="16" y1="9" y2="15"/><line x1="16" x2="22" y1="9" y2="15"/></svg>
        </button>
      </div>
      </section>

      <!-- Right: the player mat (character card) -->
      <!-- Interactive Character Studio Card -->
      <div class="char-studio-card">
        <div class="studio-header">
          <span class="studio-badge" data-i18n="studio_badge">Karakterin</span>
          <span class="profile-level-badge" id="profile-level">Lv. 1</span>
          <button type="button" class="studio-edit-toggle" id="btn-studio-edit" aria-expanded="false">Düzenle</button>
        </div>

        <div class="studio-main-row">
          <!-- Dynamic Live Character Token Box -->
          <div class="studio-preview-col">
            <div class="studio-avatar-glow" id="studio-avatar-glow">
              <div class="studio-avatar-box" id="profile-avatar-box">
                <!-- Dynamic SVG Avatar -->
              </div>
              <div class="studio-hat-badge" id="studio-hat-badge" title="Seçili Şapka">👑</div>
            </div>
            <div class="profile-title-text" id="profile-title">Acemi Gezgin</div>
            <div class="studio-xp-pill" id="profile-xp-text">0 / 140 XP</div>
          </div>

          <!-- Customizer Controls -->
          <div class="studio-controls-col">
            <!-- Name Input Field -->
            <div class="studio-input-group">
              <label class="studio-label" data-i18n="studio_name_label">Karakter Adın:</label>
              <input type="text" id="home-name-input" class="studio-text-input" placeholder="Adını yaz..." data-i18n-placeholder="studio_name_placeholder" maxlength="14">
            </div>

            <!-- Archetype Selector -->
            <div class="studio-input-group">
              <label class="studio-label" data-i18n="studio_model_label">Karakter Modeli:</label>
              <div class="studio-chips-carousel" id="home-avatar-chips">
                <!-- Injected dynamically -->
              </div>
            </div>

            <!-- Hat / Accessory Selector -->
            <div class="studio-input-group">
              <label class="studio-label" data-i18n="studio_hat_label">Şapka & Başlık:</label>
              <div class="studio-chips-carousel" id="home-hat-chips">
                <!-- Injected dynamically -->
              </div>
            </div>

            <!-- Color Palette Selector -->
            <div class="studio-input-group">
              <label class="studio-label" data-i18n="studio_color_label">Gövde & Çerçeve Rengi:</label>
              <div class="studio-colors-row" id="home-color-chips">
                <!-- Injected dynamically -->
              </div>
            </div>
          </div>
        </div>
      </div>

      </div><!-- /.home-layout -->

    </div>
  </div>

  <!-- ======================================================================
       2. LOBBY SCREEN
       ====================================================================== -->
  <div id="screen-lobby" class="screen hidden">
    <div class="lobby-container">
      
      <div class="lobby-header-card">
        <div style="flex: 1; min-width: 0;">
          <div class="lobby-eyebrow">Oda kodu</div>
          <div class="room-code-tag" id="lobby-room-code">----</div>
          <div id="lobby-map-badge" class="lobby-map-badge">
            🗺️ Harita: İstanbul - Boğazın İncisi
          </div>
          
          <div class="lobby-map-switcher">
            <button class="lobby-map-btn active" data-theme="istanbul">🇹🇷 İstanbul</button>
            <button class="lobby-map-btn" data-theme="tokyo">🇯🇵 Tokyo</button>
            <button class="lobby-map-btn" data-theme="paris">🇫🇷 Paris</button>
            <button class="lobby-map-btn" data-theme="newyork">🇺🇸 New York</button>
          </div>

          <input type="text" id="lobby-share-url" class="lobby-share-url" readonly value="" onclick="this.select()" title="Tıkla ve kopyala">

          <div class="lobby-actions-row">
            <button class="btn-pill btn-felt" id="btn-copy-link">
              📋 Bağlantıyı Kopyala
            </button>
            <button class="btn-pill btn-felt" id="btn-show-qr">
              📱 QR Kodu Göster
            </button>
          </div>
        </div>

        <div class="lobby-qr-box" id="lobby-qr-container">
          <div id="lobby-qr-svg"></div>
          <div class="lobby-qr-caption">Telefonunla tara</div>
        </div>
      </div>

      <h3 class="lobby-section-title">Oyuncular (<span id="lobby-player-count">...</span>/4)</h3>
      <div class="lobby-players-grid" id="lobby-players-list">
        <!-- Rendered dynamically -->
      </div>

      <div class="lobby-controls-bar">
        <button class="btn-pill btn-felt" id="btn-lobby-leave">
          🚪 Odadan Ayrıl
        </button>
        <button class="btn-pill btn-brass" id="btn-lobby-add-bot">
          🤖 Bot Ekle
        </button>
        <button class="btn-pill btn-teal btn-start" id="btn-lobby-start">
          🚀 Partiyi Başlat
        </button>
      </div>

    </div>
  </div>

  <!-- ======================================================================
       3. IN-GAME BOARD SCREEN
       ====================================================================== -->
  <div id="screen-game" class="screen game-screen hidden">
    
    <!-- Top HUD Navigation -->
    <header class="game-hud-top">
      <div class="hud-left">
        <!-- Round Progress Tracker Widget -->
        <div class="hud-round-card" id="hud-round-card">
          <div class="hud-round-top">
            <span class="hud-round-title" id="hud-round">Tur 1 / 10</span>
            <span class="hud-round-sub" id="hud-round-remaining">9 Tur Kaldı</span>
          </div>
          <div class="hud-round-bar"><div class="hud-round-fill" id="hud-round-fill" style="width: 10%;"></div></div>
        </div>

        <div id="hud-map-name" class="hud-round-pill" style="background:#E0E7FF; color:#3730A3;">İstanbul</div>
        <div class="hud-active-turn-box">
          <div class="hud-turn-avatar" id="hud-turn-avatar"></div>
          <span id="hud-turn-name">Oyuncu Sırası</span>
        </div>
      </div>

      <div class="hud-right-actions">
        <button class="btn-icon-round" id="btn-hud-qr" title="Arkadaşına QR Göster" style="width:38px;height:38px;">
          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/></svg>
        </button>
        <button class="btn-icon-round" id="btn-hud-music" title="Müzik" style="width:38px;height:38px;">
          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="22" x2="16" y1="9" y2="15"/><line x1="16" x2="22" y1="9" y2="15"/></svg>
        </button>
        <button class="btn-hud-leave" id="btn-hud-leave" title="Oyundan Ayrıl">
          <span>🚪</span> <span>Ayrıl</span>
        </button>
      </div>
    </header>

    <!-- Unmissable High-Visibility Turn Alert Banner (Disabled for clean minimal HUD) -->
    <div id="hud-turn-alert-banner" class="hud-turn-alert-banner" style="display:none;">
      <div class="turn-banner-icon" id="turn-banner-icon">🎲</div>
      <div class="turn-banner-text">
        <div class="turn-banner-title" id="turn-banner-title">Parti Yükleniyor...</div>
        <div class="turn-banner-sub" id="turn-banner-sub">Zar atmak veya eşya kullanmak için sıranı bekle.</div>
      </div>
    </div>

    <!-- Golden Final Round Celebration Banner -->
    <div id="final-round-banner" class="final-round-banner hidden">
      <div class="final-banner-glow">🚨 FİNAL TURU! 🚨</div>
      <div class="final-banner-sub">Bu son tur! Şampiyon bu turun sonunda taç giyecek!</div>
    </div>

    <!-- Prominent Broadcasted Tile Event Card / Modal -->
    <div id="tile-event-overlay" class="tile-event-overlay hidden">
      <div class="tile-event-card" id="tile-event-card">
        <div class="tile-event-avatar" id="tile-event-avatar"></div>
        <div class="tile-event-icon" id="tile-event-icon">💥</div>
        <div class="tile-event-title" id="tile-event-title">TUZAK!</div>
        <div class="tile-event-desc" id="tile-event-desc">Dikenli tuzağa düştün ve 5 altın kaybettin!</div>
      </div>
    </div>

    <!-- Players Scoreboard Bar -->
    <div class="players-hud-carousel" id="players-carousel">
      <!-- Player cards injected here -->
    </div>

    <!-- Interactive Board Map Canvas -->
    <main class="board-arena-container">
      <canvas id="board-canvas"></canvas>

      <!-- Floating Camera Controls -->
      <div class="floating-cam-controls">
        <button class="cam-btn" id="btn-cam-zoom-in" title="Yakınlaştır">+</button>
        <button class="cam-btn" id="btn-cam-zoom-out" title="Uzaklaştır">-</button>
        <button class="cam-btn" id="btn-reset-cam" title="Kamerayı Ortala">⟲</button>
      </div>
    </main>

    <!-- Event Feed Corner Ticker -->
    <div class="hud-event-feed" id="hud-event-feed"></div>

    <!-- Bottom Floating Mobile Dock -->
    <footer class="game-bottom-dock">
      <div class="dock-status-bar" id="dock-status-text" style="display:none;"></div>
      <div class="dock-buttons-row">
        <button class="dock-secondary-btn" id="btn-open-inventory" title="Çantam">
          <span>🎒</span> <span class="dock-btn-label">Çantam</span>
          <span class="btn-badge" id="inventory-badge">0</span>
        </button>

        <button class="dock-secondary-btn" id="btn-open-trade" title="Takas">
          <span>🤝</span> <span class="dock-btn-label">Takas</span>
        </button>

        <button class="btn-roll-dice pulse-turn" id="btn-roll-dice">
          <span>🎲</span> Zar at <kbd class="roll-kbd">Space</kbd>
        </button>

        <button class="dock-secondary-btn speed-btn" id="btn-toggle-speed" title="Oyun Hızı">
          <span>⚡</span> <span id="speed-label">1x</span>
        </button>

        <button class="dock-secondary-btn" id="btn-open-chat" title="Sohbet">
          <span>🗨️</span>
        </button>

        <button class="dock-secondary-btn" id="btn-open-emotes" title="İfade">
          <span>💬</span>
        </button>
      </div>
    </footer>

  </div>

  <!-- ======================================================================
       MODALS & OVERLAYS
       ====================================================================== -->

  <!-- 3D Tumbling Single Die Roll Animation Modal -->
  <div id="dice-roll-overlay" class="modal-overlay hidden">
    <div class="modal-card dice-roll-modal">
      <div class="dice-roller-title" id="dice-roller-name">Zar atılıyor</div>
      <!-- Legacy 2D die (kept for compatibility; hidden when the 3D dice are available) -->
      <div class="dice-single-wrapper" id="dice-legacy-wrapper">
        <div class="single-dice-box rolling" id="dice-cube-1">
          <div class="dice-pip-face" id="dice-pip-face">
            <span class="pip"></span>
          </div>
        </div>
      </div>
      <!-- 3D lacquered dice, driven by js/fx.js -->
      <div class="dice3d-stage" id="dice3d-stage" hidden></div>
      <div class="dice-result-badge" id="dice-result-value" aria-live="polite">Zar dönüyor...</div>
    </div>
  </div>

  <!-- Create Room Settings Modal -->
  <div id="modal-create-room" class="modal-overlay hidden">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">Oda & Harita Ayarları</h3>
        <button class="modal-close-btn" onclick="document.getElementById('modal-create-room').classList.add('hidden')">✕</button>
      </div>
      
      <div style="font-size: 13px; font-weight: 800; color: #475569; margin-bottom: 8px;">1. DÜNYA ŞEHRİNİ SEÇ:</div>
      <div class="map-selection-grid four-cities">
        <label class="map-card-label">
          <input type="radio" name="map-theme-select" value="istanbul" checked>
          <div class="map-card-box">
            <div class="map-icon">🇹🇷</div>
            <div class="map-name">İstanbul</div>
            <div class="map-sub">Boğaz & Galata</div>
          </div>
        </label>
        <label class="map-card-label">
          <input type="radio" name="map-theme-select" value="tokyo">
          <div class="map-card-box">
            <div class="map-icon">🇯🇵</div>
            <div class="map-name">Tokyo</div>
            <div class="map-sub">Neon & Skytree</div>
          </div>
        </label>
        <label class="map-card-label">
          <input type="radio" name="map-theme-select" value="paris">
          <div class="map-card-box">
            <div class="map-icon">🇫🇷</div>
            <div class="map-name">Paris</div>
            <div class="map-sub">Eyfel & Sen Nehri</div>
          </div>
        </label>
        <label class="map-card-label">
          <input type="radio" name="map-theme-select" value="newyork">
          <div class="map-card-box">
            <div class="map-icon">🇺🇸</div>
            <div class="map-name">New York</div>
            <div class="map-sub">Empire & Özgürlük</div>
          </div>
        </label>
      </div>

      <div style="font-size: 13px; font-weight: 800; color: #475569; margin-bottom: 8px;">2. TUR SAYISI:</div>
      <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px;">
        <label style="display:flex; align-items:center; gap: 10px; font-weight:700; cursor:pointer;">
          <input type="radio" name="round-select" value="5">
          <span>⚡ Hızlı Parti (5 Tur • ~5 Dakika)</span>
        </label>
        <label style="display:flex; align-items:center; gap: 10px; font-weight:700; cursor:pointer;">
          <input type="radio" name="round-select" value="10" checked>
          <span>🏆 Standart Parti (10 Tur • ~10-12 Dakika)</span>
        </label>
        <label style="display:flex; align-items:center; gap: 10px; font-weight:700; cursor:pointer;">
          <input type="radio" name="round-select" value="15">
          <span>🔥 Destansı Parti (15 Tur • ~18 Dakika)</span>
        </label>
      </div>

      <button class="btn-pill" id="btn-confirm-create" style="width: 100%;">🏰 Odayı Kur & Lobiye Geç</button>
    </div>
  </div>

  <!-- Interactive Tile Inspector Modal -->
  <div id="modal-tile-inspector" class="modal-overlay hidden">
    <div class="modal-card tile-inspector-card">
      <h3 class="ti-title" id="ti-tile-title">Karo Detayı</h3>
      <p class="ti-desc" id="ti-tile-desc">Açıklama</p>
      <div class="ti-steps-badge" id="ti-tile-steps">0 adım mesafede</div>
      
      <div style="width: 100%; text-align: left; margin-top: 10px;">
        <div style="font-size: 12px; font-weight: 800; color: #64748B;">BU KARODAKİ OYUNCULAR:</div>
        <div class="ti-players-list" id="ti-players-here"></div>
      </div>

      <button class="btn-pill" id="btn-close-tile-inspector" style="width: 100%; margin-top: 10px;">Anladım</button>
    </div>
  </div>

  <!-- Trade Propose Modal -->
  <div id="modal-trade" class="modal-overlay hidden">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">🤝 Oyuncular Arası Takas</h3>
        <button class="modal-close-btn" id="btn-close-trade">✕</button>
      </div>
      <p style="font-size: 13px; color: #64748B;">Bir rakip seç ve takas teklifini hazırla:</p>

      <div style="margin: 12px 0;">
        <label style="font-size: 12px; font-weight: 800; color: #475569;">Hedef Oyuncu:</label>
        <select id="trade-target-player" class="trade-select" style="width: 100%; margin-top: 4px;"></select>
      </div>

      <div class="trade-form-grid">
        <!-- My Offer -->
        <div class="trade-col">
          <div class="trade-col-title">Senin Teklifin</div>
          <div class="trade-input-row">
            <label>Altın:</label>
            <input type="number" id="trade-offer-coins" class="trade-number-input" min="0" value="0">
          </div>
          <div class="trade-input-row">
            <label>Eşya:</label>
            <select id="trade-offer-item" class="trade-select"></select>
          </div>
        </div>

        <!-- My Request -->
        <div class="trade-col">
          <div class="trade-col-title">Ondan İstediğin</div>
          <div class="trade-input-row">
            <label>Altın:</label>
            <input type="number" id="trade-request-coins" class="trade-number-input" min="0" value="0">
          </div>
          <div class="trade-input-row">
            <label>Eşya:</label>
            <select id="trade-request-item" class="trade-select"></select>
          </div>
        </div>
      </div>

      <button class="btn-pill" id="btn-send-trade-offer" style="width: 100%; margin-top: 18px;">
        Teklifi Gönder
      </button>
    </div>
  </div>

  <!-- Incoming Trade Offer Modal -->
  <div id="modal-incoming-trade" class="modal-overlay hidden">
    <div class="modal-card incoming-trade-card">
      <h3 style="font-size: 22px; font-weight: 900; color: #1E293B;">🤝 Gelen Takas Teklifi!</h3>
      <p><strong id="inc-sender-name">Oyuncu</strong> seninle bir takas yapmak istiyor:</p>

      <div class="inc-trade-boxes">
        <div class="inc-box">
          <div class="inc-box-label">Sana Teklifi</div>
          <div class="inc-box-val" id="inc-offer-details">-</div>
        </div>
        <div style="font-size: 20px;">⇄</div>
        <div class="inc-box">
          <div class="inc-box-label">Senden İstediği</div>
          <div class="inc-box-val" id="inc-request-details">-</div>
        </div>
      </div>

      <div style="display: flex; gap: 12px; margin-top: 10px;">
        <button class="btn-pill" id="btn-decline-trade" style="flex:1; background: #EF4444; box-shadow: 0 4px 0 #B91C1C;">
          Reddet
        </button>
        <button class="btn-pill" id="btn-accept-trade" style="flex:1; background: #10B981; box-shadow: 0 4px 0 #059669;">
          Kabul Et
        </button>
      </div>
    </div>
  </div>

  <!-- Quick Tactical Chat Modal -->
  <div id="modal-quick-chat" class="modal-overlay hidden">
    <div class="modal-card" style="max-width: 400px;">
      <div class="modal-header">
        <h3 class="modal-title">🗨️ Hızlı Sohbet & Taktik</h3>
        <button class="modal-close-btn" id="btn-close-quick-chat">✕</button>
      </div>
      <div class="chat-presets-list" id="quick-chat-options"></div>
    </div>
  </div>

  <!-- Wardrobe Customization Modal -->
  <div id="modal-wardrobe" class="modal-overlay hidden">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">Gardırop & Seviye Ödülleri</h3>
        <button class="modal-close-btn" onclick="document.getElementById('modal-wardrobe').classList.add('hidden')">✕</button>
      </div>

      <!-- Player Name Input -->
      <div class="wardrobe-tabs-section">
        <div class="wardrobe-section-label">Oyuncu Adın</div>
        <input type="text" id="wardrobe-name-input" class="join-room-input" style="width: 100%; text-align: left;" maxlength="14">
      </div>

      <!-- Titles Dropdown -->
      <div class="wardrobe-tabs-section">
        <div class="wardrobe-section-label">Unvanın</div>
        <select id="wardrobe-titles" style="width:100%; padding:10px; border-radius:10px; border:2px solid #CBD5E1; font-weight:700;"></select>
      </div>

      <!-- Avatars -->
      <div class="wardrobe-tabs-section">
        <div class="wardrobe-section-label">Açılan Karakterler</div>
        <div class="wardrobe-grid" id="wardrobe-avatars"></div>
      </div>

      <!-- Dice Skins -->
      <div class="wardrobe-tabs-section">
        <div class="wardrobe-section-label">Açılan Zar Temaları</div>
        <div class="wardrobe-grid" id="wardrobe-dice"></div>
      </div>

      <button class="btn-pill" id="btn-save-wardrobe" style="width: 100%;">Kaydet & Kuşan</button>
    </div>
  </div>

  <!-- Shop Modal -->
  <div id="modal-shop" class="modal-overlay hidden">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">🛍️ Parti Eşya Dükkanı</h3>
        <button class="modal-close-btn" id="btn-close-shop">✕</button>
      </div>
      <div class="shop-items-grid" id="shop-items-grid"></div>
    </div>
  </div>

  <!-- Inventory Modal -->
  <div id="modal-inventory" class="modal-overlay hidden">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">🎒 Çantan & Eşyaların</h3>
        <button class="modal-close-btn" id="btn-close-inventory">✕</button>
      </div>
      <div class="inventory-items-grid" id="inventory-items-grid"></div>
    </div>
  </div>

  <!-- Lucky Die Picker Modal -->
  <div id="modal-lucky-die" class="modal-overlay hidden">
    <div class="modal-card" style="max-width: 360px; text-align: center;">
      <div class="modal-header">
        <h3 class="modal-title">🎯 Şanslı Zarını Seç</h3>
        <button class="modal-close-btn" id="btn-close-lucky-die">✕</button>
      </div>
      <p style="font-size: 13px; color: #64748B; margin-bottom: 16px;">Sıradaki zar atışında gelmesini istediğin kesin sayıyı belirle:</p>
      <div class="lucky-die-grid" id="lucky-die-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 8px;">
        <!-- 1 to 6 injected dynamically -->
      </div>
    </div>
  </div>

  <!-- Swap Wand Target Picker Modal -->
  <div id="modal-swap-wand" class="modal-overlay hidden">
    <div class="modal-card" style="max-width: 380px;">
      <div class="modal-header">
        <h3 class="modal-title">🪄 Takas Asası - Hedef Seç</h3>
        <button class="modal-close-btn" id="btn-close-swap-wand">✕</button>
      </div>
      <p style="font-size: 13px; color: #64748B; margin-bottom: 14px;">Konumunu anında takas etmek istediğin rakip oyuncuyu seç:</p>
      <div class="swap-wand-list" id="swap-wand-list" style="display: flex; flex-direction: column; gap: 8px;">
        <!-- Rival player cards injected dynamically -->
      </div>
    </div>
  </div>

  <!-- Mystery Roulette Modal -->
  <div id="modal-mystery" class="modal-overlay hidden">
    <div class="modal-card mystery-roulette-container">
      <h3 style="font-weight: 900; font-size: 20px;" data-i18n="roulette_title">🎡 Şans Çarkı!</h3>
      <p id="mystery-player-text" style="color:#64748B; font-size:13px;">...</p>
      <div class="roulette-wheel-wrapper">
        <div class="roulette-pointer-holder" id="roulette-pointer">
          <svg width="28" height="36" viewBox="0 0 28 36" fill="none" class="roulette-pointer-svg">
            <defs>
              <filter id="pointer-shadow" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000000" flood-opacity="0.35"/>
              </filter>
            </defs>
            <g filter="url(#pointer-shadow)">
              <circle cx="14" cy="9" r="7" fill="#F59E0B" stroke="#B45309" stroke-width="2"/>
              <circle cx="14" cy="9" r="3.5" fill="#FEF3C7"/>
              <path d="M7 11 L21 11 L14 32 Z" fill="#DC2626" stroke="#991B1B" stroke-width="1.5" stroke-linejoin="round"/>
              <path d="M12 12 L14 28 L14 12 Z" fill="#FCA5A5" opacity="0.6"/>
            </g>
          </svg>
        </div>
        <canvas id="roulette-canvas" width="260" height="260" class="roulette-canvas-disc"></canvas>
        <div class="roulette-center-stud">🎲</div>
      </div>
      <div class="mystery-result-box" id="mystery-event-result" data-i18n="roulette_spinning">Çark dönüyor...</div>
    </div>
  </div>

  <!-- Emotes Modal -->
  <div id="modal-emotes" class="modal-overlay hidden">
    <div class="modal-card" style="max-width:380px;">
      <div class="modal-header">
        <h3 class="modal-title">💬 İfade Gönder</h3>
        <button class="modal-close-btn" id="btn-close-emotes">✕</button>
      </div>
      <div class="emotes-grid" id="emotes-grid"></div>
    </div>
  </div>

  <!-- Scannable LAN QR Modal -->
  <div id="modal-qr" class="modal-overlay hidden">
    <div class="modal-card text-center" style="max-width:360px; text-align:center;">
      <div class="modal-header">
        <h3 class="modal-title">📱 Mobilden Katıl</h3>
        <button class="modal-close-btn" id="btn-close-qr">✕</button>
      </div>
      <p style="font-size: 13px; color: #64748B; margin-bottom: 12px;">Aynı Wi-Fi ağındaki telefonun kamerasını buraya tut:</p>
      <div style="width:200px; height:200px; margin: 0 auto; background:#F8FAFC; padding:8px; border-radius:14px; border:2px dashed #CBD5E1;">
        <img id="lan-qr-image" src="" alt="LAN QR" style="width:100%; height:100%; object-fit:contain;">
      </div>
      <div id="lan-qr-link-text" style="font-size:11px; color:#94A3B8; word-break:break-all; margin: 12px 0;"></div>
      <button class="btn-pill" id="btn-copy-lan-link" style="width:100%;">Bağlantıyı Kopyala</button>
    </div>
  </div>

  <!-- Mini-Game Fullscreen Overlay -->
  <div id="minigame-overlay" class="modal-overlay hidden">
    <!-- Active mini-game renders here dynamically -->
  </div>

  <!-- Game Over Victory Podium Modal -->
  <div id="modal-gameover" class="modal-overlay hidden">
    <div class="modal-card text-center" style="max-width: 500px; text-align:center;">
      <h2 style="font-size: 26px; font-weight: 900; color: #1E293B;">🎉 PARTİ ŞAMPİYONU!</h2>
      <div id="go-level-up" class="hidden" style="background:#FEF9C3; color:#854D0E; font-weight:800; padding:10px; border-radius:10px; margin:10px 0;"></div>
      <div class="go-xp-section" id="go-xp-section" style="margin: 8px 0 16px 0;">
        <div id="go-xp-reward" style="font-weight:900; color:#2563EB; font-size:16px;"></div>
        <div class="go-xp-bar-wrap" style="width:100%; height:12px; background:#E2E8F0; border-radius:6px; overflow:hidden; margin:8px 0; border: 1px solid #CBD5E1;">
          <div class="go-xp-bar-fill" id="go-xp-bar-fill" style="width:0%; height:100%; background:linear-gradient(90deg, #3B82F6, #10B981); transition:width 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);"></div>
        </div>
        <div id="go-xp-detail" style="font-size:12px; font-weight:800; color:#64748B;"></div>
      </div>

      <div class="podium-list" id="go-podium-list">
        <!-- Ranks injected dynamically -->
      </div>

      <button class="btn-pill" id="btn-back-home" style="width: 100%; margin-top: 20px; padding: 14px;">
        Ana Menüye Dön
      </button>
    </div>
  </div>

  <!-- How to Play Rules Modal -->
  <div id="modal-how-to-play" class="modal-overlay hidden">
    <div class="modal-card" style="max-width: 540px;">
      <div class="modal-header">
        <h3 class="modal-title">📖 Nasıl Oynanır?</h3>
        <button class="modal-close-btn" id="btn-close-how-to-play">✕</button>
      </div>

      <div class="rules-container">
        <div class="rules-card">
          <div class="rules-card-title">🎯 Oyunun Amacı</div>
          <div class="rules-card-body">
            Belirlenen tur sayısı bitmeden önce en çok <strong>Yıldız (⭐)</strong> ve <strong>Altın (🪙)</strong> toplayan oyuncu partinin şampiyonu olur! 1 Yıldız, 20 altın değerindedir ve liderlikte en büyük önceliğe sahiptir.
          </div>
        </div>

        <div class="rules-card">
          <div class="rules-card-title">🎲 Sıra ve Hareket</div>
          <div class="rules-card-body">
            Sıra sana geldiğinde <strong>Zar At</strong> butonuna bas veya klavyeden <strong>SPACE</strong> tuşunu kullan. Zardaki sayı kadar saat yönünde ilerlersin. Başlangıç karosundan her geçişte <strong>+10 Altın</strong> ödülü kazanırsın!
          </div>
        </div>

        <div class="rules-card">
          <div class="rules-card-title">🗺️ Özel Karolar ve Etkileri</div>
          <div class="rules-card-body">
            • <strong>⭐ Yıldız Kaidesi:</strong> 20 altının varsa 1 Yıldız satın alırsın. Yıldız alındığında kaide haritada başka bir şehre/bölgeye ışınlanır!<br>
            • <strong>🪙 Altın Karosu:</strong> Üzerine bastığında sana +4 ile +8 altın kazandırır.<br>
            • <strong>🔥 Tuzaklar:</strong> Dikkat! Altın düşürürsün veya kodes cezası alırsın.<br>
            • <strong>🏦 Merkez Bankası:</strong> Havuzda biriken tüm altınları ilk basan oyuncu tek seferde silip süpürür!<br>
            • <strong>⚔️ Düello:</strong> Lider veya rakip oyuncularla altın mücadelesi.<br>
            • <strong>🛍️ Eşya Dükkanı:</strong> Çantana güçlü taktik eşyaları satın alabileceğin pazar yeri!
          </div>
        </div>

        <div class="rules-card">
          <div class="rules-card-title">🎒 Taktik Eşyaları</div>
          <div class="rules-card-body">
            • <strong>🎲🎲 Çift Zar (7🪙):</strong> Bir sonraki turda 2 zar birden atarsın (2-12 adım).<br>
            • <strong>🚀 Süper Roket (12🪙):</strong> Hedefine doğru direkt 7 adım uçar!<br>
            • <strong>🛡️ Manyetik Kalkan (8🪙):</strong> Tuzaklara ve altın çalma hamlelerine karşı 2 tur korur.<br>
            • <strong>🧲 Altın Mıknatısı (10🪙):</strong> Bütün rakiplerden 4'er altın çeker!<br>
            • <strong>🍌 Muz Kabuğu (5🪙):</strong> Bulunduğun karoya tuzak kur; basan 3 adım geri kayar!<br>
            • <strong>🪄 Takas Asası (15🪙):</strong> İstediğin bir rakiple haritadaki yerlerini takas et!
          </div>
        </div>

        <div class="rules-card">
          <div class="rules-card-title">🎮 Mini Oyunlar</div>
          <div class="rules-card-body">
            Özel anlarda başlayan refleks ve beceri mini oyunlarında (Altın Yağmuru, Zıplayan Balon, Hızlı Çekiç, Hafıza Kartları, Bomba Paslaşma) en yüksek skoru yaparak ekstra altınları kap!
          </div>
        </div>
      </div>

      <button class="btn-pill" id="btn-ok-how-to-play" style="width: 100%; margin-top: 6px;">Harika, Anladım!</button>
    </div>
  </div>

  <!-- Settings Modal -->
  <div id="modal-settings" class="modal-overlay hidden">
    <div class="modal-card" style="max-width: 440px;">
      <div class="modal-header">
        <h3 class="modal-title" data-i18n="settings_title">⚙️ Oyun Ayarları</h3>
        <button class="modal-close-btn" id="btn-close-settings">✕</button>
      </div>

      <div class="settings-list">
        <!-- Language Option -->
        <div class="settings-item">
          <div class="settings-item-label">
            <span class="settings-item-title" data-i18n="settings_lang_title">🌐 Dil / Language</span>
            <span class="settings-item-sub" data-i18n="settings_lang_sub">Arayüz ve oyun metinleri dili</span>
          </div>
          <div class="settings-lang-toggle">
            <button type="button" class="lang-pill-btn active" data-lang="tr">🇹🇷 TR</button>
            <button type="button" class="lang-pill-btn" data-lang="en">🇬🇧 EN</button>
            <button type="button" class="lang-pill-btn" data-lang="de">🇩🇪 DE</button>
            <button type="button" class="lang-pill-btn" data-lang="es">🇪🇸 ES</button>
            <button type="button" class="lang-pill-btn" data-lang="fr">🇫🇷 FR</button>
          </div>
        </div>

        <div class="settings-item">
          <div class="settings-item-label">
            <span class="settings-item-title" data-i18n="settings_sfx_title">🔊 Ses Efektleri</span>
            <span class="settings-item-sub" data-i18n="settings_sfx_sub">Zar, adım, buton ve olay sesleri</span>
          </div>
          <label class="switch-toggle">
            <input type="checkbox" id="setting-sfx" checked>
            <span class="slider-round"></span>
          </label>
        </div>

        <div class="settings-item">
          <div class="settings-item-label">
            <span class="settings-item-title" data-i18n="settings_music_title">🎵 Arka Plan Müziği</span>
            <span class="settings-item-sub" data-i18n="settings_music_sub">Web Audio sentezleyici parti müziği</span>
          </div>
          <label class="switch-toggle">
            <input type="checkbox" id="setting-music">
            <span class="slider-round"></span>
          </label>
        </div>

        <div class="settings-item">
          <div class="settings-item-label">
            <span class="settings-item-title" data-i18n="settings_dice_title">🎲 3D Zar Animasyonları</span>
            <span class="settings-item-sub" data-i18n="settings_dice_sub">Kapatılırsa zar sonucu beklemeden gelir</span>
          </div>
          <label class="switch-toggle">
            <input type="checkbox" id="setting-dice3d" checked>
            <span class="slider-round"></span>
          </label>
        </div>

        <div class="settings-item">
          <div class="settings-item-label">
            <span class="settings-item-title" data-i18n="settings_lowpower_title">⚡ Düşük Güç / Pil Tasarrufu</span>
            <span class="settings-item-sub" data-i18n="settings_lowpower_sub">Eski telefonlar için hafif grafik modu</span>
          </div>
          <label class="switch-toggle">
            <input type="checkbox" id="setting-lowpower">
            <span class="slider-round"></span>
          </label>
        </div>
      </div>

      <div style="border-top: 1px dashed #E2E8F0; padding-top: 14px; display: flex; flex-direction: column; gap: 8px;">
        <button class="btn-pill" id="btn-reset-profile" data-i18n="btn_reset_profile" style="background: #FEE2E2; color: #DC2626; box-shadow: 0 4px 0 #FCA5A5; font-size: 13px;">
          🔄 Oyuncu Profilini & Verilerini Sıfırla
        </button>
        <button class="btn-pill" id="btn-save-settings" data-i18n="btn_save_settings" style="width: 100%;">
          Ayarları Kaydet
        </button>
          Ayarları Kaydet
        </button>
      </div>
    </div>
  </div>

  <!-- Confirm Leave Game Modal -->
  <div id="modal-confirm-leave" class="modal-overlay hidden">
    <div class="modal-card" style="max-width: 400px; text-align: center;">
      <div style="font-size: 48px; margin-bottom: 8px;">🚪</div>
      <h3 class="modal-title" style="margin-bottom: 8px;" data-i18n="leave_modal_title">Oyundan Ayrıl</h3>
      <p style="color: #64748B; font-size: 13px; line-height: 1.5; margin-bottom: 20px;" data-i18n="leave_modal_desc">
        Mevcut oyundan ayrılmak ve ana menüye dönmek istediğinize emin misiniz? Çok oyunculu odada yerinizi bir bot devralacaktır.
      </p>
      <div style="display: flex; gap: 10px; justify-content: center;">
        <button class="btn-pill" id="btn-cancel-leave" data-i18n="btn_leave_cancel" style="background: #E2E8F0; color: #475569; box-shadow: 0 4px 0 #CBD5E1;">
          İptal / Oyuna Dön
        </button>
        <button class="btn-pill" id="btn-confirm-leave" data-i18n="btn_leave_confirm" style="background: #EF4444; color: white; box-shadow: 0 4px 0 #DC2626;">
          Evet, Ayrıl
        </button>
      </div>
    </div>
  </div>

  <!-- Floating Confetti Container -->
  <div id="confetti-container"></div>

  <!-- Toast Notification Container -->
  <div class="toast-container" id="toast-container"></div>

  <!-- Scripts -->
  <!-- Vendored libraries (bundled into index.html / index.php by build-single-file.js) -->
  <script>
/* =================== 0. VENDOR: GSAP (standard no-charge license) =================== */
/*!
 * GSAP 3.15.1
 * https://gsap.com
 * 
 * @license Copyright 2026, GreenSock. All rights reserved.
 * Subject to the terms at https://gsap.com/standard-license.
 * @author: Jack Doyle, jack@greensock.com
 */

!function(t,e){"object"==typeof exports&&"undefined"!=typeof module?e(exports):"function"==typeof define&&define.amd?define(["exports"],e):e((t=t||self).window=t.window||{})}(this,function(e){"use strict";function _inheritsLoose(t,e){t.prototype=Object.create(e.prototype),(t.prototype.constructor=t).__proto__=e}function _assertThisInitialized(t){if(void 0===t)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}function r(t){return"string"==typeof t}function s(t){return"function"==typeof t}function t(t){return"number"==typeof t}function u(t){return void 0===t}function v(t){return"object"==typeof t}function w(t){return!1!==t}function x(){return"undefined"!=typeof window}function y(t){return s(t)||r(t)}function R(t){return(i=bt(t,ht))&&Fe}function S(t,e){return console.warn("Invalid property",t,"set to",e,"Missing plugin? gsap.registerPlugin()")}function T(t,e){return!e&&console.warn(t)}function U(t,e){return t&&(ht[t]=e)&&i&&(i[t]=e)||ht}function V(){return 0}function ga(t){var e,r,i=t[0];if(v(i)||s(i)||(t=[t]),!(e=(i._gsap||{}).harness)){for(r=yt.length;r--&&!yt[r].targetTest(i););e=yt[r]}for(r=t.length;r--;)t[r]&&(t[r]._gsap||(t[r]._gsap=new qt(t[r],e)))||t.splice(r,1);return t}function ha(t){return t._gsap||ga(Pt(t))[0]._gsap}function ia(t,e,r){return(r=t[e])&&s(r)?t[e]():u(r)&&t.getAttribute&&t.getAttribute(e)||r}function ja(t,e){return(t=t.split(",")).forEach(e)||t}function ka(t){return Math.round(1e5*t)/1e5||0}function la(t){return Math.round(1e7*t)/1e7||0}function ma(t,e){var r=e.charAt(0),i=parseFloat(e.substr(2));return t=parseFloat(t),"+"===r?t+i:"-"===r?t-i:"*"===r?t*i:t/i}function na(t,e){for(var r=e.length,i=0;t.indexOf(e[i])<0&&++i<r;);return i<r}function oa(){var t,e,r=pt.length,i=pt.slice(0);for(_t={},t=pt.length=0;t<r;t++)(e=i[t])&&e._lazy&&(e.render(e._lazy[0],e._lazy[1],!0)._lazy=0)}function pa(t){return!!(t._initted||t._startAt||t.add)}function qa(t,e,r,i){pt.length&&!L&&oa(),t.render(e,r,i||!!(L&&e<0&&pa(t))),pt.length&&!L&&oa()}function ra(t){var e=parseFloat(t);return(e||0===e)&&(t+"").match(ot).length<2?e:r(t)?t.trim():t}function sa(t){return t}function ta(t,e){for(var r in e)r in t||(t[r]=e[r]);return t}function wa(t,e){for(var r in e)"__proto__"!==r&&"constructor"!==r&&"prototype"!==r&&(t[r]=v(e[r])?wa(t[r]||(t[r]={}),e[r]):e[r]);return t}function xa(t,e){var r,i={};for(r in t)r in e||(i[r]=t[r]);return i}function ya(t){var e=t.parent||I,r=t.keyframes?function _setKeyframeDefaults(i){return function(t,e){for(var r in e)r in t||"duration"===r&&i||"ease"===r||(t[r]=e[r])}}(K(t.keyframes)):ta;if(w(t.inherit))for(;e;)r(t,e.vars.defaults),e=e.parent||e._dp;return t}function Aa(t,e,r,i,n){void 0===r&&(r="_first"),void 0===i&&(i="_last");var a,s=t[i];if(n)for(a=e[n];s&&s[n]>a;)s=s._prev;return s?(e._next=s._next,s._next=e):(e._next=t[r],t[r]=e),e._next?e._next._prev=e:t[i]=e,e._prev=s,e.parent=e._dp=t,e}function Ba(t,e,r,i){void 0===r&&(r="_first"),void 0===i&&(i="_last");var n=e._prev,a=e._next;n?n._next=a:t[r]===e&&(t[r]=a),a?a._prev=n:t[i]===e&&(t[i]=n),e._next=e._prev=e.parent=null}function Ca(t,e){t.parent&&(!e||t.parent.autoRemoveChildren)&&t.parent.remove&&t.parent.remove(t),t._act=0}function Da(t,e){if(t&&(!e||e._end>t._dur||e._start<0))for(var r=t;r;)r._dirty=1,r=r.parent;return t}function Fa(t,e,r,i){return t._startAt&&(L?t._startAt.revert(ft):t.vars.immediateRender&&!t.vars.autoRevert||t._startAt.render(e,!0,i))}function Ha(t){return t._repeat?wt(t._tTime,t=t.duration()+t._rDelay)*t:0}function Ja(t,e){return(t-e._start)*e._ts+(0<=e._ts?0:e._dirty?e.totalDuration():e._tDur)}function Ka(t){return t._end=la(t._start+(t._tDur/Math.abs(t._ts||t._rts||X)||0))}function La(t,e){var r=t._dp;return r&&r.smoothChildTiming&&t._ts&&(t._start=la(r._time-(0<t._ts?e/t._ts:((t._dirty?t.totalDuration():t._tDur)-e)/-t._ts)),Ka(t),r._dirty||Da(r,t)),t}function Ma(t,e){var r;if((e._time||!e._dur&&e._initted||e._start<t._time&&(e._dur||!e.add))&&(r=Ja(t.rawTime(),e),(!e._dur||Mt(0,e.totalDuration(),r)-e._tTime>X)&&e.render(r,!0)),Da(t,e)._dp&&t._initted&&t._time>=t._dur&&t._ts){if(t._dur<t.duration())for(r=t;r._dp;)0<=r.rawTime()&&r.totalTime(r._tTime),r=r._dp;t._zTime=-X}}function Na(e,r,i,n){return r.parent&&Ca(r),r._start=la((t(i)?i:!i&&xt(r)&&(!e._last||e._last._start>=e._time)?e._last?e._last._start:e._time:i||e!==I?Ot(e,i,r):e._time)+r._delay),r._end=la(r._start+(r.totalDuration()/Math.abs(r.timeScale())||0)),Aa(e,r,"_first","_last",e._sort?"_start":0),xt(r)||(e._recent=r),n||Ma(e,r),e._ts<0&&La(e,e._tTime),e}function Oa(t,e){return(ht.ScrollTrigger||S("scrollTrigger",e))&&ht.ScrollTrigger.create(e,t)}function Pa(t,e,r,i,n){return $t(t,e,n),t._initted?!r&&t._pt&&!L&&(t._dur&&!1!==t.vars.lazy||!t._dur&&t.vars.lazy)&&f!==Lt.frame?(pt.push(t),t._lazy=[n,i],1):void 0:1}function Ua(t,e,r,i){var n=t._repeat,a=la(e)||0,s=t._tTime/t._tDur;return s&&!i&&(t._time*=a/t._dur),t._dur=a,t._tDur=n?n<0?1e10:la(a*(n+1)+t._rDelay*n):a,0<s&&!i&&La(t,t._tTime=t._tDur*s),t.parent&&Ka(t),r||Da(t.parent,t),t}function Va(t){return t instanceof Gt?Da(t):Ua(t,t._dur)}function Ya(e,r,i){var n,a,s=t(r[1]),o=(s?2:1)+(e<2?0:1),u=r[o];if(s&&(u.duration=r[1]),u.parent=i,e){for(n=u,a=i;a&&!("immediateRender"in n);)n=a.vars.defaults||{},a=w(a.vars.inherit)&&a.parent;u.immediateRender=w(n.immediateRender),e<2?u.runBackwards=1:u.startAt=r[o-1]}return new te(r[0],u,r[1+o])}function Za(t,e){return t||0===t?e(t):e}function _a(t,e){return r(t)&&(e=ut.exec(t))?e[1]:""}function cb(t,e){return t&&v(t)&&"length"in t&&(!e&&!t.length||t.length-1 in t&&v(t[0]))&&!t.nodeType&&t!==h}function fb(r){return r=Pt(r)[0]||T("Invalid scope")||{},function(t){var e=r.current||r.nativeElement||r;return Pt(t,e.querySelectorAll?e:e===r?T("Invalid scope")||a.createElement("div"):r)}}function gb(t){return t.sort(function(){return.5-Math.random()})}function hb(t){if(s(t))return t;var p=v(t)?t:{each:t},_=jt(p.ease),m=p.from||0,g=parseFloat(p.base)||0,y={},e=0<m&&m<1,T=isNaN(m)||e,b=p.axis,w=m,x=m;return r(m)?w=x={center:.5,edges:.5,end:1}[m]||0:!e&&T&&(w=m[0],x=m[1]),function(t,e,r){var i,n,a,s,o,u,h,l,f,c=(r||p).length,d=y[c];if(!d){if(!(f="auto"===p.grid?0:(p.grid||[1,q])[1])){for(h=-q;h<(h=r[f++].getBoundingClientRect().left)&&f<c;);f<c&&f--}for(d=y[c]=[],i=T?Math.min(f,c)*w-.5:m%f,n=f===q?0:T?c*x/f-.5:m/f|0,l=q,u=h=0;u<c;u++)a=u%f-i,s=n-(u/f|0),d[u]=o=b?Math.abs("y"===b?s:a):H(a*a+s*s),h<o&&(h=o),o<l&&(l=o);"random"===m&&gb(d),d.max=h-l,d.min=l,d.v=c=(parseFloat(p.amount)||parseFloat(p.each)*(c<f?c-1:b?"y"===b?c/f:f:Math.max(f,c/f))||0)*("edges"===m?-1:1),d.b=c<0?g-c:g,d.u=_a(p.amount||p.each)||0,_=_&&c<0?Yt(_):_}return c=(d[t]-d.min)/d.max||0,la(d.b+(_?_(c):c)*d.v)+d.u}}function ib(i){var n=Math.pow(10,((i+"").split(".")[1]||"").length);return function(e){var r=la(Math.round(parseFloat(e)/i)*i*n);return(r-r%1)/n+(t(e)?0:_a(e))}}function jb(h,e){var l,f,r=K(h);return!r&&v(h)&&(l=r=h.radius||q,h.values?(h=Pt(h.values),(f=!t(h[0]))&&(l*=l)):h=ib(h.increment)),Za(e,r?s(h)?function(t){return f=h(t),Math.abs(f-t)<=l?f:t}:function(e){for(var r,i,n=parseFloat(f?e.x:e),a=parseFloat(f?e.y:0),s=q,o=0,u=h.length;u--;)(r=f?(r=h[u].x-n)*r+(i=h[u].y-a)*i:Math.abs(h[u]-n))<s&&(s=r,o=u);return o=!l||s<=l?h[o]:e,f||o===e||t(e)?o:o+_a(e)}:ib(h))}function kb(t,e,r,i){return Za(K(t)?!e:!0===r?!!(r=0):!i,function(){return K(t)?t[~~(Math.random()*t.length)]:(r=r||1e-5)&&(i=r<1?Math.pow(10,(r+"").length-2):1)&&Math.floor(Math.round((t-r/2+Math.random()*(e-t+.99*r))/r)*r*i)/i})}function ob(e,r,t){return Za(t,function(t){return e[~~r(t)]})}function rb(t){return t.replace(tt,function(t){var e=t.indexOf("[")+1,r=t.substring(e||7,e?t.indexOf("]"):t.length-1).split(et);return kb(e?r:+r[0],e?0:+r[1],+r[2]||1e-5)})}function ub(t,e,r){var i,n,a,s=t.labels,o=q;for(i in s)(n=s[i]-e)<0==!!r&&n&&o>(n=Math.abs(n))&&(a=i,o=n);return a}function wb(t){return Ca(t),t.scrollTrigger&&t.scrollTrigger.kill(!!L),t.progress()<1&&At(t,"onInterrupt"),t}function zb(t){if(t)if(t=!t.name&&t.default||t,x()||t.headless){var e=t.name,r=s(t),i=e&&!r&&t.init?function(){this._props=[]}:t,n={init:V,render:_e,add:Ht,kill:Te,modifier:ve,rawVars:0},a={targetTest:0,get:0,getSetter:ue,aliases:{},register:0};if(It(),t!==i){if(mt[e])return;ta(i,ta(xa(t,n),a)),bt(i.prototype,bt(n,xa(t,a))),mt[i.prop=e]=i,t.targetTest&&(yt.push(i),dt[e]=1),e=("css"===e?"CSS":e.charAt(0).toUpperCase()+e.substr(1))+"Plugin"}U(e,i),t.register&&t.register(Fe,i,we)}else Dt.push(t)}function Cb(t,e,r){return(6*(t+=t<0?1:1<t?-1:0)<1?e+(r-e)*t*6:t<.5?r:3*t<2?e+(r-e)*(2/3-t)*6:e)*zt+.5|0}function Db(e,r,i){var n,a,s,o,u,h,l,f,c,d,p=e?t(e)?[e>>16,e>>8&zt,e&zt]:0:Rt.black;if(!p){if(","===e.substr(-1)&&(e=e.substr(0,e.length-1)),Rt[e])p=Rt[e];else if("#"===e.charAt(0)){if(e.length<6&&(e="#"+(n=e.charAt(1))+n+(a=e.charAt(2))+a+(s=e.charAt(3))+s+(5===e.length?e.charAt(4)+e.charAt(4):"")),9===e.length)return[(p=parseInt(e.substr(1,6),16))>>16,p>>8&zt,p&zt,parseInt(e.substr(7),16)/255];p=[(e=parseInt(e.substr(1),16))>>16,e>>8&zt,e&zt]}else if("hsl"===e.substr(0,3))if(p=d=e.match(rt),r){if(~e.indexOf("="))return p=e.match(it),i&&p.length<4&&(p[3]=1),p}else o=+p[0]%360/360,u=p[1]/100,n=2*(h=p[2]/100)-(a=h<=.5?h*(u+1):h+u-h*u),3<p.length&&(p[3]*=1),p[0]=Cb(o+1/3,n,a),p[1]=Cb(o,n,a),p[2]=Cb(o-1/3,n,a);else p=e.match(rt)||Rt.transparent;p=p.map(Number)}return r&&!d&&(n=p[0]/zt,a=p[1]/zt,s=p[2]/zt,h=((l=Math.max(n,a,s))+(f=Math.min(n,a,s)))/2,l===f?o=u=0:(c=l-f,u=.5<h?c/(2-l-f):c/(l+f),o=l===n?(a-s)/c+(a<s?6:0):l===a?(s-n)/c+2:(n-a)/c+4,o*=60),p[0]=~~(o+.5),p[1]=~~(100*u+.5),p[2]=~~(100*h+.5)),i&&p.length<4&&(p[3]=1),p}function Eb(t){var r=[],i=[],n=-1;return t.split(Et).forEach(function(t){var e=t.match(nt)||[];r.push.apply(r,e),i.push(n+=e.length+1)}),r.c=i,r}function Fb(t,e,r){var i,n,a,s,o="",u=(t+o).match(Et),h=e?"hsla(":"rgba(",l=0;if(!u)return t;if(u=u.map(function(t){return(t=Db(t,e,1))&&h+(e?t[0]+","+t[1]+"%,"+t[2]+"%,"+t[3]:t.join(","))+")"}),r&&(a=Eb(t),(i=r.c).join(o)!==a.c.join(o)))for(s=(n=t.replace(Et,"1").split(nt)).length-1;l<s;l++)o+=n[l]+(~i.indexOf(l)?u.shift()||h+"0,0,0,0)":(a.length?a:u.length?u:r).shift());if(!n)for(s=(n=t.split(Et)).length-1;l<s;l++)o+=n[l]+u[l];return o+n[s]}function Ib(t){var e,r=t.join(" ");if(Et.lastIndex=0,Et.test(r))return e=Ft.test(r),t[1]=Fb(t[1],e),t[0]=Fb(t[0],e,Eb(t[1])),!0}function Rb(t){var e=(t+"").split("("),r=Bt[e[0]];return r&&1<e.length&&r.config?r.config.apply(null,~t.indexOf("{")?[function _parseObjectInString(t){for(var e,r,i,n={},a=t.substr(1,t.length-3).split(":"),s=a[0],o=1,u=a.length;o<u;o++)r=a[o],e=o!==u-1?r.lastIndexOf(","):r.length,i=r.substr(0,e),n[s]=isNaN(i)?i.replace(Ut,"").trim():+i,s=r.substr(e+1).trim();return n}(e[1])]:function _valueInParentheses(t){var e=t.indexOf("(")+1,r=t.indexOf(")"),i=t.indexOf("(",e);return t.substring(e,~i&&i<r?t.indexOf(")",r+1):r)}(t).split(",").map(ra)):Bt._CE&&Nt.test(t)?Bt._CE("",t):r}function Ub(t,e,r,i){void 0===r&&(r=function easeOut(t){return 1-e(1-t)}),void 0===i&&(i=function easeInOut(t){return t<.5?e(2*t)/2:1-e(2*(1-t))/2});var n,a={easeIn:e,easeOut:r,easeInOut:i};return ja(t,function(t){for(var e in Bt[t]=ht[t]=a,Bt[n=t.toLowerCase()]=r,a)Bt[n+("easeIn"===e?".in":"easeOut"===e?".out":".inOut")]=Bt[t+"."+e]=a[e]}),a}function Vb(e){return function(t){return t<.5?(1-e(1-2*t))/2:.5+e(2*(t-.5))/2}}function Wb(r,t,e){function Gm(t){return 1===t?1:i*Math.pow(2,-10*t)*Q((t-a)*n)+1}var i=1<=t?t:1,n=(e||(r?.3:.45))/(t<1?t:1),a=n/G*(Math.asin(1/i)||0),s="out"===r?Gm:"in"===r?function(t){return 1-Gm(1-t)}:Vb(Gm);return n=G/n,s.config=function(t,e){return Wb(r,t,e)},s}function Xb(e,r){function Om(t){return t?--t*t*((r+1)*t+r)+1:0}void 0===r&&(r=1.70158);var t="out"===e?Om:"in"===e?function(t){return 1-Om(1-t)}:Vb(Om);return t.config=function(t){return Xb(e,t)},t}var F,L,l,I,h,n,a,i,o,f,c,d,p,_,m,g,b,k,O,M,C,P,A,D,z,E,B,N,Y={autoSleep:120,force3D:"auto",nullTargetWarn:1,units:{lineHeight:""}},j={duration:.5,overwrite:!1,delay:0},q=1e8,X=1/q,G=2*Math.PI,Z=G/4,W=0,H=Math.sqrt,$=Math.cos,Q=Math.sin,J="function"==typeof ArrayBuffer&&ArrayBuffer.isView||function(){},K=Array.isArray,tt=/random\([^)]+\)/g,et=/,\s*/g,rt=/(?:-?\.?\d|\.)+/gi,it=/[-+=.]*\d+[.e\-+]*\d*[e\-+]*\d*/g,nt=/[-+=.]*\d+[.e-]*\d*[a-z%]*/g,at=/[-+=.]*\d+\.?\d*(?:e-|e\+)?\d*/gi,st=/[+-]=-?[.\d]+/,ot=/[^,'"\[\]\s]+/gi,ut=/^[+\-=e\s\d]*\d+[.\d]*([a-z]*|%)\s*$/i,ht={},lt={suppressEvents:!0,isStart:!0,kill:!1},ft={suppressEvents:!0,kill:!1},ct={suppressEvents:!0},dt={},pt=[],_t={},mt={},gt={},vt=30,yt=[],Tt="",bt=function _merge(t,e){for(var r in e)t[r]=e[r];return t},wt=function _animationCycle(t,e){var r=Math.floor(t=la(t/e));return t&&r===t?r-1:r},xt=function _isFromOrFromStart(t){var e=t.data;return"isFromStart"===e||"isStart"===e},kt={_start:0,endTime:V,totalDuration:V},Ot=function _parsePosition(t,e,i){var n,a,s,o=t.labels,u=t._recent||kt,h=t.duration()>=q?u.endTime(!1):t._dur;return r(e)&&(isNaN(e)||e in o)?(a=e.charAt(0),s="%"===e.substr(-1),n=e.indexOf("="),"<"===a||">"===a?(0<=n&&(e=e.replace(/=/,"")),("<"===a?u._start:u.endTime(0<=u._repeat))+(parseFloat(e.substr(1))||0)*(s?(n<0?u:i).totalDuration()/100:1)):n<0?(e in o||(o[e]=h),o[e]):(a=parseFloat(e.charAt(n-1)+e.substr(n+1)),s&&i&&(a=a/100*(K(i)?i[0]:i).totalDuration()),1<n?_parsePosition(t,e.substr(0,n-1),i)+a:h+a)):null==e?h:+e},Mt=function _clamp(t,e,r){return r<t?t:e<r?e:r},Ct=[].slice,Pt=function toArray(t,e,i){return l&&!e&&l.selector?l.selector(t):!r(t)||i||!n&&It()?K(t)?function _flatten(t,e,i){return void 0===i&&(i=[]),t.forEach(function(t){return r(t)&&!e||cb(t,1)?i.push.apply(i,Pt(t)):i.push(t)})||i}(t,i):cb(t)?Ct.call(t,0):t?[t]:[]:Ct.call((e||a).querySelectorAll(t),0)},St=function mapRange(e,t,r,i,n){var a=t-e,s=i-r;return Za(n,function(t){return r+((t-e)/a*s||0)})},At=function _callback(t,e,r){var i,n,a,s=t.vars,o=s[e],u=l,h=t._ctx;if(o)return i=s[e+"Params"],n=s.callbackScope||t,r&&pt.length&&oa(),h&&(l=h),a=i?o.apply(n,i):o.call(n),l=u,a},Dt=[],zt=255,Rt={aqua:[0,zt,zt],lime:[0,zt,0],silver:[192,192,192],black:[0,0,0],maroon:[128,0,0],teal:[0,128,128],blue:[0,0,zt],navy:[0,0,128],white:[zt,zt,zt],olive:[128,128,0],yellow:[zt,zt,0],orange:[zt,165,0],gray:[128,128,128],purple:[128,0,128],green:[0,128,0],red:[zt,0,0],pink:[zt,192,203],cyan:[0,zt,zt],transparent:[zt,zt,zt,0]},Et=function(){var t,e="(?:\\b(?:(?:rgb|rgba|hsl|hsla)\\(.+?\\))|\\B#(?:[0-9a-f]{3,4}){1,2}\\b";for(t in Rt)e+="|"+t+"\\b";return new RegExp(e+")","gi")}(),Ft=/hsl[a]?\(/,Lt=(O=Date.now,M=500,C=33,P=O(),A=P,z=D=1e3/240,g={time:0,frame:0,tick:function tick(){zl(!0)},deltaRatio:function deltaRatio(t){return b/(1e3/(t||60))},wake:function wake(){o&&(!n&&x()&&(h=n=window,a=h.document||{},ht.gsap=Fe,(h.gsapVersions||(h.gsapVersions=[])).push(Fe.version),R(i||h.GreenSockGlobals||!h.gsap&&h||{}),Dt.forEach(zb)),m="undefined"!=typeof requestAnimationFrame&&requestAnimationFrame,p&&g.sleep(),_=m||function(t){return setTimeout(t,z-1e3*g.time+1|0)},d=1,zl(2))},sleep:function sleep(){(m?cancelAnimationFrame:clearTimeout)(p),d=0,_=V},lagSmoothing:function lagSmoothing(t,e){M=t||1/0,C=Math.min(e||33,M)},fps:function fps(t){D=1e3/(t||240),z=1e3*g.time+D},add:function add(n,t,e){var a=t?function(t,e,r,i){n(t,e,r,i),g.remove(a)}:n;return g.remove(n),E[e?"unshift":"push"](a),It(),a},remove:function remove(t,e){~(e=E.indexOf(t))&&E.splice(e,1)&&e<=k&&k--},_listeners:E=[]}),It=function _wake(){return!d&&Lt.wake()},Bt={},Nt=/^[\d.\-M][\d.\-,\s]/,Ut=/["']/g,Yt=function _invertEase(e){return function(t){return 1-e(1-t)}},jt=function _parseEase(t,e){return t&&(s(t)?t:Bt[t]||Rb(t))||e};function zl(t){var e,r,i,n,a=O()-A,s=!0===t;if((M<a||a<0)&&(P+=a-C),(0<(e=(i=(A+=a)-P)-z)||s)&&(n=++g.frame,b=i-1e3*g.time,g.time=i/=1e3,z+=e+(D<=e?0:D-e),r=1),s||(p=_(zl)),r)for(k=0;k<E.length;k++)E[k](i,b,n,t)}function dn(t){return t<N?B*t*t:t<.7272727272727273?B*Math.pow(t-1.5/2.75,2)+.75:t<.9090909090909092?B*(t-=2.25/2.75)*t+.9375:B*Math.pow(t-2.625/2.75,2)+.984375}ja("Linear,Quad,Cubic,Quart,Quint,Strong",function(t,e){var r=e<5?e+1:e;Ub(t+",Power"+(r-1),e?function(t){return Math.pow(t,r)}:function(t){return t},function(t){return 1-Math.pow(1-t,r)},function(t){return t<.5?Math.pow(2*t,r)/2:1-Math.pow(2*(1-t),r)/2})}),Bt.Linear.easeNone=Bt.none=Bt.Linear.easeIn,Ub("Elastic",Wb("in"),Wb("out"),Wb()),B=7.5625,N=1/2.75,Ub("Bounce",function(t){return 1-dn(1-t)},dn),Ub("Expo",function(t){return Math.pow(2,10*(t-1))*t+t*t*t*t*t*t*(1-t)}),Ub("Circ",function(t){return-(H(1-t*t)-1)}),Ub("Sine",function(t){return 1===t?1:1-$(t*Z)}),Ub("Back",Xb("in"),Xb("out"),Xb()),Bt.SteppedEase=Bt.steps=ht.SteppedEase={config:function config(t,e){void 0===t&&(t=1);var r=1/t,i=t+(e?0:1),n=e?1:0;return function(t){return((i*Mt(0,.99999999,t)|0)+n)*r}}},j.ease=Bt["quad.out"],ja("onComplete,onUpdate,onStart,onRepeat,onReverseComplete,onInterrupt",function(t){return Tt+=t+","+t+"Params,"});var Vt,qt=function GSCache(t,e){this.id=W++,(t._gsap=this).target=t,this.harness=e,this.get=e?e.get:ia,this.set=e?e.getSetter:ue},Xt=((Vt=Animation.prototype).delay=function delay(t){return t||0===t?(this.parent&&this.parent.smoothChildTiming&&this.startTime(this._start+t-this._delay),this._delay=t,this):this._delay},Vt.duration=function duration(t){return arguments.length?this.totalDuration(0<this._repeat?t+(t+this._rDelay)*this._repeat:t):this.totalDuration()&&this._dur},Vt.totalDuration=function totalDuration(t){return arguments.length?(this._dirty=0,Ua(this,this._repeat<0?t:(t-this._repeat*this._rDelay)/(this._repeat+1))):this._tDur},Vt.totalTime=function totalTime(t,e){if(It(),!arguments.length)return this._tTime;var r=this._dp;if(r&&r.smoothChildTiming&&this._ts){for(La(this,t),!r._dp||r.parent||Ma(r,this);r&&r.parent;)r.parent._time!==r._start+(0<=r._ts?r._tTime/r._ts:(r.totalDuration()-r._tTime)/-r._ts)&&r.totalTime(r._tTime,!0),r=r.parent;!this.parent&&this._dp.autoRemoveChildren&&(0<this._ts&&t<this._tDur||this._ts<0&&0<t||!this._tDur&&!t)&&Na(this._dp,this,this._start-this._delay)}return(this._tTime!==t||!this._dur&&!e||this._initted&&Math.abs(this._zTime)===X||!this._initted&&this._dur&&t||!t&&!this._initted&&(this.add||this._ptLookup))&&(this._ts||(this._pTime=t),qa(this,t,e)),this},Vt.time=function time(t,e){return arguments.length?this.totalTime(Math.min(this.totalDuration(),t+Ha(this))%(this._dur+this._rDelay)||(t?this._dur:0),e):this._time},Vt.totalProgress=function totalProgress(t,e){return arguments.length?this.totalTime(this.totalDuration()*t,e):this.totalDuration()?Math.min(1,this._tTime/this._tDur):0<=this.rawTime()&&this._initted?1:0},Vt.progress=function progress(t,e){return arguments.length?this.totalTime(this.duration()*(!this._yoyo||1&this.iteration()?t:1-t)+Ha(this),e):this.duration()?Math.min(1,this._time/this._dur):0<this.rawTime()?1:0},Vt.iteration=function iteration(t,e){var r=this.duration()+this._rDelay;return arguments.length?this.totalTime(this._time+(t-1)*r,e):this._repeat?wt(this._tTime,r)+1:1},Vt.timeScale=function timeScale(t,e){if(!arguments.length)return this._rts===-X?0:this._rts;if(this._rts===t)return this;var r=this.parent&&this._ts?Ja(this.parent._time,this):this._tTime,i=this._rts<0;this._rts=+t||0,this._ts=this._ps||t===-X?0:this._rts;var n=this.totalDuration();return(i!=this._rts<0||0<r&&r<n)&&this.totalTime(Mt(-Math.abs(this._delay),n,r),!1!==e),Ka(this),function _recacheAncestors(t){for(var e=t.parent;e&&e.parent;)e._dirty=1,e.totalDuration(),e=e.parent;return t}(this)},Vt.paused=function paused(t){if(!arguments.length)return this._ps;if(this._ps!==t)if(this._ps=t)this._pTime=this._tTime||Math.max(-this._delay,this.rawTime()),this._ts=this._act=0;else{It(),this._ts=this._rts;var e,r=this.parent&&!this.parent.smoothChildTiming?this.rawTime():this._tTime||this._pTime,i=1===this.progress()&&Math.abs(this._zTime)!==X;if(i)for(this._tTime-=X,e=this._first;e;){if(!e._dur&&e._zTime<0&&e._ts&&e._start<=r+X){i=!1;break}e=e._next}this.totalTime(r,i)}return this},Vt.startTime=function startTime(t){if(arguments.length){this._start=la(t);var e=this.parent||this._dp;return!e||!e._sort&&this.parent||Na(e,this,this._start-this._delay),this}return this._start},Vt.endTime=function endTime(t){return this._start+(w(t)?this.totalDuration():this.duration())/Math.abs(this._ts||1)},Vt.rawTime=function rawTime(t){var e=this.parent||this._dp;return e?t&&(!this._ts||this._repeat&&this._time&&this.totalProgress()<1)?this._tTime%(this._dur+this._rDelay):this._ts?Ja(e.rawTime(t),this):this._tTime:this._tTime},Vt.revert=function revert(t){void 0===t&&(t=ct);var e=L;return L=t,pa(this)&&(this.timeline&&this.timeline.revert(t),this.totalTime(-.01,t.suppressEvents)),"nested"!==this.data&&!1!==t.kill&&this.kill(),L=e,this},Vt.globalTime=function globalTime(t){for(var e=this,r=arguments.length?t:e.rawTime();e;)r=e._start+r/(Math.abs(e._ts)||1),e=e._dp;return!this.parent&&this._sat?this._sat.globalTime(t):r},Vt.repeat=function repeat(t){return arguments.length?(this._repeat=t===1/0?-2:t,Va(this)):-2===this._repeat?1/0:this._repeat},Vt.repeatDelay=function repeatDelay(t){if(arguments.length){var e=this._time;return this._rDelay=t,Va(this),e?this.time(e):this}return this._rDelay},Vt.yoyo=function yoyo(t){return arguments.length?(this._yoyo=t,this):this._yoyo},Vt.seek=function seek(t,e){return this.totalTime(Ot(this,t),w(e))},Vt.restart=function restart(t,e){return this.play().totalTime(t?-this._delay:0,w(e)),this._dur||(this._zTime=-X),this},Vt.play=function play(t,e){return null!=t&&this.seek(t,e),this.reversed(!1).paused(!1)},Vt.reverse=function reverse(t,e){return null!=t&&this.seek(t||this.totalDuration(),e),this.reversed(!0).paused(!1)},Vt.pause=function pause(t,e){return null!=t&&this.seek(t,e),this.paused(!0)},Vt.resume=function resume(){return this.paused(!1)},Vt.reversed=function reversed(t){return arguments.length?(!!t!==this.reversed()&&this.timeScale(-this._rts||(t?-X:0)),this):this._rts<0},Vt.invalidate=function invalidate(){return this._initted=this._act=0,this._zTime=-X,this},Vt.isActive=function isActive(){var t,e=this.parent||this._dp,r=this._start;return!(e&&!(this._ts&&this._initted&&e.isActive()&&(t=e.rawTime(!0))>=r&&t<this.endTime(!0)-X))},Vt.eventCallback=function eventCallback(t,e,r){var i=this.vars;return 1<arguments.length?(e?(i[t]=e,r&&(i[t+"Params"]=r),"onUpdate"===t&&(this._onUpdate=e)):delete i[t],this):i[t]},Vt.then=function then(t){var i=this,n=i._prom;return new Promise(function(e){function Fo(){var t=i.then;i.then=null,n&&n(),s(r)&&(r=r(i))&&(r.then||r===i)&&(i.then=t),e(r),i.then=t}var r=s(t)?t:sa;i._initted&&1===i.totalProgress()&&0<=i._ts||!i._tTime&&i._ts<0?Fo():i._prom=Fo})},Vt.kill=function kill(){wb(this)},Animation);function Animation(t){this.vars=t,this._delay=+t.delay||0,(this._repeat=t.repeat===1/0?-2:t.repeat||0)&&(this._rDelay=t.repeatDelay||0,this._yoyo=!!t.yoyo||!!t.yoyoEase),this._ts=1,Ua(this,+t.duration,1,1),this.data=t.data,l&&(this._ctx=l).data.push(this),d||Lt.wake()}ta(Xt.prototype,{_time:0,_start:0,_end:0,_tTime:0,_tDur:0,_dirty:0,_repeat:0,_yoyo:!1,parent:null,_initted:!1,_rDelay:0,_ts:1,_dp:0,ratio:0,_zTime:-X,_prom:0,_ps:!1,_rts:1});var Gt=function(i){function Timeline(t,e){var r;return void 0===t&&(t={}),(r=i.call(this,t)||this).labels={},r.smoothChildTiming=!!t.smoothChildTiming,r.autoRemoveChildren=!!t.autoRemoveChildren,r._sort=w(t.sortChildren),I&&Na(t.parent||I,_assertThisInitialized(r),e),t.reversed&&r.reverse(),t.paused&&r.paused(!0),t.scrollTrigger&&Oa(_assertThisInitialized(r),t.scrollTrigger),r}_inheritsLoose(Timeline,i);var e=Timeline.prototype;return e.to=function to(t,e,r){return Ya(0,arguments,this),this},e.from=function from(t,e,r){return Ya(1,arguments,this),this},e.fromTo=function fromTo(t,e,r,i){return Ya(2,arguments,this),this},e.set=function set(t,e,r){return e.duration=0,e.parent=this,ya(e).repeatDelay||(e.repeat=0),e.immediateRender=!!e.immediateRender,new te(t,e,Ot(this,r),1),this},e.call=function call(t,e,r){return Na(this,te.delayedCall(0,t,e),r)},e.staggerTo=function staggerTo(t,e,r,i,n,a,s){return r.duration=e,r.stagger=r.stagger||i,r.onComplete=a,r.onCompleteParams=s,r.parent=this,new te(t,r,Ot(this,n)),this},e.staggerFrom=function staggerFrom(t,e,r,i,n,a,s){return r.runBackwards=1,ya(r).immediateRender=w(r.immediateRender),this.staggerTo(t,e,r,i,n,a,s)},e.staggerFromTo=function staggerFromTo(t,e,r,i,n,a,s,o){return i.startAt=r,ya(i).immediateRender=w(i.immediateRender),this.staggerTo(t,e,i,n,a,s,o)},e.render=function render(t,e,r){var i,n,a,s,o,u,h,l,f,c,d,p,_=this._time,m=this._dirty?this.totalDuration():this._tDur,g=this._dur,v=t<=0?0:la(t),y=this._zTime<0!=t<0&&(this._initted||!g);if(this!==I&&m<v&&0<=t&&(v=m),v!==this._tTime||r||y){if(_!==this._time&&g&&(v+=this._time-_,t+=this._time-_),i=v,f=this._start,u=!(l=this._ts),y&&(g||(_=this._zTime),!t&&e||(this._zTime=t)),this._repeat){if(d=this._yoyo,o=g+this._rDelay,this._repeat<-1&&t<0)return this.totalTime(100*o+t,e,r);if(i=la(v%o),v===m?(s=this._repeat,i=g):((s=~~(c=la(v/o)))&&s===c&&(i=g,s--),g<i&&(i=g)),c=wt(this._tTime,o),!_&&this._tTime&&c!==s&&this._tTime-c*o-this._dur<=0&&(c=s),d&&1&s&&(i=g-i,p=1),s!==c&&!this._lock){var T=d&&1&c,b=T===(d&&1&s);if(s<c&&(T=!T),_=T?0:v%g?g:v,this._lock=1,this.render(_||(p?0:la(s*o)),e,!g)._lock=0,this._tTime=v,!e&&this.parent&&At(this,"onRepeat"),this.vars.repeatRefresh&&!p&&(this.invalidate()._lock=1,c=s),_&&_!==this._time||u!=!this._ts||this.vars.onRepeat&&!this.parent&&!this._act)return this;if(g=this._dur,m=this._tDur,b&&(this._lock=2,_=T?g:-1e-4,this.render(_,!0),this.vars.repeatRefresh&&!p&&this.invalidate()),this._lock=0,!this._ts&&!u)return this}}if(this._hasPause&&!this._forcing&&this._lock<2&&(h=function _findNextPauseTween(t,e,r){var i;if(e<r)for(i=t._first;i&&i._start<=r;){if("isPause"===i.data&&i._start>e)return i;i=i._next}else for(i=t._last;i&&i._start>=r;){if("isPause"===i.data&&i._start<e)return i;i=i._prev}}(this,la(_),la(i)))&&(v-=i-(i=h._start)),this._tTime=v,this._time=i,this._act=!!l,this._initted||(this._onUpdate=this.vars.onUpdate,this._initted=1,this._zTime=t,_=0),!_&&v&&g&&!e&&!c&&(At(this,"onStart"),this._tTime!==v))return this;if(_<=i&&0<=t)for(n=this._first;n;){if(a=n._next,(n._act||i>=n._start)&&n._ts&&h!==n){if(n.parent!==this)return this.render(t,e,r);if(n.render(0<n._ts?(i-n._start)*n._ts:(n._dirty?n.totalDuration():n._tDur)+(i-n._start)*n._ts,e,r),i!==this._time||!this._ts&&!u){h=0,a&&(v+=this._zTime=-X);break}}n=a}else{n=this._last;for(var w=t<0?t:i;n;){if(a=n._prev,(n._act||w<=n._end)&&n._ts&&h!==n){if(n.parent!==this)return this.render(t,e,r);if(n.render(0<n._ts?(w-n._start)*n._ts:(n._dirty?n.totalDuration():n._tDur)+(w-n._start)*n._ts,e,r||L&&pa(n)),i!==this._time||!this._ts&&!u){h=0,a&&(v+=this._zTime=w?-X:X);break}}n=a}}if(h&&!e&&(this.pause(),h.render(_<=i?0:-X)._zTime=_<=i?1:-1,this._ts))return this._start=f,Ka(this),this.render(t,e,r);this._onUpdate&&!e&&At(this,"onUpdate",!0),(v===m&&this._tTime>=this.totalDuration()||!v&&_)&&(f!==this._start&&Math.abs(l)===Math.abs(this._ts)||this._lock||(!t&&g||!(v===m&&0<this._ts||!v&&this._ts<0)||Ca(this,1),e||t<0&&!_||!v&&!_&&m||(At(this,v===m&&0<=t?"onComplete":"onReverseComplete",!0),!this._prom||v<m&&0<this.timeScale()||this._prom())))}return this},e.add=function add(e,i){var n=this;if(t(i)||(i=Ot(this,i,e)),!(e instanceof Xt)){if(K(e))return e.forEach(function(t){return n.add(t,i)}),this;if(r(e))return this.addLabel(e,i);if(!s(e))return this;e=te.delayedCall(0,e)}return this!==e?Na(this,e,i):this},e.getChildren=function getChildren(t,e,r,i){void 0===t&&(t=!0),void 0===e&&(e=!0),void 0===r&&(r=!0),void 0===i&&(i=-q);for(var n=[],a=this._first;a;)a._start>=i&&(a instanceof te?e&&n.push(a):(r&&n.push(a),t&&n.push.apply(n,a.getChildren(!0,e,r)))),a=a._next;return n},e.getById=function getById(t){for(var e=this.getChildren(1,1,1),r=e.length;r--;)if(e[r].vars.id===t)return e[r]},e.remove=function remove(t){return r(t)?this.removeLabel(t):s(t)?this.killTweensOf(t):(t.parent===this&&Ba(this,t),t===this._recent&&(this._recent=this._last),Da(this))},e.totalTime=function totalTime(t,e){return arguments.length?(this._forcing=1,!this._dp&&this._ts&&(this._start=la(Lt.time-(0<this._ts?t/this._ts:(this.totalDuration()-t)/-this._ts))),i.prototype.totalTime.call(this,t,e),this._forcing=0,this):this._tTime},e.addLabel=function addLabel(t,e){return this.labels[t]=Ot(this,e),this},e.removeLabel=function removeLabel(t){return delete this.labels[t],this},e.addPause=function addPause(t,e,r){var i=te.delayedCall(0,e||V,r);return i.data="isPause",this._hasPause=1,Na(this,i,Ot(this,t))},e.removePause=function removePause(t){var e=this._first;for(t=Ot(this,t);e;)e._start===t&&"isPause"===e.data&&Ca(e),e=e._next},e.killTweensOf=function killTweensOf(t,e,r){for(var i=this.getTweensOf(t,r),n=i.length;n--;)Zt!==i[n]&&i[n].kill(t,e);return this},e.getTweensOf=function getTweensOf(e,r){for(var i,n=[],a=Pt(e),s=this._first,o=t(r);s;)s instanceof te?na(s._targets,a)&&(o?(!Zt||s._initted&&s._ts)&&s.globalTime(0)<=r&&s.globalTime(s.totalDuration())>r:!r||s.isActive())&&n.push(s):(i=s.getTweensOf(a,r)).length&&n.push.apply(n,i),s=s._next;return n},e.tweenTo=function tweenTo(t,e){e=e||{};var r,i=this,n=Ot(i,t),a=e.startAt,s=e.onStart,o=e.onStartParams,u=e.immediateRender,h=te.to(i,ta({ease:e.ease||"none",lazy:!1,immediateRender:!1,time:n,overwrite:"auto",duration:e.duration||Math.abs((n-(a&&"time"in a?a.time:i._time))/i.timeScale())||X,onStart:function onStart(){if(i.pause(),!r){var t=e.duration||Math.abs((n-(a&&"time"in a?a.time:i._time))/i.timeScale());h._dur!==t&&Ua(h,t,0,1).render(h._time,!0,!0),r=1}s&&s.apply(h,o||[])}},e));return u?h.render(0):h},e.tweenFromTo=function tweenFromTo(t,e,r){return this.tweenTo(e,ta({startAt:{time:Ot(this,t)}},r))},e.recent=function recent(){return this._recent},e.nextLabel=function nextLabel(t){return void 0===t&&(t=this._time),ub(this,Ot(this,t))},e.previousLabel=function previousLabel(t){return void 0===t&&(t=this._time),ub(this,Ot(this,t),1)},e.currentLabel=function currentLabel(t){return arguments.length?this.seek(t,!0):this.previousLabel(this._time+X)},e.shiftChildren=function shiftChildren(t,e,r){void 0===r&&(r=0);var i,n=this._first,a=this.labels;for(t=la(t);n;)n._start>=r&&(n._start+=t,n._end+=t),n=n._next;if(e)for(i in a)a[i]>=r&&(a[i]+=t);return Da(this)},e.invalidate=function invalidate(t){var e=this._first;for(this._lock=0;e;)e.invalidate(t),e=e._next;return i.prototype.invalidate.call(this,t)},e.clear=function clear(t){void 0===t&&(t=!0);for(var e,r=this._first;r;)e=r._next,this.remove(r),r=e;return this._dp&&(this._time=this._tTime=this._pTime=0),t&&(this.labels={}),Da(this)},e.totalDuration=function totalDuration(t){var e,r,i,n=0,a=this,s=a._last,o=q;if(arguments.length)return a.timeScale((a._repeat<0?a.duration():a.totalDuration())/(a.reversed()?-t:t));if(a._dirty){for(i=a.parent;s;)e=s._prev,s._dirty&&s.totalDuration(),o<(r=s._start)&&a._sort&&s._ts&&!a._lock?(a._lock=1,Na(a,s,r-s._delay,1)._lock=0):o=r,r<0&&s._ts&&(n-=r,(!i&&!a._dp||i&&i.smoothChildTiming)&&(a._start+=la(r/a._ts),a._time-=r,a._tTime-=r),a.shiftChildren(-r,!1,-Infinity),o=0),s._end>n&&s._ts&&(n=s._end),s=e;Ua(a,a===I&&a._time>n?a._time:n,1,1),a._dirty=0}return a._tDur},Timeline.updateRoot=function updateRoot(t){if(I._ts&&(qa(I,Ja(t,I)),f=Lt.frame),Lt.frame>=vt){vt+=Y.autoSleep||120;var e=I._first;if((!e||!e._ts)&&Y.autoSleep&&Lt._listeners.length<2){for(;e&&!e._ts;)e=e._next;e||Lt.sleep()}}},Timeline}(Xt);ta(Gt.prototype,{_lock:0,_hasPause:0,_forcing:0});function cc(t,e,i,n,a,o){var u,h,l,f;if(mt[t]&&!1!==(u=new mt[t]).init(a,u.rawVars?e[t]:function _processVars(t,e,i,n,a){if(s(t)&&(t=Qt(t,a,e,i,n)),!v(t)||t.style&&t.nodeType||K(t)||J(t))return r(t)?Qt(t,a,e,i,n):t;var o,u={};for(o in t)u[o]=Qt(t[o],a,e,i,n);return u}(e[t],n,a,o,i),i,n,o)&&(i._pt=h=new we(i._pt,a,t,0,1,u.render,u,0,u.priority),i!==c))for(l=i._ptLookup[i._targets.indexOf(a)],f=u._props.length;f--;)l[u._props[f]]=h;return u}function ic(t,r,e,i){var n,a,s=r.ease||i||"power1.inOut";if(K(r))a=e[t]||(e[t]=[]),r.forEach(function(t,e){return a.push({t:e/(r.length-1)*100,v:t,e:s})});else for(n in r)a=e[n]||(e[n]=[]),"ease"===n||a.push({t:parseFloat(t),v:r[n],e:s})}var Zt,Wt,Ht=function _addPropTween(t,e,i,n,a,o,u,h,l,f){s(n)&&(n=n(a||0,t,o));var c,d=t[e],p="get"!==i?i:s(d)?l?t[e.indexOf("set")||!s(t["get"+e.substr(3)])?e:"get"+e.substr(3)](l):t[e]():d,_=s(d)?l?se:ae:ie;if(r(n)&&(~n.indexOf("random(")&&(n=rb(n)),"="===n.charAt(1)&&(!(c=ma(p,n)+(_a(p)||0))&&0!==c||(n=c))),!f||p!==n||Wt)return isNaN(p*n)||""===n?(d||e in t||S(e,n),function _addComplexStringPropTween(t,e,r,i,n,a,s){var o,u,h,l,f,c,d,p,_=new we(this._pt,t,e,0,1,pe,null,n),m=0,g=0;for(_.b=r,_.e=i,r+="",(d=~(i+="").indexOf("random("))&&(i=rb(i)),a&&(a(p=[r,i],t,e),r=p[0],i=p[1]),u=r.match(at)||[];o=at.exec(i);)l=o[0],f=i.substring(m,o.index),h?h=(h+1)%5:"rgba("===f.substr(-5)&&(h=1),l!==u[g++]&&(c=parseFloat(u[g-1])||0,_._pt={_next:_._pt,p:f||1===g?f:",",s:c,c:"="===l.charAt(1)?ma(c,l)-c:parseFloat(l)-c,m:h&&h<4?Math.round:0},m=at.lastIndex);return _.c=m<i.length?i.substring(m,i.length):"",_.fp=s,(st.test(i)||d)&&(_.e=0),this._pt=_}.call(this,t,e,p,n,_,h||Y.stringFilter,l)):(c=new we(this._pt,t,e,+p||0,n-(p||0),"boolean"==typeof d?de:fe,0,_),l&&(c.fp=l),u&&c.modifier(u,this,t),this._pt=c)},$t=function _initTween(t,e,r){var i,n,a,s,o,u,h,l,f,c,d,p,_,m=t.vars,g=m.ease,v=m.startAt,y=m.immediateRender,T=m.lazy,b=m.onUpdate,x=m.runBackwards,k=m.yoyoEase,O=m.keyframes,M=m.autoRevert,C=t._dur,P=t._startAt,S=t._targets,A=t.parent,D=A&&"nested"===A.data?A.vars.targets:S,z="auto"===t._overwrite&&!F,R=t.timeline,E=m.easeReverse||k;if(!R||O&&g||(g="none"),t._ease=jt(g,j.ease),t._rEase=E&&(jt(E)||t._ease),t._from=!R&&!!m.runBackwards,t._from&&(t.ratio=1),!R||O&&!m.stagger){if(p=(l=S[0]?ha(S[0]).harness:0)&&m[l.prop],i=xa(m,dt),P&&(P._zTime<0&&P.progress(1),e<0&&x&&y&&!M?P.render(-1,!0):P.revert(x&&C?ft:lt),P._lazy=0),v){if(Ca(t._startAt=te.set(S,ta({data:"isStart",overwrite:!1,parent:A,immediateRender:!0,lazy:!P&&w(T),startAt:null,delay:0,onUpdate:b&&function(){return At(t,"onUpdate")},stagger:0},v))),t._startAt._dp=0,t._startAt._sat=t,e<0&&(L||!y&&!M)&&t._startAt.revert(ft),y&&C&&e<=0&&r<=0)return void(e&&(t._zTime=e))}else if(x&&C&&!P)if(e&&(y=!1),a=ta({overwrite:!1,data:"isFromStart",lazy:y&&!P&&w(T),immediateRender:y,stagger:0,parent:A},i),p&&(a[l.prop]=p),Ca(t._startAt=te.set(S,a)),t._startAt._dp=0,t._startAt._sat=t,e<0&&(L?t._startAt.revert(ft):t._startAt.render(-1,!0)),t._zTime=e,y){if(!e)return}else _initTween(t._startAt,X,X);for(t._pt=t._ptCache=0,T=C&&w(T)||T&&!C,n=0;n<S.length;n++){if(h=(o=S[n])._gsap||ga(S)[n]._gsap,t._ptLookup[n]=c={},_t[h.id]&&pt.length&&oa(),d=D===S?n:D.indexOf(o),l&&!1!==(f=new l).init(o,p||i,t,d,D)&&(t._pt=s=new we(t._pt,o,f.name,0,1,f.render,f,0,f.priority),f._props.forEach(function(t){c[t]=s}),f.priority&&(u=1)),!l||p)for(a in i)mt[a]&&(f=cc(a,i,t,d,o,D))?f.priority&&(u=1):c[a]=s=Ht.call(t,o,a,"get",i[a],d,D,0,m.stringFilter);t._op&&t._op[n]&&t.kill(o,t._op[n]),z&&t._pt&&(Zt=t,I.killTweensOf(o,c,t.globalTime(e)),_=!t.parent,Zt=0),t._pt&&T&&(_t[h.id]=1)}u&&be(t),t._onInit&&t._onInit(t)}t._onUpdate=b,t._initted=(!t._op||t._pt)&&!_,O&&e<=0&&R.render(q,!0,!0)},Qt=function _parseFuncOrString(t,e,i,n,a){return s(t)?t.call(e,i,n,a):r(t)&&~t.indexOf("random(")?rb(t):t},Jt=Tt+"repeat,repeatDelay,yoyo,repeatRefresh,yoyoEase,easeReverse,autoRevert",Kt={};ja(Jt+",id,stagger,delay,duration,paused,scrollTrigger",function(t){return Kt[t]=1}),Jt=Jt.split(",");var te=function(E){function Tween(e,r,i,n){var a;"number"==typeof r&&(i.duration=r,r=i,i=null);var s,o,u,h,l,f,c,d,p=(a=E.call(this,n?r:ya(r))||this).vars,_=p.duration,m=p.delay,g=p.immediateRender,b=p.stagger,x=p.overwrite,k=p.keyframes,O=p.defaults,M=p.scrollTrigger,C=r.parent||I,P=(K(e)||J(e)?t(e[0]):"length"in r)?[e]:Pt(e);if(a._targets=P.length?ga(P):T("GSAP target "+e+" not found. https://gsap.com",!Y.nullTargetWarn)||[],a._ptLookup=[],a._overwrite=x,k||b||y(_)||y(m)){var S=(r=a.vars).easeReverse||r.yoyoEase;if((s=a.timeline=new Gt({data:"nested",defaults:O||{},targets:C&&"nested"===C.data?C.vars.targets:P})).kill(),s.parent=s._dp=_assertThisInitialized(a),s._start=0,b||y(_)||y(m)){if(h=P.length,c=b&&hb(b),v(b))for(l in b)~Jt.indexOf(l)&&((d=d||{})[l]=b[l]);for(o=0;o<h;o++)(u=xa(r,Kt)).stagger=0,S&&(u.easeReverse=S),d&&bt(u,d),f=P[o],u.duration=+Qt(_,_assertThisInitialized(a),o,f,P),u.delay=(+Qt(m,_assertThisInitialized(a),o,f,P)||0)-a._delay,!b&&1===h&&u.delay&&(a._delay=m=u.delay,a._start+=m,u.delay=0),s.to(f,u,c?c(o,f,P):0),s._ease=Bt.none;s.duration()?_=m=0:a.timeline=0}else if(k){ya(ta(s.vars.defaults,{ease:"none"})),s._ease=jt(k.ease||r.ease||"none");var A,D,z,R=0;if(K(k))k.forEach(function(t){return s.to(P,t,">")}),s.duration();else{for(l in u={},k)"ease"===l||"easeEach"===l||ic(l,k[l],u,k.easeEach);for(l in u)for(A=u[l].sort(function(t,e){return t.t-e.t}),o=R=0;o<A.length;o++)(z={ease:(D=A[o]).e,duration:(D.t-(o?A[o-1].t:0))/100*_})[l]=D.v,s.to(P,z,R),R+=z.duration;s.duration()<_&&s.to({},{duration:_-s.duration()})}}_||a.duration(_=s.duration())}else a.timeline=0;return!0!==x||F||(Zt=_assertThisInitialized(a),I.killTweensOf(P),Zt=0),Na(C,_assertThisInitialized(a),i),r.reversed&&a.reverse(),r.paused&&a.paused(!0),(g||!_&&!k&&a._start===la(C._time)&&w(g)&&function _hasNoPausedAncestors(t){return!t||t._ts&&_hasNoPausedAncestors(t.parent)}(_assertThisInitialized(a))&&"nested"!==C.data)&&(a._tTime=-X,a.render(Math.max(0,-m)||0)),M&&Oa(_assertThisInitialized(a),M),a}_inheritsLoose(Tween,E);var e=Tween.prototype;return e.render=function render(t,e,r){var i,n,a,s,o,u,h,l,f=this._time,c=this._tDur,d=this._dur,p=t<0,_=c-X<t&&!p?c:t<X?0:t;if(d){if(_!==this._tTime||!t||r||!this._initted&&this._tTime||this._startAt&&this._zTime<0!=p||this._lazy){if(i=_,l=this.timeline,this._repeat){if(s=d+this._rDelay,this._repeat<-1&&p)return this.totalTime(100*s+t,e,r);if(i=la(_%s),_===c?(a=this._repeat,i=d):(a=~~(o=la(_/s)))&&a===o?(i=d,a--):d<i&&(i=d),(u=this._yoyo&&1&a)&&(i=d-i),o=wt(this._tTime,s),i===f&&!r&&this._initted&&a===o)return this._tTime=_,this;a!==o&&this.vars.repeatRefresh&&!u&&!this._lock&&i!==s&&this._initted&&(this._lock=r=1,this.render(la(s*a),!0).invalidate()._lock=0)}if(!this._initted){if(Pa(this,p?t:i,r,e,_))return this._tTime=0,this;if(!(f===this._time||r&&this.vars.repeatRefresh&&a!==o))return this;if(d!==this._dur)return this.render(t,e,r)}if(this._rEase){var m=i<f;if(m!==this._inv){var g=m?f:d-f;this._inv=m,this._from&&(this.ratio=1-this.ratio),this._invRatio=this.ratio,this._invTime=f,this._invRecip=g?(m?-1:1)/g:0,this._invScale=m?-this.ratio:1-this.ratio,this._invEase=m?this._rEase:this._ease}this.ratio=h=this._invRatio+this._invScale*this._invEase((i-this._invTime)*this._invRecip)}else this.ratio=h=this._ease(i/d);if(this._from&&(this.ratio=h=1-h),this._tTime=_,this._time=i,!this._act&&this._ts&&(this._act=1,this._lazy=0),!f&&_&&!e&&!o&&(At(this,"onStart"),this._tTime!==_))return this;for(n=this._pt;n;)n.r(h,n.d),n=n._next;l&&l.render(t<0?t:l._dur*l._ease(i/this._dur),e,r)||this._startAt&&(this._zTime=t),this._onUpdate&&!e&&(p&&Fa(this,t,0,r),At(this,"onUpdate")),this._repeat&&a!==o&&this.vars.onRepeat&&!e&&this.parent&&At(this,"onRepeat"),_!==this._tDur&&_||this._tTime!==_||(p&&!this._onUpdate&&Fa(this,t,0,!0),!t&&d||!(_===this._tDur&&0<this._ts||!_&&this._ts<0)||Ca(this,1),e||p&&!f||!(_||f||u)||(At(this,_===c?"onComplete":"onReverseComplete",!0),!this._prom||_<c&&0<this.timeScale()||this._prom()))}}else!function _renderZeroDurationTween(t,e,r,i){var n,a,s,o=t.ratio,u=e<0||!e&&(!t._start&&function _parentPlayheadIsBeforeStart(t){var e=t.parent;return e&&e._ts&&e._initted&&!e._lock&&(e.rawTime()<0||_parentPlayheadIsBeforeStart(e))}(t)&&(t._initted||!xt(t))||(t._ts<0||t._dp._ts<0)&&!xt(t))?0:1,h=t._rDelay,l=0;if(h&&t._repeat&&(l=Mt(0,t._tDur,e),a=wt(l,h),t._yoyo&&1&a&&(u=1-u),a!==wt(t._tTime,h)&&(o=1-u,t.vars.repeatRefresh&&t._initted&&t.invalidate())),u!==o||L||i||t._zTime===X||!e&&t._zTime){if(!t._initted&&Pa(t,e,i,r,l))return;for(s=t._zTime,t._zTime=e||(r?X:0),r=r||e&&!s,t.ratio=u,t._from&&(u=1-u),t._time=0,t._tTime=l,n=t._pt;n;)n.r(u,n.d),n=n._next;e<0&&Fa(t,e,0,!0),t._onUpdate&&!r&&At(t,"onUpdate"),l&&t._repeat&&!r&&t.parent&&At(t,"onRepeat"),(e>=t._tDur||e<0)&&t.ratio===u&&(u&&Ca(t,1),r||L||(At(t,u?"onComplete":"onReverseComplete",!0),t._prom&&t._prom()))}else t._zTime||(t._zTime=e)}(this,t,e,r);return this},e.targets=function targets(){return this._targets},e.invalidate=function invalidate(t){return t&&this.vars.runBackwards||(this._startAt=0),this._pt=this._op=this._onUpdate=this._lazy=this.ratio=0,this._ptLookup=[],this.timeline&&this.timeline.invalidate(t),E.prototype.invalidate.call(this,t)},e.resetTo=function resetTo(t,e,r,i,n){d||Lt.wake(),this._ts||this.play();var a,s=Math.min(this._dur,(this._dp._time-this._start)*this._ts);return this._initted||$t(this,s),a=this._ease(s/this._dur),function _updatePropTweens(t,e,r,i,n,a,s,o){var u,h,l,f,c=(t._pt&&t._ptCache||(t._ptCache={}))[e];if(!c)for(c=t._ptCache[e]=[],l=t._ptLookup,f=t._targets.length;f--;){if((u=l[f][e])&&u.d&&u.d._pt)for(u=u.d._pt;u&&u.p!==e&&u.fp!==e;)u=u._next;if(!u)return Wt=1,t.vars[e]="+=0",$t(t,s),Wt=0,o?T(e+" not eligible for reset. Try splitting into individual properties"):1;c.push(u)}for(f=c.length;f--;)(u=(h=c[f])._pt||h).s=!i&&0!==i||n?u.s+(i||0)+a*u.c:i,u.c=r-u.s,h.e&&(h.e=ka(r)+_a(h.e)),h.b&&(h.b=u.s+_a(h.b))}(this,t,e,r,i,a,s,n)?this.resetTo(t,e,r,i,1):(La(this,0),this.parent||Aa(this._dp,this,"_first","_last",this._dp._sort?"_start":0),this.render(0))},e.kill=function kill(t,e){if(void 0===e&&(e="all"),!(t||e&&"all"!==e))return this._lazy=this._pt=0,this.parent?wb(this):this.scrollTrigger&&this.scrollTrigger.kill(!!L),this;if(this.timeline){var i=this.timeline.totalDuration();return this.timeline.killTweensOf(t,e,Zt&&!0!==Zt.vars.overwrite)._first||wb(this),this.parent&&i!==this.timeline.totalDuration()&&Ua(this,this._dur*this.timeline._tDur/i,0,1),this}var n,a,s,o,u,h,l,f=this._targets,c=t?Pt(t):f,d=this._ptLookup,p=this._pt;if((!e||"all"===e)&&function _arraysMatch(t,e){for(var r=t.length,i=r===e.length;i&&r--&&t[r]===e[r];);return r<0}(f,c))return"all"===e&&(this._pt=0),wb(this);for(n=this._op=this._op||[],"all"!==e&&(r(e)&&(u={},ja(e,function(t){return u[t]=1}),e=u),e=function _addAliasesToVars(t,e){var r,i,n,a,s=t[0]?ha(t[0]).harness:0,o=s&&s.aliases;if(!o)return e;for(i in r=bt({},e),o)if(i in r)for(n=(a=o[i].split(",")).length;n--;)r[a[n]]=r[i];return r}(f,e)),l=f.length;l--;)if(~c.indexOf(f[l]))for(u in a=d[l],"all"===e?(n[l]=e,o=a,s={}):(s=n[l]=n[l]||{},o=e),o)(h=a&&a[u])&&("kill"in h.d&&!0!==h.d.kill(u)||Ba(this,h,"_pt"),delete a[u]),"all"!==s&&(s[u]=1);return this._initted&&!this._pt&&p&&wb(this),this},Tween.to=function to(t,e,r){return new Tween(t,e,r)},Tween.from=function from(t,e){return Ya(1,arguments)},Tween.delayedCall=function delayedCall(t,e,r,i){return new Tween(e,0,{immediateRender:!1,lazy:!1,overwrite:!1,delay:t,onComplete:e,onReverseComplete:e,onCompleteParams:r,onReverseCompleteParams:r,callbackScope:i})},Tween.fromTo=function fromTo(t,e,r){return Ya(2,arguments)},Tween.set=function set(t,e){return e.duration=0,e.repeatDelay||(e.repeat=0),new Tween(t,e)},Tween.killTweensOf=function killTweensOf(t,e,r){return I.killTweensOf(t,e,r)},Tween}(Xt);ta(te.prototype,{_targets:[],_lazy:0,_startAt:0,_op:0,_onInit:0}),ja("staggerTo,staggerFrom,staggerFromTo",function(r){te[r]=function(){var t=new Gt,e=Ct.call(arguments,0);return e.splice("staggerFromTo"===r?5:4,0,0),t[r].apply(t,e)}});function qc(t,e,r){return t.setAttribute(e,r)}function yc(t,e,r,i){i.mSet(t,e,i.m.call(i.tween,r,i.mt),i)}var ie=function _setterPlain(t,e,r){return t[e]=r},ae=function _setterFunc(t,e,r){return t[e](r)},se=function _setterFuncWithParam(t,e,r,i){return t[e](i.fp,r)},ue=function _getSetter(t,e){return s(t[e])?ae:u(t[e])&&t.setAttribute?qc:ie},fe=function _renderPlain(t,e){return e.set(e.t,e.p,Math.round(1e6*(e.s+e.c*t))/1e6,e)},de=function _renderBoolean(t,e){return e.set(e.t,e.p,!!(e.s+e.c*t),e)},pe=function _renderComplexString(t,e){var r=e._pt,i="";if(!t&&e.b)i=e.b;else if(1===t&&e.e)i=e.e;else{for(;r;)i=r.p+(r.m?r.m(r.s+r.c*t):Math.round(1e4*(r.s+r.c*t))/1e4)+i,r=r._next;i+=e.c}e.set(e.t,e.p,i,e)},_e=function _renderPropTweens(t,e){for(var r=e._pt;r;)r.r(t,r.d),r=r._next},ve=function _addPluginModifier(t,e,r,i){for(var n,a=this._pt;a;)n=a._next,a.p===i&&a.modifier(t,e,r),a=n},Te=function _killPropTweensOf(t){for(var e,r,i=this._pt;i;)r=i._next,i.p===t&&!i.op||i.op===t?Ba(this,i,"_pt"):i.dep||(e=1),i=r;return!e},be=function _sortPropTweensByPriority(t){for(var e,r,i,n,a=t._pt;a;){for(e=a._next,r=i;r&&r.pr>a.pr;)r=r._next;(a._prev=r?r._prev:n)?a._prev._next=a:i=a,(a._next=r)?r._prev=a:n=a,a=e}t._pt=i},we=(PropTween.prototype.modifier=function modifier(t,e,r){this.mSet=this.mSet||this.set,this.set=yc,this.m=t,this.mt=r,this.tween=e},PropTween);function PropTween(t,e,r,i,n,a,s,o,u){this.t=e,this.s=i,this.c=n,this.p=r,this.r=a||fe,this.d=s||this,this.set=o||ie,this.pr=u||0,(this._next=t)&&(t._prev=this)}ja(Tt+"parent,duration,ease,delay,overwrite,runBackwards,startAt,yoyo,immediateRender,repeat,repeatDelay,data,paused,reversed,lazy,callbackScope,stringFilter,id,yoyoEase,stagger,inherit,repeatRefresh,keyframes,autoRevert,scrollTrigger,easeReverse",function(t){return dt[t]=1}),ht.TweenMax=ht.TweenLite=te,ht.TimelineLite=ht.TimelineMax=Gt,I=new Gt({sortChildren:!1,defaults:j,autoRemoveChildren:!0,id:"root",smoothChildTiming:!0}),Y.stringFilter=Ib;function Gc(t){return(Oe[t]||Me).map(function(t){return t()})}function Hc(){var t=Date.now(),o=[];2<t-Ce&&(Gc("matchMediaInit"),ke.forEach(function(t){var e,r,i,n,a=t.queries,s=t.conditions;for(r in a)(e=h.matchMedia(a[r]).matches)&&(i=1),e!==s[r]&&(s[r]=e,n=1);n&&(t.revert(),i&&o.push(t))}),Gc("matchMediaRevert"),o.forEach(function(e){return e.onMatch(e,function(t){return e.add(null,t)})}),Ce=t,Gc("matchMedia"))}var xe,ke=[],Oe={},Me=[],Ce=0,Pe=0,Se=((xe=Context.prototype).add=function add(t,i,n){function Lw(){var t,e=l,r=a.selector;return e&&e!==a&&e.data.push(a),n&&(a.selector=fb(n)),l=a,t=i.apply(a,arguments),s(t)&&a._r.push(t),l=e,a.selector=r,a.isReverted=!1,t}s(t)&&(n=i,i=t,t=s);var a=this;return a.last=Lw,t===s?Lw(a,function(t){return a.add(null,t)}):t?a[t]=Lw:Lw},xe.ignore=function ignore(t){var e=l;l=null,t(this),l=e},xe.getTweens=function getTweens(){var e=[];return this.data.forEach(function(t){return t instanceof Context?e.push.apply(e,t.getTweens()):t instanceof te&&!(t.parent&&"nested"===t.parent.data)&&e.push(t)}),e},xe.clear=function clear(){this._r.length=this.data.length=0},xe.kill=function kill(i,t){var n=this;if(i?function(){for(var t,e=n.getTweens(),r=n.data.length;r--;)"isFlip"===(t=n.data[r]).data&&(t.revert(),t.getChildren(!0,!0,!1).forEach(function(t){return e.splice(e.indexOf(t),1)}));for(e.map(function(t){return{g:t._dur||t._delay||t._sat&&!t._sat.vars.immediateRender?t.globalTime(0):-1/0,t:t}}).sort(function(t,e){return e.g-t.g||-1/0}).forEach(function(t){return t.t.revert(i)}),r=n.data.length;r--;)(t=n.data[r])instanceof Gt?"nested"!==t.data&&(t.scrollTrigger&&t.scrollTrigger.revert(),t.kill()):t instanceof te||!t.revert||t.revert(i);n._r.forEach(function(t){return t(i,n)}),n.isReverted=!0}():this.data.forEach(function(t){return t.kill&&t.kill()}),this.clear(),t){for(var e=ke.length;e--;)ke[e].id===this.id&&ke.splice(e,1);this._mqs&&this._mqs.forEach(function(t){return t.removeListener?t.removeListener(Hc):t.removeEventListener("change",Hc)}),this._mqs=null}},xe.revert=function revert(t){this.kill(t||{})},Context);function Context(t,e){this.selector=e&&fb(e),this.data=[],this._r=[],this.isReverted=!1,this.id=Pe++,t&&this.add(t)}var De,Re=((De=MatchMedia.prototype).add=function add(t,e,r){v(t)||(t={matches:t});var i,n,a,s=new Se(0,r||this.scope),o=s.conditions={};for(n in l&&!s.selector&&(s.selector=l.selector),this.contexts.push(s),e=s.add("onMatch",e),s.queries=t)"all"===n?a=1:(i=h.matchMedia(t[n]))&&(ke.indexOf(s)<0&&ke.push(s),(o[n]=i.matches)&&(a=1),i.addListener?i.addListener(Hc):i.addEventListener("change",Hc),(s._mqs||(s._mqs=[])).push(i));return a&&e(s,function(t){return s.add(null,t)}),this},De.revert=function revert(t){this.kill(t||{})},De.kill=function kill(e){this.contexts.forEach(function(t){return t.kill(e,!0)})},MatchMedia);function MatchMedia(t){this.contexts=[],this.scope=t,l&&l.data.push(this)}var Ee={registerPlugin:function registerPlugin(){for(var t=arguments.length,e=new Array(t),r=0;r<t;r++)e[r]=arguments[r];e.forEach(function(t){return zb(t)})},timeline:function timeline(t){return new Gt(t)},getTweensOf:function getTweensOf(t,e){return I.getTweensOf(t,e)},getProperty:function getProperty(i,t,e,n){r(i)&&(i=Pt(i)[0]);var a=ha(i||{}).get,s=e?sa:ra;return"native"===e&&(e=""),i?t?s((mt[t]&&mt[t].get||a)(i,t,e,n)):function(t,e,r){return s((mt[t]&&mt[t].get||a)(i,t,e,r))}:i},quickSetter:function quickSetter(r,e,i){if(1<(r=Pt(r)).length){var n=r.map(function(t){return Fe.quickSetter(t,e,i)}),a=n.length;return function(t){for(var e=a;e--;)n[e](t)}}r=r[0]||{};var s=mt[e],o=ha(r),u=o.harness&&(o.harness.aliases||{})[e]||e,h=s?function(t){var e=new s;c._pt=0,e.init(r,i?t+i:t,c,0,[r]),e.render(1,e),c._pt&&_e(1,c)}:o.set(r,u);return s?h:function(t){return h(r,u,i?t+i:t,o,1)}},quickTo:function quickTo(t,i,e){function ey(t,e,r){return n.resetTo(i,t,e,r)}var r,n=Fe.to(t,ta(((r={})[i]="+=0.1",r.paused=!0,r.stagger=0,r),e||{}));return ey.tween=n,ey},isTweening:function isTweening(t){return 0<I.getTweensOf(t,!0).length},defaults:function defaults(t){return t&&t.ease&&(t.ease=jt(t.ease,j.ease)),wa(j,t||{})},config:function config(t){return wa(Y,t||{})},registerEffect:function registerEffect(t){var i=t.name,n=t.effect,e=t.plugins,a=t.defaults,r=t.extendTimeline;(e||"").split(",").forEach(function(t){return t&&!mt[t]&&!ht[t]&&T(i+" effect requires "+t+" plugin.")}),gt[i]=function(t,e,r){return n(Pt(t),ta(e||{},a),r)},r&&(Gt.prototype[i]=function(t,e,r){return this.add(gt[i](t,v(e)?e:(r=e)&&{},this),r)})},registerEase:function registerEase(t,e){Bt[t]=jt(e)},parseEase:function parseEase(t,e){return arguments.length?jt(t,e):Bt},getById:function getById(t){return I.getById(t)},exportRoot:function exportRoot(t,e){void 0===t&&(t={});var r,i,n=new Gt(t);for(n.smoothChildTiming=w(t.smoothChildTiming),I.remove(n),n._dp=0,n._time=n._tTime=I._time,r=I._first;r;)i=r._next,!e&&!r._dur&&r instanceof te&&r.vars.onComplete===r._targets[0]||Na(n,r,r._start-r._delay),r=i;return Na(I,n,0),n},context:function context(t,e){return t?new Se(t,e):l},matchMedia:function matchMedia(t){return new Re(t)},matchMediaRefresh:function matchMediaRefresh(){return ke.forEach(function(t){var e,r,i=t.conditions;for(r in i)i[r]&&(i[r]=!1,e=1);e&&t.revert()})||Hc()},addEventListener:function addEventListener(t,e){var r=Oe[t]||(Oe[t]=[]);~r.indexOf(e)||r.push(e)},removeEventListener:function removeEventListener(t,e){var r=Oe[t],i=r&&r.indexOf(e);0<=i&&r.splice(i,1)},utils:{wrap:function wrap(e,t,r){var i=t-e;return K(e)?ob(e,wrap(0,e.length),t):Za(r,function(t){return(i+(t-e)%i)%i+e})},wrapYoyo:function wrapYoyo(e,t,r){var i=t-e,n=2*i;return K(e)?ob(e,wrapYoyo(0,e.length-1),t):Za(r,function(t){return e+(i<(t=(n+(t-e)%n)%n||0)?n-t:t)})},distribute:hb,random:kb,snap:jb,normalize:function normalize(t,e,r){return St(t,e,0,1,r)},getUnit:_a,clamp:function clamp(e,r,t){return Za(t,function(t){return Mt(e,r,t)})},splitColor:Db,toArray:Pt,selector:fb,mapRange:St,pipe:function pipe(){for(var t=arguments.length,e=new Array(t),r=0;r<t;r++)e[r]=arguments[r];return function(t){return e.reduce(function(t,e){return e(t)},t)}},unitize:function unitize(e,r){return function(t){return e(parseFloat(t))+(r||_a(t))}},interpolate:function interpolate(e,i,t,n){var a=isNaN(e+i)?0:function(t){return(1-t)*e+t*i};if(!a){var s,o,u,h,l,f=r(e),c={};if(!0===t&&(n=1)&&(t=null),f)e={p:e},i={p:i};else if(K(e)&&!K(i)){for(u=[],h=e.length,l=h-2,o=1;o<h;o++)u.push(interpolate(e[o-1],e[o]));h--,a=function func(t){t*=h;var e=Math.min(l,~~t);return u[e](t-e)},t=i}else n||(e=bt(K(e)?[]:{},e));if(!u){for(s in i)Ht.call(c,e,s,"get",i[s]);a=function func(t){return _e(t,c)||(f?e.p:e)}}}return Za(t,a)},shuffle:gb},install:R,effects:gt,ticker:Lt,updateRoot:Gt.updateRoot,plugins:mt,globalTimeline:I,core:{PropTween:we,globals:U,Tween:te,Timeline:Gt,Animation:Xt,getCache:ha,_removeLinkedListItem:Ba,reverting:function reverting(){return L},context:function context(t){return t&&l&&(l.data.push(t),t._ctx=l),l},suppressOverwrites:function suppressOverwrites(t){return F=t}}};ja("to,from,fromTo,delayedCall,set,killTweensOf",function(t){return Ee[t]=te[t]}),Lt.add(Gt.updateRoot),c=Ee.to({},{duration:0});function Lc(t,e){for(var r=t._pt;r&&r.p!==e&&r.op!==e&&r.fp!==e;)r=r._next;return r}function Nc(t,a){return{name:t,headless:1,rawVars:1,init:function init(t,n,e){e._onInit=function(t){var e,i;if(r(n)&&(e={},ja(n,function(t){return e[t]=1}),n=e),a){for(i in e={},n)e[i]=a(n[i]);n=e}!function _addModifiers(t,e){var r,i,n,a=t._targets;for(r in e)for(i=a.length;i--;)(n=(n=t._ptLookup[i][r])&&n.d)&&(n._pt&&(n=Lc(n,r)),n&&n.modifier&&n.modifier(e[r],t,a[i],r))}(t,n)}}}}var Fe=Ee.registerPlugin({name:"attr",init:function init(t,e,r,i,n){var a,s,o;for(a in this.tween=r,e)o=t.getAttribute(a)||"",(s=this.add(t,"setAttribute",(o||0)+"",e[a],i,n,0,0,a)).op=a,s.b=o,this._props.push(a)},render:function render(t,e){for(var r=e._pt;r;)L?r.set(r.t,r.p,r.b,r):r.r(t,r.d),r=r._next}},{name:"endArray",headless:1,init:function init(t,e){for(var r=e.length;r--;)this.add(t,r,t[r]||0,e[r],0,0,0,0,0,1)}},Nc("roundProps",ib),Nc("modifiers"),Nc("snap",jb))||Ee;te.version=Gt.version=Fe.version="3.15.1",o=1,x()&&It();function xd(t,e){return e.set(e.t,e.p,Math.round(1e4*(e.s+e.c*t))/1e4+e.u,e)}function yd(t,e){return e.set(e.t,e.p,1===t?e.e:Math.round(1e4*(e.s+e.c*t))/1e4+e.u,e)}function zd(t,e){return e.set(e.t,e.p,t?Math.round(1e4*(e.s+e.c*t))/1e4+e.u:e.b,e)}function Ad(t,e){return e.set(e.t,e.p,1===t?e.e:t?Math.round(1e4*(e.s+e.c*t))/1e4+e.u:e.b,e)}function Bd(t,e){var r=e.s+e.c*t;e.set(e.t,e.p,~~(r+(r<0?-.5:.5))+e.u,e)}function Cd(t,e){return e.set(e.t,e.p,t?e.e:e.b,e)}function Dd(t,e){return e.set(e.t,e.p,1!==t?e.b:e.e,e)}function Ed(t,e,r){return t.style[e]=r}function Fd(t,e,r){return t.style.setProperty(e,r)}function Gd(t,e,r){return t._gsap[e]=r}function Hd(t,e,r){return t._gsap.scaleX=t._gsap.scaleY=r}function Id(t,e,r,i,n){var a=t._gsap;a.scaleX=a.scaleY=r,a.renderTransform(n,a)}function Jd(t,e,r,i,n){var a=t._gsap;a[e]=r,a.renderTransform(n,a)}function Md(t,e){var r=this,i=this.target,n=i.style,a=i._gsap;if(t in ur&&n){if(this.tfm=this.tfm||{},"transform"===t)return _r.transform.split(",").forEach(function(t){return Md.call(r,t,e)});if(~(t=_r[t]||t).indexOf(",")?t.split(",").forEach(function(t){return r.tfm[t]=wr(i,t)}):this.tfm[t]=a.x?a[t]:wr(i,t),t===gr&&(this.tfm.zOrigin=a.zOrigin),0<=this.props.indexOf(mr))return;a.svg&&(this.svgo=i.getAttribute("data-svg-origin"),this.props.push(gr,e,"")),t=mr}(n||e)&&this.props.push(t,e,n[t])}function Nd(t){t.translate&&(t.removeProperty("translate"),t.removeProperty("scale"),t.removeProperty("rotate"))}function Od(){var t,e,r=this.props,i=this.target,n=i.style,a=i._gsap;for(t=0;t<r.length;t+=3)r[t+1]?2===r[t+1]?i[r[t]](r[t+2]):i[r[t]]=r[t+2]:r[t+2]?n[r[t]]=r[t+2]:n.removeProperty("--"===r[t].substr(0,2)?r[t]:r[t].replace(cr,"-$1").toLowerCase());if(this.tfm){for(e in this.tfm)a[e]=this.tfm[e];a.svg&&(a.renderTransform(),i.setAttribute("data-svg-origin",this.svgo||"")),(t=je())&&t.isStart||n[mr]||(Nd(n),a.zOrigin&&n[gr]&&(n[gr]+=" "+a.zOrigin+"px",a.zOrigin=0,a.renderTransform()),a.uncache=1)}}function Pd(t,e){var r={target:t,props:[],revert:Od,save:Md};return t._gsap||Fe.core.getCache(t),e&&t.style&&t.nodeType&&e.split(",").forEach(function(t){return r.save(t)}),r}function Rd(t,e){var r=Ie.createElementNS?Ie.createElementNS((e||"http://www.w3.org/1999/xhtml").replace(/^https/,"http"),t):Ie.createElement(t);return r&&r.style?r:Ie.createElement(t)}function Sd(t,e,r){var i=getComputedStyle(t);return i[e]||i.getPropertyValue(e.replace(cr,"-$1").toLowerCase())||i.getPropertyValue(e)||!r&&Sd(t,yr(e)||e,1)||""}function Vd(){(function _windowExists(){return"undefined"!=typeof window})()&&window.document&&(Le=window,Ie=Le.document,Be=Ie.documentElement,Ue=Rd("div")||{style:{}},Rd("div"),mr=yr(mr),gr=mr+"Origin",Ue.style.cssText="border-width:0;line-height:0;position:absolute;padding:0",Ve=!!yr("perspective"),je=Fe.core.reverting,Ne=1)}function Wd(t){var e,r=t.ownerSVGElement,i=Rd("svg",r&&r.getAttribute("xmlns")||"http://www.w3.org/2000/svg"),n=t.cloneNode(!0);n.style.display="block",i.appendChild(n),Be.appendChild(i);try{e=n.getBBox()}catch(t){}return i.removeChild(n),Be.removeChild(i),e}function Xd(t,e){for(var r=e.length;r--;)if(t.hasAttribute(e[r]))return t.getAttribute(e[r])}function Yd(e){var r,i;try{r=e.getBBox()}catch(t){r=Wd(e),i=1}return r&&(r.width||r.height)||i||(r=Wd(e)),!r||r.width||r.x||r.y?r:{x:+Xd(e,["x","cx","x1"])||0,y:+Xd(e,["y","cy","y1"])||0,width:0,height:0}}function Zd(t){return!(!t.getCTM||t.parentNode&&!t.ownerSVGElement||!Yd(t))}function $d(t,e){if(e){var r,i=t.style;e in ur&&e!==gr&&(e=mr),i.removeProperty?("ms"!==(r=e.substr(0,2))&&"webkit"!==e.substr(0,6)||(e="-"+e),i.removeProperty("--"===r?e:e.replace(cr,"-$1").toLowerCase())):i.removeAttribute(e)}}function _d(t,e,r,i,n,a){var s=new we(t._pt,e,r,0,1,a?Dd:Cd);return(t._pt=s).b=i,s.e=n,t._props.push(r),s}function ce(t,e,r,i){var n,a,s,o,u=parseFloat(r)||0,h=(r+"").trim().substr((u+"").length)||"px",l=Ue.style,f=dr.test(e),c="svg"===t.tagName.toLowerCase(),d=(c?"client":"offset")+(f?"Width":"Height"),p="px"===i,_="%"===i;if(i===h||!u||Tr[i]||Tr[h])return u;if("px"===h||p||(u=ce(t,e,r,"px")),o=t.getCTM&&Zd(t),(_||"%"===h)&&(ur[e]||~e.indexOf("adius")))return n=o?t.getBBox()[f?"width":"height"]:t[d],ka(_?u/n*100:u/100*n);if(l[f?"width":"height"]=100+(p?h:i),a="rem"!==i&&~e.indexOf("adius")||"em"===i&&t.appendChild&&!c?t:t.parentNode,o&&(a=(t.ownerSVGElement||{}).parentNode),a&&a!==Ie&&a.appendChild||(a=Ie.body),(s=a._gsap)&&_&&s.width&&f&&s.time===Lt.time&&!s.uncache)return ka(u/s.width*100);if(!_||"height"!==e&&"width"!==e)!_&&"%"!==h||br[Sd(a,"display")]||(l.position=Sd(t,"position")),a===t&&(l.position="static"),a.appendChild(Ue),n=Ue[d],a.removeChild(Ue),l.position="absolute";else{var m=t.style[e];t.style[e]=100+i,n=t[d],m?t.style[e]=m:$d(t,e)}return f&&_&&((s=ha(a)).time=Lt.time,s.width=a[d]),ka(p?n*u/100:n&&u?100/n*u:0)}function ee(t,e,r,i){if(!r||"none"===r){var n=yr(e,t,1),a=n&&Sd(t,n,1);a&&a!==r?(e=n,r=a):"borderColor"===e&&(r=Sd(t,"borderTopColor"))}var s,o,u,h,l,f,c,d,p,_,m,g=new we(this._pt,t.style,e,0,1,pe),v=0,y=0;if(g.b=r,g.e=i,r+="","var(--"===(i+="").substring(0,6)&&(i=Sd(t,i.substring(4,i.indexOf(")")))),"auto"===i&&(f=t.style[e],t.style[e]=i,i=Sd(t,e)||i,f?t.style[e]=f:$d(t,e)),Ib(s=[r,i]),i=s[1],u=(r=s[0]).match(nt)||[],(i.match(nt)||[]).length){for(;o=nt.exec(i);)c=o[0],p=i.substring(v,o.index),l?l=(l+1)%5:"rgba("!==p.substr(-5)&&"hsla("!==p.substr(-5)||(l=1),c!==(f=u[y++]||"")&&(h=parseFloat(f)||0,m=f.substr((h+"").length),"="===c.charAt(1)&&(c=ma(h,c)+m),d=parseFloat(c),_=c.substr((d+"").length),v=nt.lastIndex-_.length,_||(_=_||Y.units[e]||m,v===i.length&&(i+=_,g.e+=_)),m!==_&&(h=ce(t,e,f,_)||0),g._pt={_next:g._pt,p:p||1===y?p:",",s:h,c:d-h,m:l&&l<4||"zIndex"===e?Math.round:0});g.c=v<i.length?i.substring(v,i.length):""}else g.r="display"===e&&"none"===i?Dd:Cd;return st.test(i)&&(g.e=0),this._pt=g}function ge(t){var e=t.split(" "),r=e[0],i=e[1]||"50%";return"top"!==r&&"bottom"!==r&&"left"!==i&&"right"!==i||(t=r,r=i,i=t),e[0]=xr[r]||r,e[1]=xr[i]||i,e.join(" ")}function he(t,e){if(e.tween&&e.tween._time===e.tween._dur){var r,i,n,a=e.t,s=a.style,o=e.u,u=a._gsap;if("all"===o||!0===o)s.cssText="",i=1;else for(n=(o=o.split(",")).length;-1<--n;)r=o[n],ur[r]&&(i=1,r="transformOrigin"===r?gr:mr),$d(a,r);i&&($d(a,mr),u&&(u.svg&&a.removeAttribute("transform"),s.scale=s.rotate=s.translate="none",Cr(a,1),u.uncache=1,Nd(s)))}}function le(t){return"matrix(1, 0, 0, 1, 0, 0)"===t||"none"===t||!t}function me(t){var e=Sd(t,mr);return le(e)?Or:e.substr(7).match(it).map(ka)}function ne(t,e){var r,i,n,a,s=t._gsap||ha(t),o=t.style,u=me(t);return s.svg&&t.getAttribute("transform")?"1,0,0,1,0,0"===(u=[(n=t.transform.baseVal.consolidate().matrix).a,n.b,n.c,n.d,n.e,n.f]).join(",")?Or:u:(u!==Or||t.offsetParent||t===Be||s.svg||(n=o.display,o.display="block",(r=t.parentNode)&&(t.offsetParent||t.getBoundingClientRect().width)||(a=1,i=t.nextElementSibling,Be.appendChild(t)),u=me(t),n?o.display=n:$d(t,"display"),a&&(i?r.insertBefore(t,i):r?r.appendChild(t):Be.removeChild(t))),e&&6<u.length?[u[0],u[1],u[4],u[5],u[12],u[13]]:u)}function oe(t,e,r,i,n,a){var s,o,u,h=t._gsap,l=n||ne(t,!0),f=h.xOrigin||0,c=h.yOrigin||0,d=h.xOffset||0,p=h.yOffset||0,_=l[0],m=l[1],g=l[2],v=l[3],y=l[4],T=l[5],b=e.split(" "),w=parseFloat(b[0])||0,x=parseFloat(b[1])||0;r?l!==Or&&(o=_*v-m*g)&&(u=w*(-m/o)+x*(_/o)-(_*T-m*y)/o,w=w*(v/o)+x*(-g/o)+(g*T-v*y)/o,x=u):(w=(s=Yd(t)).x+(~b[0].indexOf("%")?w/100*s.width:w),x=s.y+(~(b[1]||b[0]).indexOf("%")?x/100*s.height:x)),i||!1!==i&&h.smooth?(y=w-f,T=x-c,h.xOffset=d+(y*_+T*g)-y,h.yOffset=p+(y*m+T*v)-T):h.xOffset=h.yOffset=0,h.xOrigin=w,h.yOrigin=x,h.smooth=!!i,h.origin=e,h.originIsAbsolute=!!r,t.style[gr]="0px 0px",a&&(_d(a,h,"xOrigin",f,w),_d(a,h,"yOrigin",c,x),_d(a,h,"xOffset",d,h.xOffset),_d(a,h,"yOffset",p,h.yOffset)),t.setAttribute("data-svg-origin",w+" "+x)}function re(t,e,r){var i=_a(e);return ka(parseFloat(e)+parseFloat(ce(t,"x",r+"px",i)))+i}function ye(t,e,i,n,a){var s,o,u=360,h=r(a),l=parseFloat(a)*(h&&~a.indexOf("rad")?hr:1)-n,f=n+l+"deg";return h&&("short"===(s=a.split("_")[1])&&(l%=u)!==l%180&&(l+=l<0?u:-u),"cw"===s&&l<0?l=(l+36e9)%u-~~(l/u)*u:"ccw"===s&&0<l&&(l=(l-36e9)%u-~~(l/u)*u)),t._pt=o=new we(t._pt,e,i,n,l,yd),o.e=f,o.u="deg",t._props.push(i),o}function ze(t,e){for(var r in e)t[r]=e[r];return t}function Ae(t,e,r){var i,n,a,s,o,u,h,l=ze({},r._gsap),f=r.style;for(n in l.svg?(a=r.getAttribute("transform"),r.setAttribute("transform",""),f[mr]=e,i=Cr(r,1),$d(r,mr),r.setAttribute("transform",a)):(a=getComputedStyle(r)[mr],f[mr]=e,i=Cr(r,1),f[mr]=a),ur)(a=l[n])!==(s=i[n])&&"perspective,force3D,transformOrigin,svgOrigin".indexOf(n)<0&&(o=_a(a)!==(h=_a(s))?ce(r,n,a,h):parseFloat(a),u=parseFloat(s),t._pt=new we(t._pt,i,n,o,u-o,xd),t._pt.u=h||0,t._props.push(n));ze(i,l)}var Le,Ie,Be,Ne,Ue,Ye,je,Ve,qe=Bt.Power0,Xe=Bt.Power1,Ge=Bt.Power2,Ze=Bt.Power3,We=Bt.Power4,He=Bt.Linear,$e=Bt.Quad,Qe=Bt.Cubic,Je=Bt.Quart,Ke=Bt.Quint,tr=Bt.Strong,er=Bt.Elastic,rr=Bt.Back,ir=Bt.SteppedEase,nr=Bt.Bounce,ar=Bt.Sine,sr=Bt.Expo,or=Bt.Circ,ur={},hr=180/Math.PI,lr=Math.PI/180,fr=Math.atan2,cr=/([A-Z])/g,dr=/(left|right|width|margin|padding|x)/i,pr=/[\s,\(]\S/,_r={autoAlpha:"opacity,visibility",scale:"scaleX,scaleY",alpha:"opacity"},mr="transform",gr=mr+"Origin",vr="O,Moz,ms,Ms,Webkit".split(","),yr=function _checkPropPrefix(t,e,r){var i=(e||Ue).style,n=5;if(t in i&&!r)return t;for(t=t.charAt(0).toUpperCase()+t.substr(1);n--&&!(vr[n]+t in i););return n<0?null:(3===n?"ms":0<=n?vr[n]:"")+t},Tr={deg:1,rad:1,turn:1},br={grid:1,flex:1},wr=function _get(t,e,r,i){var n;return Ne||Vd(),e in _r&&"transform"!==e&&~(e=_r[e]).indexOf(",")&&(e=e.split(",")[0]),ur[e]&&"transform"!==e?(n=Cr(t,i),n="transformOrigin"!==e?n[e]:n.svg?n.origin:Pr(Sd(t,gr))+" "+n.zOrigin+"px"):(n=t.style[e])&&"auto"!==n&&!i&&!~(n+"").indexOf("calc(")||(n=kr[e]&&kr[e](t,e,r)||Sd(t,e)||ia(t,e)||("opacity"===e?1:0)),r&&!~(n+"").trim().indexOf(" ")?ce(t,e,n,r)+r:n},xr={top:"0%",bottom:"100%",left:"0%",right:"100%",center:"50%"},kr={clearProps:function clearProps(t,e,r,i,n){if("isFromStart"!==n.data){var a=t._pt=new we(t._pt,e,r,0,0,he);return a.u=i,a.pr=-10,a.tween=n,t._props.push(r),1}}},Or=[1,0,0,1,0,0],Mr={},Cr=function _parseTransform(t,e){var r=t._gsap||new qt(t);if("x"in r&&!e&&!r.uncache)return r;var i,n,a,s,o,u,h,l,f,c,d,p,_,m,g,v,y,T,b,w,x,k,O,M,C,P,S,A,D,z,R,E,F=t.style,L=r.scaleX<0,I="deg",B=getComputedStyle(t),N=Sd(t,gr)||"0";return i=n=a=u=h=l=f=c=d=0,s=o=1,r.svg=!(!t.getCTM||!Zd(t)),B.translate&&("none"===B.translate&&"none"===B.scale&&"none"===B.rotate||(F[mr]=("none"!==B.translate?"translate3d("+(B.translate+" 0 0").split(" ").slice(0,3).join(", ")+") ":"")+("none"!==B.rotate?"rotate("+B.rotate+") ":"")+("none"!==B.scale?"scale("+B.scale.split(" ").join(",")+") ":"")+("none"!==B[mr]?B[mr]:"")),F.scale=F.rotate=F.translate="none"),m=ne(t,r.svg),r.svg&&(M=r.uncache?(C=t.getBBox(),N=r.xOrigin-C.x+"px "+(r.yOrigin-C.y)+"px",""):!e&&t.getAttribute("data-svg-origin"),oe(t,M||N,!!M||r.originIsAbsolute,!1!==r.smooth,m)),p=r.xOrigin||0,_=r.yOrigin||0,m!==Or&&(T=m[0],b=m[1],w=m[2],x=m[3],i=k=m[4],n=O=m[5],6===m.length?(s=Math.sqrt(T*T+b*b),o=Math.sqrt(x*x+w*w),u=T||b?fr(b,T)*hr:0,(f=w||x?fr(w,x)*hr+u:0)&&(o*=Math.abs(Math.cos(f*lr))),r.svg&&(i-=p-(p*T+_*w),n-=_-(p*b+_*x))):(E=m[6],z=m[7],S=m[8],A=m[9],D=m[10],R=m[11],i=m[12],n=m[13],a=m[14],h=(g=fr(E,D))*hr,g&&(M=k*(v=Math.cos(-g))+S*(y=Math.sin(-g)),C=O*v+A*y,P=E*v+D*y,S=k*-y+S*v,A=O*-y+A*v,D=E*-y+D*v,R=z*-y+R*v,k=M,O=C,E=P),l=(g=fr(-w,D))*hr,g&&(v=Math.cos(-g),R=x*(y=Math.sin(-g))+R*v,T=M=T*v-S*y,b=C=b*v-A*y,w=P=w*v-D*y),u=(g=fr(b,T))*hr,g&&(M=T*(v=Math.cos(g))+b*(y=Math.sin(g)),C=k*v+O*y,b=b*v-T*y,O=O*v-k*y,T=M,k=C),h&&359.9<Math.abs(h)+Math.abs(u)&&(h=u=0,l=180-l),s=ka(Math.sqrt(T*T+b*b+w*w)),o=ka(Math.sqrt(O*O+E*E)),g=fr(k,O),f=2e-4<Math.abs(g)?g*hr:0,d=R?1/(R<0?-R:R):0),r.svg&&(M=t.getAttribute("transform"),r.forceCSS=t.setAttribute("transform","")||!le(Sd(t,mr)),M&&t.setAttribute("transform",M))),90<Math.abs(f)&&Math.abs(f)<270&&(L?(s*=-1,f+=u<=0?180:-180,u+=u<=0?180:-180):(o*=-1,f+=f<=0?180:-180)),e=e||r.uncache,r.x=i-((r.xPercent=i&&(!e&&r.xPercent||(Math.round(t.offsetWidth/2)===Math.round(-i)?-50:0)))?t.offsetWidth*r.xPercent/100:0)+"px",r.y=n-((r.yPercent=n&&(!e&&r.yPercent||(Math.round(t.offsetHeight/2)===Math.round(-n)?-50:0)))?t.offsetHeight*r.yPercent/100:0)+"px",r.z=a+"px",r.scaleX=ka(s),r.scaleY=ka(o),r.rotation=ka(u)+I,r.rotationX=ka(h)+I,r.rotationY=ka(l)+I,r.skewX=f+I,r.skewY=c+I,r.transformPerspective=d+"px",(r.zOrigin=parseFloat(N.split(" ")[2])||!e&&r.zOrigin||0)&&(F[gr]=Pr(N)),r.xOffset=r.yOffset=0,r.force3D=Y.force3D,r.renderTransform=r.svg?Er:Ve?Rr:Sr,r.uncache=0,r},Pr=function _firstTwoOnly(t){return(t=t.split(" "))[0]+" "+t[1]},Sr=function _renderNon3DTransforms(t,e){e.z="0px",e.rotationY=e.rotationX="0deg",e.force3D=0,Rr(t,e)},Ar="0deg",Dr="0px",zr=") ",Rr=function _renderCSSTransforms(t,e){var r=e||this,i=r.xPercent,n=r.yPercent,a=r.x,s=r.y,o=r.z,u=r.rotation,h=r.rotationY,l=r.rotationX,f=r.skewX,c=r.skewY,d=r.scaleX,p=r.scaleY,_=r.transformPerspective,m=r.force3D,g=r.target,v=r.zOrigin,y="",T="auto"===m&&t&&1!==t||!0===m;if(v&&(l!==Ar||h!==Ar)){var b,w=parseFloat(h)*lr,x=Math.sin(w),k=Math.cos(w);w=parseFloat(l)*lr,b=Math.cos(w),a=re(g,a,x*b*-v),s=re(g,s,-Math.sin(w)*-v),o=re(g,o,k*b*-v+v)}_!==Dr&&(y+="perspective("+_+zr),(i||n)&&(y+="translate("+i+"%, "+n+"%) "),!T&&a===Dr&&s===Dr&&o===Dr||(y+=o!==Dr||T?"translate3d("+a+", "+s+", "+o+") ":"translate("+a+", "+s+zr),u!==Ar&&(y+="rotate("+u+zr),h!==Ar&&(y+="rotateY("+h+zr),l!==Ar&&(y+="rotateX("+l+zr),f===Ar&&c===Ar||(y+="skew("+f+", "+c+zr),1===d&&1===p||(y+="scale("+d+", "+p+zr),g.style[mr]=y||"translate(0, 0)"},Er=function _renderSVGTransforms(t,e){var r,i,n,a,s,o=e||this,u=o.xPercent,h=o.yPercent,l=o.x,f=o.y,c=o.rotation,d=o.skewX,p=o.skewY,_=o.scaleX,m=o.scaleY,g=o.target,v=o.xOrigin,y=o.yOrigin,T=o.xOffset,b=o.yOffset,w=o.forceCSS,x=parseFloat(l),k=parseFloat(f);c=parseFloat(c),d=parseFloat(d),(p=parseFloat(p))&&(d+=p=parseFloat(p),c+=p),c||d?(c*=lr,d*=lr,r=Math.cos(c)*_,i=Math.sin(c)*_,n=Math.sin(c-d)*-m,a=Math.cos(c-d)*m,d&&(p*=lr,s=Math.tan(d-p),n*=s=Math.sqrt(1+s*s),a*=s,p&&(s=Math.tan(p),r*=s=Math.sqrt(1+s*s),i*=s)),r=ka(r),i=ka(i),n=ka(n),a=ka(a)):(r=_,a=m,i=n=0),(x&&!~(l+"").indexOf("px")||k&&!~(f+"").indexOf("px"))&&(x=ce(g,"x",l,"px"),k=ce(g,"y",f,"px")),(v||y||T||b)&&(x=ka(x+v-(v*r+y*n)+T),k=ka(k+y-(v*i+y*a)+b)),(u||h)&&(s=g.getBBox(),x=ka(x+u/100*s.width),k=ka(k+h/100*s.height)),s="matrix("+r+","+i+","+n+","+a+","+x+","+k+")",g.setAttribute("transform",s),w&&(g.style[mr]=s)};ja("padding,margin,Width,Radius",function(e,r){var t="Right",i="Bottom",n="Left",o=(r<3?["Top",t,i,n]:["Top"+n,"Top"+t,i+t,i+n]).map(function(t){return r<2?e+t:"border"+t+e});kr[1<r?"border"+e:e]=function(e,t,r,i,n){var a,s;if(arguments.length<4)return a=o.map(function(t){return wr(e,t,r)}),5===(s=a.join(" ")).split(a[0]).length?a[0]:s;a=(i+"").split(" "),s={},o.forEach(function(t,e){return s[t]=a[e]=a[e]||a[(e-1)/2|0]}),e.init(t,s,n)}});var Fr,Lr,Ir,Br={name:"css",register:Vd,targetTest:function targetTest(t){return t.style&&t.nodeType},init:function init(t,e,i,n,a){var s,o,u,h,l,f,c,d,p,_,m,g,v,y,T,b,w,x=this._props,k=t.style,O=i.vars.startAt;for(c in Ne||Vd(),this.styles=this.styles||Pd(t),b=this.styles.props,this.tween=i,e)if("autoRound"!==c&&(o=e[c],!mt[c]||!cc(c,e,i,n,t,a)))if(l=typeof o,f=kr[c],"function"===l&&(l=typeof(o=o.call(i,n,t,a))),"string"===l&&~o.indexOf("random(")&&(o=rb(o)),f)f(this,t,c,o,i)&&(T=1);else if("--"===c.substr(0,2))s=(getComputedStyle(t).getPropertyValue(c)+"").trim(),o+="",Et.lastIndex=0,Et.test(s)||(d=_a(s),(p=_a(o))?d!==p&&(s=ce(t,c,s,p)+p):d&&(o+=d)),this.add(k,"setProperty",s,o,n,a,0,0,c),x.push(c),b.push(c,0,k[c]);else if("undefined"!==l){if(O&&c in O?(s="function"==typeof O[c]?O[c].call(i,n,t,a):O[c],r(s)&&~s.indexOf("random(")&&(s=rb(s)),_a(s+"")||"auto"===s||(s+=Y.units[c]||_a(wr(t,c))||""),"="===(s+"").charAt(1)&&(s=wr(t,c))):s=wr(t,c),h=parseFloat(s),(_="string"===l&&"="===o.charAt(1)&&o.substr(0,2))&&(o=o.substr(2)),u=parseFloat(o),c in _r&&("autoAlpha"===c&&(1===h&&"hidden"===wr(t,"visibility")&&u&&(h=0),b.push("visibility",0,k.visibility),_d(this,k,"visibility",h?"inherit":"hidden",u?"inherit":"hidden",!u)),"scale"!==c&&"transform"!==c&&~(c=_r[c]).indexOf(",")&&(c=c.split(",")[0])),m=c in ur){if(this.styles.save(c),w=o,"string"===l&&"var(--"===o.substring(0,6)){if("calc("===(o=Sd(t,o.substring(4,o.indexOf(")")))).substring(0,5)){var M=t.style.perspective;t.style.perspective=o,o=Sd(t,"perspective"),M?t.style.perspective=M:$d(t,"perspective")}u=parseFloat(o)}if(g||((v=t._gsap).renderTransform&&!e.parseTransform||Cr(t,e.parseTransform),y=!1!==e.smoothOrigin&&v.smooth,(g=this._pt=new we(this._pt,k,mr,0,1,v.renderTransform,v,0,-1)).dep=1),"scale"===c)this._pt=new we(this._pt,v,"scaleY",v.scaleY,(_?ma(v.scaleY,_+u):u)-v.scaleY||0,xd),this._pt.u=0,x.push("scaleY",c),c+="X";else{if("transformOrigin"===c){b.push(gr,0,k[gr]),o=ge(o),v.svg?oe(t,o,0,y,0,this):((p=parseFloat(o.split(" ")[2])||0)!==v.zOrigin&&_d(this,v,"zOrigin",v.zOrigin,p),_d(this,k,c,Pr(s),Pr(o)));continue}if("svgOrigin"===c){oe(t,o,1,y,0,this);continue}if(c in Mr){ye(this,v,c,h,_?ma(h,_+o):o);continue}if("smoothOrigin"===c){_d(this,v,"smooth",v.smooth,o);continue}if("force3D"===c){v[c]=o;continue}if("transform"===c){Ae(this,o,t);continue}}}else c in k||(c=yr(c)||c);if(m||(u||0===u)&&(h||0===h)&&!pr.test(o)&&c in k)u=u||0,(d=(s+"").substr((h+"").length))!==(p=_a(o)||(c in Y.units?Y.units[c]:d))&&(h=ce(t,c,s,p)),this._pt=new we(this._pt,m?v:k,c,h,(_?ma(h,_+u):u)-h,m||"px"!==p&&"zIndex"!==c||!1===e.autoRound?xd:Bd),this._pt.u=p||0,m&&w!==o?(this._pt.b=s,this._pt.e=w,this._pt.r=Ad):d!==p&&"%"!==p&&(this._pt.b=s,this._pt.r=zd);else if(c in k)ee.call(this,t,c,s,_?_+o:o);else if(c in t)this.add(t,c,s||t[c],_?_+o:o,n,a);else if("parseTransform"!==c){S(c,o);continue}m||(c in k?b.push(c,0,k[c]):"function"==typeof t[c]?b.push(c,2,t[c]()):b.push(c,1,s||t[c])),x.push(c)}T&&be(this)},render:function render(t,e){if(e.tween._time||!je())for(var r=e._pt;r;)r.r(t,r.d),r=r._next;else e.styles.revert()},get:wr,aliases:_r,getSetter:function getSetter(t,e,r){var i=_r[e];return i&&i.indexOf(",")<0&&(e=i),e in ur&&e!==gr&&(t._gsap.x||wr(t,"x"))?r&&Ye===r?"scale"===e?Hd:Gd:(Ye=r||{})&&("scale"===e?Id:Jd):t.style&&!u(t.style[e])?Ed:~e.indexOf("-")?Fd:ue(t,e)},core:{_removeProperty:$d,_getMatrix:ne}};Fe.utils.checkPrefix=yr,Fe.core.getStyleSaver=Pd,Ir=ja((Fr="x,y,z,scale,scaleX,scaleY,xPercent,yPercent")+","+(Lr="rotation,rotationX,rotationY,skewX,skewY")+",transform,transformOrigin,svgOrigin,force3D,smoothOrigin,transformPerspective",function(t){ur[t]=1}),ja(Lr,function(t){Y.units[t]="deg",Mr[t]=1}),_r[Ir[13]]=Fr+","+Lr,ja("0:translateX,1:translateY,2:translateZ,8:rotate,8:rotationZ,8:rotateZ,9:rotateX,10:rotateY",function(t){var e=t.split(":");_r[e[1]]=Ir[e[0]]}),ja("x,y,z,top,right,bottom,left,width,height,fontSize,padding,margin,perspective",function(t){Y.units[t]="px"}),Fe.registerPlugin(Br);var Nr=Fe.registerPlugin(Br)||Fe,Ur=Nr.core.Tween;e.Back=rr,e.Bounce=nr,e.CSSPlugin=Br,e.Circ=or,e.Cubic=Qe,e.Elastic=er,e.Expo=sr,e.Linear=He,e.Power0=qe,e.Power1=Xe,e.Power2=Ge,e.Power3=Ze,e.Power4=We,e.Quad=$e,e.Quart=Je,e.Quint=Ke,e.Sine=ar,e.SteppedEase=ir,e.Strong=tr,e.TimelineLite=Gt,e.TimelineMax=Gt,e.TweenLite=te,e.TweenMax=Ur,e.default=Nr,e.gsap=Nr;if (typeof(window)==="undefined"||window!==e){Object.defineProperty(e,"__esModule",{value:!0})} else {delete e.default}});


  </script>
  <script>
/* =================== 0.5. VENDOR: canvas-confetti (ISC) =================== */
// canvas-confetti v1.9.4 built on 2025-10-25T05:14:56.640Z
!(function (window, module) {
// source content
/* globals Map */

(function main(global, module, isWorker, workerSize) {
  var canUseWorker = !!(
    global.Worker &&
    global.Blob &&
    global.Promise &&
    global.OffscreenCanvas &&
    global.OffscreenCanvasRenderingContext2D &&
    global.HTMLCanvasElement &&
    global.HTMLCanvasElement.prototype.transferControlToOffscreen &&
    global.URL &&
    global.URL.createObjectURL);

  var canUsePaths = typeof Path2D === 'function' && typeof DOMMatrix === 'function';
  var canDrawBitmap = (function () {
    // this mostly supports ssr
    if (!global.OffscreenCanvas) {
      return false;
    }

    try {
      var canvas = new OffscreenCanvas(1, 1);
      var ctx = canvas.getContext('2d');
      ctx.fillRect(0, 0, 1, 1);
      var bitmap = canvas.transferToImageBitmap();
      ctx.createPattern(bitmap, 'no-repeat');
    } catch (e) {
      return false;
    }

    return true;
  })();

  function noop() {}

  // create a promise if it exists, otherwise, just
  // call the function directly
  function promise(func) {
    var ModulePromise = module.exports.Promise;
    var Prom = ModulePromise !== void 0 ? ModulePromise : global.Promise;

    if (typeof Prom === 'function') {
      return new Prom(func);
    }

    func(noop, noop);

    return null;
  }

  var bitmapMapper = (function (skipTransform, map) {
    // see https://github.com/catdad/canvas-confetti/issues/209
    // creating canvases is actually pretty expensive, so we should create a
    // 1:1 map for bitmap:canvas, so that we can animate the confetti in
    // a performant manner, but also not store them forever so that we don't
    // have a memory leak
    return {
      transform: function(bitmap) {
        if (skipTransform) {
          return bitmap;
        }

        if (map.has(bitmap)) {
          return map.get(bitmap);
        }

        var canvas = new OffscreenCanvas(bitmap.width, bitmap.height);
        var ctx = canvas.getContext('2d');
        ctx.drawImage(bitmap, 0, 0);

        map.set(bitmap, canvas);

        return canvas;
      },
      clear: function () {
        map.clear();
      }
    };
  })(canDrawBitmap, new Map());

  var raf = (function () {
    var TIME = Math.floor(1000 / 60);
    var frame, cancel;
    var frames = {};
    var lastFrameTime = 0;

    if (typeof requestAnimationFrame === 'function' && typeof cancelAnimationFrame === 'function') {
      frame = function (cb) {
        var id = Math.random();

        frames[id] = requestAnimationFrame(function onFrame(time) {
          if (lastFrameTime === time || lastFrameTime + TIME - 1 < time) {
            lastFrameTime = time;
            delete frames[id];

            cb();
          } else {
            frames[id] = requestAnimationFrame(onFrame);
          }
        });

        return id;
      };
      cancel = function (id) {
        if (frames[id]) {
          cancelAnimationFrame(frames[id]);
        }
      };
    } else {
      frame = function (cb) {
        return setTimeout(cb, TIME);
      };
      cancel = function (timer) {
        return clearTimeout(timer);
      };
    }

    return { frame: frame, cancel: cancel };
  }());

  var getWorker = (function () {
    var worker;
    var prom;
    var resolves = {};

    function decorate(worker) {
      function execute(options, callback) {
        worker.postMessage({ options: options || {}, callback: callback });
      }
      worker.init = function initWorker(canvas) {
        var offscreen = canvas.transferControlToOffscreen();
        worker.postMessage({ canvas: offscreen }, [offscreen]);
      };

      worker.fire = function fireWorker(options, size, done) {
        if (prom) {
          execute(options, null);
          return prom;
        }

        var id = Math.random().toString(36).slice(2);

        prom = promise(function (resolve) {
          function workerDone(msg) {
            if (msg.data.callback !== id) {
              return;
            }

            delete resolves[id];
            worker.removeEventListener('message', workerDone);

            prom = null;

            bitmapMapper.clear();

            done();
            resolve();
          }

          worker.addEventListener('message', workerDone);
          execute(options, id);

          resolves[id] = workerDone.bind(null, { data: { callback: id }});
        });

        return prom;
      };

      worker.reset = function resetWorker() {
        worker.postMessage({ reset: true });

        for (var id in resolves) {
          resolves[id]();
          delete resolves[id];
        }
      };
    }

    return function () {
      if (worker) {
        return worker;
      }

      if (!isWorker && canUseWorker) {
        var code = [
          'var CONFETTI, SIZE = {}, module = {};',
          '(' + main.toString() + ')(this, module, true, SIZE);',
          'onmessage = function(msg) {',
          '  if (msg.data.options) {',
          '    CONFETTI(msg.data.options).then(function () {',
          '      if (msg.data.callback) {',
          '        postMessage({ callback: msg.data.callback });',
          '      }',
          '    });',
          '  } else if (msg.data.reset) {',
          '    CONFETTI && CONFETTI.reset();',
          '  } else if (msg.data.resize) {',
          '    SIZE.width = msg.data.resize.width;',
          '    SIZE.height = msg.data.resize.height;',
          '  } else if (msg.data.canvas) {',
          '    SIZE.width = msg.data.canvas.width;',
          '    SIZE.height = msg.data.canvas.height;',
          '    CONFETTI = module.exports.create(msg.data.canvas);',
          '  }',
          '}',
        ].join('\n');
        try {
          worker = new Worker(URL.createObjectURL(new Blob([code])));
        } catch (e) {
          // eslint-disable-next-line no-console
          typeof console !== 'undefined' && typeof console.warn === 'function' ? console.warn('🎊 Could not load worker', e) : null;

          return null;
        }

        decorate(worker);
      }

      return worker;
    };
  })();

  var defaults = {
    particleCount: 50,
    angle: 90,
    spread: 45,
    startVelocity: 45,
    decay: 0.9,
    gravity: 1,
    drift: 0,
    ticks: 200,
    x: 0.5,
    y: 0.5,
    shapes: ['square', 'circle'],
    zIndex: 100,
    colors: [
      '#26ccff',
      '#a25afd',
      '#ff5e7e',
      '#88ff5a',
      '#fcff42',
      '#ffa62d',
      '#ff36ff'
    ],
    // probably should be true, but back-compat
    disableForReducedMotion: false,
    scalar: 1
  };

  function convert(val, transform) {
    return transform ? transform(val) : val;
  }

  function isOk(val) {
    return !(val === null || val === undefined);
  }

  function prop(options, name, transform) {
    return convert(
      options && isOk(options[name]) ? options[name] : defaults[name],
      transform
    );
  }

  function onlyPositiveInt(number){
    return number < 0 ? 0 : Math.floor(number);
  }

  function randomInt(min, max) {
    // [min, max)
    return Math.floor(Math.random() * (max - min)) + min;
  }

  function toDecimal(str) {
    return parseInt(str, 16);
  }

  function colorsToRgb(colors) {
    return colors.map(hexToRgb);
  }

  function hexToRgb(str) {
    var val = String(str).replace(/[^0-9a-f]/gi, '');

    if (val.length < 6) {
        val = val[0]+val[0]+val[1]+val[1]+val[2]+val[2];
    }

    return {
      r: toDecimal(val.substring(0,2)),
      g: toDecimal(val.substring(2,4)),
      b: toDecimal(val.substring(4,6))
    };
  }

  function getOrigin(options) {
    var origin = prop(options, 'origin', Object);
    origin.x = prop(origin, 'x', Number);
    origin.y = prop(origin, 'y', Number);

    return origin;
  }

  function setCanvasWindowSize(canvas) {
    canvas.width = document.documentElement.clientWidth;
    canvas.height = document.documentElement.clientHeight;
  }

  function setCanvasRectSize(canvas) {
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;
  }

  function getCanvas(zIndex) {
    var canvas = document.createElement('canvas');

    canvas.style.position = 'fixed';
    canvas.style.top = '0px';
    canvas.style.left = '0px';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = zIndex;

    return canvas;
  }

  function ellipse(context, x, y, radiusX, radiusY, rotation, startAngle, endAngle, antiClockwise) {
    context.save();
    context.translate(x, y);
    context.rotate(rotation);
    context.scale(radiusX, radiusY);
    context.arc(0, 0, 1, startAngle, endAngle, antiClockwise);
    context.restore();
  }

  function randomPhysics(opts) {
    var radAngle = opts.angle * (Math.PI / 180);
    var radSpread = opts.spread * (Math.PI / 180);

    return {
      x: opts.x,
      y: opts.y,
      wobble: Math.random() * 10,
      wobbleSpeed: Math.min(0.11, Math.random() * 0.1 + 0.05),
      velocity: (opts.startVelocity * 0.5) + (Math.random() * opts.startVelocity),
      angle2D: -radAngle + ((0.5 * radSpread) - (Math.random() * radSpread)),
      tiltAngle: (Math.random() * (0.75 - 0.25) + 0.25) * Math.PI,
      color: opts.color,
      shape: opts.shape,
      tick: 0,
      totalTicks: opts.ticks,
      decay: opts.decay,
      drift: opts.drift,
      random: Math.random() + 2,
      tiltSin: 0,
      tiltCos: 0,
      wobbleX: 0,
      wobbleY: 0,
      gravity: opts.gravity * 3,
      ovalScalar: 0.6,
      scalar: opts.scalar,
      flat: opts.flat
    };
  }

  function updateFetti(context, fetti) {
    fetti.x += Math.cos(fetti.angle2D) * fetti.velocity + fetti.drift;
    fetti.y += Math.sin(fetti.angle2D) * fetti.velocity + fetti.gravity;
    fetti.velocity *= fetti.decay;

    if (fetti.flat) {
      fetti.wobble = 0;
      fetti.wobbleX = fetti.x + (10 * fetti.scalar);
      fetti.wobbleY = fetti.y + (10 * fetti.scalar);

      fetti.tiltSin = 0;
      fetti.tiltCos = 0;
      fetti.random = 1;
    } else {
      fetti.wobble += fetti.wobbleSpeed;
      fetti.wobbleX = fetti.x + ((10 * fetti.scalar) * Math.cos(fetti.wobble));
      fetti.wobbleY = fetti.y + ((10 * fetti.scalar) * Math.sin(fetti.wobble));

      fetti.tiltAngle += 0.1;
      fetti.tiltSin = Math.sin(fetti.tiltAngle);
      fetti.tiltCos = Math.cos(fetti.tiltAngle);
      fetti.random = Math.random() + 2;
    }

    var progress = (fetti.tick++) / fetti.totalTicks;

    var x1 = fetti.x + (fetti.random * fetti.tiltCos);
    var y1 = fetti.y + (fetti.random * fetti.tiltSin);
    var x2 = fetti.wobbleX + (fetti.random * fetti.tiltCos);
    var y2 = fetti.wobbleY + (fetti.random * fetti.tiltSin);

    context.fillStyle = 'rgba(' + fetti.color.r + ', ' + fetti.color.g + ', ' + fetti.color.b + ', ' + (1 - progress) + ')';

    context.beginPath();

    if (canUsePaths && fetti.shape.type === 'path' && typeof fetti.shape.path === 'string' && Array.isArray(fetti.shape.matrix)) {
      context.fill(transformPath2D(
        fetti.shape.path,
        fetti.shape.matrix,
        fetti.x,
        fetti.y,
        Math.abs(x2 - x1) * 0.1,
        Math.abs(y2 - y1) * 0.1,
        Math.PI / 10 * fetti.wobble
      ));
    } else if (fetti.shape.type === 'bitmap') {
      var rotation = Math.PI / 10 * fetti.wobble;
      var scaleX = Math.abs(x2 - x1) * 0.1;
      var scaleY = Math.abs(y2 - y1) * 0.1;
      var width = fetti.shape.bitmap.width * fetti.scalar;
      var height = fetti.shape.bitmap.height * fetti.scalar;

      var matrix = new DOMMatrix([
        Math.cos(rotation) * scaleX,
        Math.sin(rotation) * scaleX,
        -Math.sin(rotation) * scaleY,
        Math.cos(rotation) * scaleY,
        fetti.x,
        fetti.y
      ]);

      // apply the transform matrix from the confetti shape
      matrix.multiplySelf(new DOMMatrix(fetti.shape.matrix));

      var pattern = context.createPattern(bitmapMapper.transform(fetti.shape.bitmap), 'no-repeat');
      pattern.setTransform(matrix);

      context.globalAlpha = (1 - progress);
      context.fillStyle = pattern;
      context.fillRect(
        fetti.x - (width / 2),
        fetti.y - (height / 2),
        width,
        height
      );
      context.globalAlpha = 1;
    } else if (fetti.shape === 'circle') {
      context.ellipse ?
        context.ellipse(fetti.x, fetti.y, Math.abs(x2 - x1) * fetti.ovalScalar, Math.abs(y2 - y1) * fetti.ovalScalar, Math.PI / 10 * fetti.wobble, 0, 2 * Math.PI) :
        ellipse(context, fetti.x, fetti.y, Math.abs(x2 - x1) * fetti.ovalScalar, Math.abs(y2 - y1) * fetti.ovalScalar, Math.PI / 10 * fetti.wobble, 0, 2 * Math.PI);
    } else if (fetti.shape === 'star') {
      var rot = Math.PI / 2 * 3;
      var innerRadius = 4 * fetti.scalar;
      var outerRadius = 8 * fetti.scalar;
      var x = fetti.x;
      var y = fetti.y;
      var spikes = 5;
      var step = Math.PI / spikes;

      while (spikes--) {
        x = fetti.x + Math.cos(rot) * outerRadius;
        y = fetti.y + Math.sin(rot) * outerRadius;
        context.lineTo(x, y);
        rot += step;

        x = fetti.x + Math.cos(rot) * innerRadius;
        y = fetti.y + Math.sin(rot) * innerRadius;
        context.lineTo(x, y);
        rot += step;
      }
    } else {
      context.moveTo(Math.floor(fetti.x), Math.floor(fetti.y));
      context.lineTo(Math.floor(fetti.wobbleX), Math.floor(y1));
      context.lineTo(Math.floor(x2), Math.floor(y2));
      context.lineTo(Math.floor(x1), Math.floor(fetti.wobbleY));
    }

    context.closePath();
    context.fill();

    return fetti.tick < fetti.totalTicks;
  }

  function animate(canvas, fettis, resizer, size, done) {
    var animatingFettis = fettis.slice();
    var context = canvas.getContext('2d');
    var animationFrame;
    var destroy;

    var prom = promise(function (resolve) {
      function onDone() {
        animationFrame = destroy = null;

        context.clearRect(0, 0, size.width, size.height);
        bitmapMapper.clear();

        done();
        resolve();
      }

      function update() {
        if (isWorker && !(size.width === workerSize.width && size.height === workerSize.height)) {
          size.width = canvas.width = workerSize.width;
          size.height = canvas.height = workerSize.height;
        }

        if (!size.width && !size.height) {
          resizer(canvas);
          size.width = canvas.width;
          size.height = canvas.height;
        }

        context.clearRect(0, 0, size.width, size.height);

        animatingFettis = animatingFettis.filter(function (fetti) {
          return updateFetti(context, fetti);
        });

        if (animatingFettis.length) {
          animationFrame = raf.frame(update);
        } else {
          onDone();
        }
      }

      animationFrame = raf.frame(update);
      destroy = onDone;
    });

    return {
      addFettis: function (fettis) {
        animatingFettis = animatingFettis.concat(fettis);

        return prom;
      },
      canvas: canvas,
      promise: prom,
      reset: function () {
        if (animationFrame) {
          raf.cancel(animationFrame);
        }

        if (destroy) {
          destroy();
        }
      }
    };
  }

  function confettiCannon(canvas, globalOpts) {
    var isLibCanvas = !canvas;
    var allowResize = !!prop(globalOpts || {}, 'resize');
    var hasResizeEventRegistered = false;
    var globalDisableForReducedMotion = prop(globalOpts, 'disableForReducedMotion', Boolean);
    var shouldUseWorker = canUseWorker && !!prop(globalOpts || {}, 'useWorker');
    var worker = shouldUseWorker ? getWorker() : null;
    var resizer = isLibCanvas ? setCanvasWindowSize : setCanvasRectSize;
    var initialized = (canvas && worker) ? !!canvas.__confetti_initialized : false;
    var preferLessMotion = typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion)').matches;
    var animationObj;

    function fireLocal(options, size, done) {
      var particleCount = prop(options, 'particleCount', onlyPositiveInt);
      var angle = prop(options, 'angle', Number);
      var spread = prop(options, 'spread', Number);
      var startVelocity = prop(options, 'startVelocity', Number);
      var decay = prop(options, 'decay', Number);
      var gravity = prop(options, 'gravity', Number);
      var drift = prop(options, 'drift', Number);
      var colors = prop(options, 'colors', colorsToRgb);
      var ticks = prop(options, 'ticks', Number);
      var shapes = prop(options, 'shapes');
      var scalar = prop(options, 'scalar');
      var flat = !!prop(options, 'flat');
      var origin = getOrigin(options);

      var temp = particleCount;
      var fettis = [];

      var startX = canvas.width * origin.x;
      var startY = canvas.height * origin.y;

      while (temp--) {
        fettis.push(
          randomPhysics({
            x: startX,
            y: startY,
            angle: angle,
            spread: spread,
            startVelocity: startVelocity,
            color: colors[temp % colors.length],
            shape: shapes[randomInt(0, shapes.length)],
            ticks: ticks,
            decay: decay,
            gravity: gravity,
            drift: drift,
            scalar: scalar,
            flat: flat
          })
        );
      }

      // if we have a previous canvas already animating,
      // add to it
      if (animationObj) {
        return animationObj.addFettis(fettis);
      }

      animationObj = animate(canvas, fettis, resizer, size , done);

      return animationObj.promise;
    }

    function fire(options) {
      var disableForReducedMotion = globalDisableForReducedMotion || prop(options, 'disableForReducedMotion', Boolean);
      var zIndex = prop(options, 'zIndex', Number);

      if (disableForReducedMotion && preferLessMotion) {
        return promise(function (resolve) {
          resolve();
        });
      }

      if (isLibCanvas && animationObj) {
        // use existing canvas from in-progress animation
        canvas = animationObj.canvas;
      } else if (isLibCanvas && !canvas) {
        // create and initialize a new canvas
        canvas = getCanvas(zIndex);
        document.body.appendChild(canvas);
      }

      if (allowResize && !initialized) {
        // initialize the size of a user-supplied canvas
        resizer(canvas);
      }

      var size = {
        width: canvas.width,
        height: canvas.height
      };

      if (worker && !initialized) {
        worker.init(canvas);
      }

      initialized = true;

      if (worker) {
        canvas.__confetti_initialized = true;
      }

      function onResize() {
        if (worker) {
          // TODO this really shouldn't be immediate, because it is expensive
          var obj = {
            getBoundingClientRect: function () {
              if (!isLibCanvas) {
                return canvas.getBoundingClientRect();
              }
            }
          };

          resizer(obj);

          worker.postMessage({
            resize: {
              width: obj.width,
              height: obj.height
            }
          });
          return;
        }

        // don't actually query the size here, since this
        // can execute frequently and rapidly
        size.width = size.height = null;
      }

      function done() {
        animationObj = null;

        if (allowResize) {
          hasResizeEventRegistered = false;
          global.removeEventListener('resize', onResize);
        }

        if (isLibCanvas && canvas) {
          if (document.body.contains(canvas)) {
            document.body.removeChild(canvas);
          }
          canvas = null;
          initialized = false;
        }
      }

      if (allowResize && !hasResizeEventRegistered) {
        hasResizeEventRegistered = true;
        global.addEventListener('resize', onResize, false);
      }

      if (worker) {
        return worker.fire(options, size, done);
      }

      return fireLocal(options, size, done);
    }

    fire.reset = function () {
      if (worker) {
        worker.reset();
      }

      if (animationObj) {
        animationObj.reset();
      }
    };

    return fire;
  }

  // Make default export lazy to defer worker creation until called.
  var defaultFire;
  function getDefaultFire() {
    if (!defaultFire) {
      defaultFire = confettiCannon(null, { useWorker: true, resize: true });
    }
    return defaultFire;
  }

  function transformPath2D(pathString, pathMatrix, x, y, scaleX, scaleY, rotation) {
    var path2d = new Path2D(pathString);

    var t1 = new Path2D();
    t1.addPath(path2d, new DOMMatrix(pathMatrix));

    var t2 = new Path2D();
    // see https://developer.mozilla.org/en-US/docs/Web/API/DOMMatrix/DOMMatrix
    t2.addPath(t1, new DOMMatrix([
      Math.cos(rotation) * scaleX,
      Math.sin(rotation) * scaleX,
      -Math.sin(rotation) * scaleY,
      Math.cos(rotation) * scaleY,
      x,
      y
    ]));

    return t2;
  }

  function shapeFromPath(pathData) {
    if (!canUsePaths) {
      throw new Error('path confetti are not supported in this browser');
    }

    var path, matrix;

    if (typeof pathData === 'string') {
      path = pathData;
    } else {
      path = pathData.path;
      matrix = pathData.matrix;
    }

    var path2d = new Path2D(path);
    var tempCanvas = document.createElement('canvas');
    var tempCtx = tempCanvas.getContext('2d');

    if (!matrix) {
      // attempt to figure out the width of the path, up to 1000x1000
      var maxSize = 1000;
      var minX = maxSize;
      var minY = maxSize;
      var maxX = 0;
      var maxY = 0;
      var width, height;

      // do some line skipping... this is faster than checking
      // every pixel and will be mostly still correct
      for (var x = 0; x < maxSize; x += 2) {
        for (var y = 0; y < maxSize; y += 2) {
          if (tempCtx.isPointInPath(path2d, x, y, 'nonzero')) {
            minX = Math.min(minX, x);
            minY = Math.min(minY, y);
            maxX = Math.max(maxX, x);
            maxY = Math.max(maxY, y);
          }
        }
      }

      width = maxX - minX;
      height = maxY - minY;

      var maxDesiredSize = 10;
      var scale = Math.min(maxDesiredSize/width, maxDesiredSize/height);

      matrix = [
        scale, 0, 0, scale,
        -Math.round((width/2) + minX) * scale,
        -Math.round((height/2) + minY) * scale
      ];
    }

    return {
      type: 'path',
      path: path,
      matrix: matrix
    };
  }

  function shapeFromText(textData) {
    var text,
        scalar = 1,
        color = '#000000',
        // see https://nolanlawson.com/2022/04/08/the-struggle-of-using-native-emoji-on-the-web/
        fontFamily = '"Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji", "EmojiOne Color", "Android Emoji", "Twemoji Mozilla", "system emoji", sans-serif';

    if (typeof textData === 'string') {
      text = textData;
    } else {
      text = textData.text;
      scalar = 'scalar' in textData ? textData.scalar : scalar;
      fontFamily = 'fontFamily' in textData ? textData.fontFamily : fontFamily;
      color = 'color' in textData ? textData.color : color;
    }

    // all other confetti are 10 pixels,
    // so this pixel size is the de-facto 100% scale confetti
    var fontSize = 10 * scalar;
    var font = '' + fontSize + 'px ' + fontFamily;

    var canvas = new OffscreenCanvas(fontSize, fontSize);
    var ctx = canvas.getContext('2d');

    ctx.font = font;
    var size = ctx.measureText(text);
    var width = Math.ceil(size.actualBoundingBoxRight + size.actualBoundingBoxLeft);
    var height = Math.ceil(size.actualBoundingBoxAscent + size.actualBoundingBoxDescent);

    var padding = 2;
    var x = size.actualBoundingBoxLeft + padding;
    var y = size.actualBoundingBoxAscent + padding;
    width += padding + padding;
    height += padding + padding;

    canvas = new OffscreenCanvas(width, height);
    ctx = canvas.getContext('2d');
    ctx.font = font;
    ctx.fillStyle = color;

    ctx.fillText(text, x, y);

    var scale = 1 / scalar;

    return {
      type: 'bitmap',
      // TODO these probably need to be transfered for workers
      bitmap: canvas.transferToImageBitmap(),
      matrix: [scale, 0, 0, scale, -width * scale / 2, -height * scale / 2]
    };
  }

  module.exports = function() {
    return getDefaultFire().apply(this, arguments);
  };
  module.exports.reset = function() {
    getDefaultFire().reset();
  };
  module.exports.create = confettiCannon;
  module.exports.shapeFromPath = shapeFromPath;
  module.exports.shapeFromText = shapeFromText;
}((function () {
  if (typeof window !== 'undefined') {
    return window;
  }

  if (typeof self !== 'undefined') {
    return self;
  }

  return this || {};
})(), module, false));

// end source content

  window.confetti = module.exports;
}(window, {}));

  </script>
  <script>
/* =================== 1. ICONS =================== */
// Crisp vector Lucide-style SVG icon registry
const LucideIcons = {
  dices: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <rect width="12" height="12" x="2" y="10" rx="2" ry="2"/>
    <path d="m17.92 14 3.5-3.5a2.24 2.24 0 0 0 0-3.16l-4.76-4.76a2.24 2.24 0 0 0-3.16 0L10 6.08"/>
    <path d="M6 14h.01"/><path d="M10 18h.01"/>
    <path d="M14 6h.01"/><path d="M18 10h.01"/>
  </svg>`,
  
  star: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="currentColor" stroke-linecap="round" stroke-linejoin="round">
    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
  </svg>`,

  coins: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="8" cy="8" r="6"/>
    <path d="M18.09 10.37A6 6 0 1 1 10.34 18"/>
    <path d="M7 6h1v4H7"/>
    <path d="m16.71 13.88.7.71-2.82 2.82"/>
  </svg>`,

  trophy: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
    <path d="M4 22h16"/>
    <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
    <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
    <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
  </svg>`,

  rocket: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/>
    <path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>
    <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/>
    <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
  </svg>`,

  shield: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
  </svg>`,

  magnet: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="m6 15-4-4 6.75-6.77a7.78 7.78 0 0 1 11 11L13 22l-4-4 6.39-6.36a2.14 2.14 0 0 0-3-3.04L6 15z"/>
    <path d="m5 8 4 4"/>
    <path d="m12 15 4 4"/>
  </svg>`,

  banana: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 13c3.5-2 8-2 10 2 1.5 3 2.5 5 6 6-1.5-6-4.5-12-10-15-3-1.6-6-1-8 2-.6.9-1 2.3-1 4 0 1.2.5 2.1 3 1"/>
  </svg>`,

  "refresh-cw": `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
    <path d="M21 3v5h-5"/>
    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
    <path d="M8 16H3v5"/>
  </svg>`,

  sparkles: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
    <path d="M5 3v4"/>
    <path d="M19 17v4"/>
    <path d="M3 5h4"/>
    <path d="M17 19h4"/>
  </svg>`,

  flag: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
    <line x1="4" x2="4" y1="22" y2="15"/>
  </svg>`,

  flame: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
  </svg>`,

  "shopping-bag": `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>
    <path d="M3 6h18"/>
    <path d="M16 10a4 4 0 0 1-8 0"/>
  </svg>`,

  swords: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <polyline points="14.5 17.5 3 6 3 3 6 3 17.5 14.5"/>
    <line x1="13" x2="19" y1="19" y2="13"/>
    <line x1="16" x2="20" y1="16" y2="20"/>
    <line x1="19" x2="21" y1="21" y2="19"/>
    <polyline points="14.5 6.5 18 3 21 3 21 6 17.5 9.5"/>
    <line x1="5" x2="11" y1="14" y2="20"/>
  </svg>`,

  zap: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
  </svg>`,

  "undo-2": `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M9 14 4 9l5-5"/>
    <path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/>
  </svg>`,

  users: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
    <circle cx="9" cy="7" r="4"/>
    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
  </svg>`,

  bot: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <rect width="18" height="12" x="3" y="6" rx="2"/>
    <path d="M9 13v.01"/><path d="M15 13v.01"/>
    <path d="M12 6V2"/><circle cx="12" cy="2" r="1"/>
    <path d="M2 12h1"/><path d="M21 12h1"/>
  </svg>`,

  qr: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <rect width="5" height="5" x="3" y="3" rx="1"/>
    <rect width="5" height="5" x="16" y="3" rx="1"/>
    <rect width="5" height="5" x="3" y="16" rx="1"/>
    <path d="M21 16h-3a2 2 0 0 0-2 2v3"/>
    <path d="M21 21v.01"/>
    <path d="M12 7v3a2 2 0 0 1-2 2H7"/>
    <path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/>
  </svg>`,

  volume2: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
    <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
  </svg>`,

  volumeX: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
    <line x1="22" x2="16" y1="9" y2="15"/>
    <line x1="16" x2="22" y1="9" y2="15"/>
  </svg>`,

  check: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <polyline points="20 6 9 17 4 12"/>
  </svg>`,

  copy: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
  </svg>`,

  award: `<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="8" r="6"/>
    <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
  </svg>`
};

function getIcon(name, fallback = '') {
  return LucideIcons[name] || fallback;
}


/* =================== 1.5. I18N (MULTI-LANGUAGE) =================== */
// ============================================================================
// PartyCraft: Wonder Board - Internationalization (i18n) Engine
// Supports: Turkish (tr), English (en), German (de), Spanish (es), French (fr)
// Dynamic live switching without page reload & automatic localStorage persistence
// ============================================================================

const I18N_DATA = {
  tr: {
    hero_badge: '🎲 Çok Oyunculu Dünya Turu Masa Oyunu',
    hero_title: 'Parti <span>Dünyası</span>',
    hero_subtitle: 'İstanbul, Tokyo, Paris ve New York sokaklarında zar at, yarış ve eğlen!',
    studio_badge: 'Karakterin',
    studio_name_label: 'Karakter Adın:',
    studio_name_placeholder: 'Adını yaz...',
    studio_model_label: 'Karakter Modeli:',
    studio_hat_label: 'Şapka & Başlık:',
    studio_color_label: 'Gövde & Çerçeve Rengi:',
    btn_quick_play: 'Hızlı Oyna',
    btn_quick_play_desc: 'Beklemeden hemen 3 akıllı bot ile İstanbul haritasında partiye başla!',
    btn_play_friends: 'Arkadaşlarınla Oyna',
    btn_play_friends_desc: 'Tek tıkla oda kur, davet bağlantısı veya QR kod ile arkadaşlarını çağır!',
    btn_play_bots: 'Botlarla Oyna',
    btn_play_bots_desc: '1 ila 3 bot seç ve antrenman yap!',
    btn_how_to_play: 'Nasıl Oynanır',
    btn_how_to_play_desc: 'Oyun kuralları, karolar ve taktikler',
    btn_settings: 'Ayarlar',
    btn_settings_desc: 'Ses, müzik, dil ve grafik ayarları',
    room_code_placeholder: 'ODA KODU (ÖRN: ABCD)',
    btn_join_room: 'Odaya Katıl',
    server_status_checking: 'Sunucu Durumu: Bağlantı kontrol ediliyor...',
    server_status_php: 'Sunucu Durumu: PHP & Çok Oyunculu Çevrimiçi Hazır',
    server_status_node: 'Sunucu Durumu: Node.js WebSocket Çevrimiçi Hazır',
    server_status_offline: 'Sunucu Durumu: Çevrimdışı Mod (Botlar & Yerel Oyun Hazır)',

    // Lobby
    lobby_room_title: 'Oda Kodu:',
    lobby_players_title: 'Oyuncular',
    lobby_share_title: 'Arkadaşını Çağır (Davet Bağlantısı):',
    btn_copy_link: '📋 Kopyala',
    btn_copied: '✅ Kopyalandı!',
    btn_show_qr: '📱 QR Kodu Göster',
    btn_add_bot: '🤖 Bot Ekle',
    btn_leave_room: '🚪 Odadan Ayrıl',
    lobby_city_title: 'Oyun Şehri / Haritası:',
    lobby_rounds_title: 'Tur Sayısı:',
    btn_start_game: '🎉 Partiyi Başlat!',
    lobby_waiting_host: 'Oda sahibinin oyunu başlatması bekleniyor...',

    // HUD & In-game
    hud_your_turn: 'Sıra Sende! Zarı at ve ilerle',
    hud_turn_prefix: 'Sıra: ',
    hud_turn_waiting: 'Oyuncu Sırası',
    hud_round_prefix: 'Tur: ',
    btn_roll_dice: '🎲 ZAR AT',
    btn_bag: '🎒 Çantam',
    btn_trade: '🤝 Takas',
    btn_chat: '💬 Sohbet',
    btn_hud_leave: 'Odadan Ayrıl',

    // Modals
    roulette_title: '🎡 Şans Çarkı!',
    roulette_spinning: 'Çark dönüyor...',
    roulette_player_at: 'Şans Karosunda!',
    roulette_won: 'Tebrikler, Büyük Ödül Kazandın!',

    leave_modal_title: 'Oyundan Ayrıl',
    leave_modal_desc: 'Mevcut oyundan ayrılmak ve ana menüye dönmek istediğinize emin misiniz? Çok oyunculu odada yerinizi bir bot devralacaktır.',
    btn_leave_confirm: '🚪 Evet, Odadan Ayrıl',
    btn_leave_cancel: 'İptal / Oyuna Dön',

    settings_title: '⚙️ Oyun Ayarları',
    settings_lang_title: '🌐 Dil / Language',
    settings_lang_sub: 'Arayüz ve oyun metinleri dili',
    settings_sfx_title: '🔊 Ses Efektleri',
    settings_sfx_sub: 'Zar, adım, altın ve zafer sesleri',
    settings_music_title: '🎵 Arka Plan Müziği',
    settings_music_sub: 'Web Audio sentezleyici parti müziği',
    settings_dice_title: '🎲 3D Zar Animasyonları',
    settings_dice_sub: 'Kapatılırsa zar sonucu beklemeden gelir',
    settings_lowpower_title: '⚡ Düşük Güç / Pil Tasarrufu',
    settings_lowpower_sub: 'Eski cihazlar için hafif grafik modu',
    btn_reset_profile: '🔄 Oyuncu Profilini & Verilerini Sıfırla',
    btn_save_settings: 'Ayarları Kaydet',

    rules_title: '📖 Parti Dünyası — Nasıl Oynanır?',
    rules_goal_title: '🎯 Oyunun Amacı',
    rules_goal_body: 'Belirlenen tur sayısı bitmeden önce en çok <strong>Yıldız (⭐)</strong> ve <strong>Altın (🪙)</strong> toplayan oyuncu partinin şampiyonu olur! 1 Yıldız, 20 altın değerindedir ve liderlikte en büyük önceliğe sahiptir.',
    rules_turn_title: '🎲 Sıra ve Hareket',
    rules_turn_body: 'Sıra sana geldiğinde <strong>Zar At</strong> butonuna bas veya klavyeden <strong>SPACE</strong> tuşunu kullan. Zardaki sayı kadar saat yönünde ilerlersin. Başlangıç karosundan her geçişte <strong>+10 Altın</strong> ödülü kazanırsın!',
    rules_tiles_title: '🗺️ Özel Karolar ve Etkileri',
    rules_items_title: '🎒 Taktik Eşyaları',
    rules_close_btn: 'Harika, Oyuna Dön!',

    // Tiles
    tile_start: 'Başlangıç (+10 🪙)',
    tile_coin: 'Altın Karosu',
    tile_star: 'Yıldız Kaidesi',
    tile_mystery: 'Şans Çarkı',
    tile_trap: 'Tuzak Karosu',
    tile_shop: 'Eşya Dükkanı',
    tile_bank: 'Merkez Bankası',
    tile_jail: 'Karakol / Kodes',
    tile_duel: 'Düello Alanı',

    // Titles
    title_novice: 'Acemi Gezgin',
    title_dice: 'Zar Cambazı',
    title_star: 'Yıldız Avcısı',
    title_board: 'Karelerin Efendisi',
    title_party: 'Parti Efsanesi'
  },

  en: {
    hero_badge: '🎲 Multiplayer World Tour Board Game',
    hero_title: 'Party<span>Craft</span>',
    hero_subtitle: 'Roll dice, race, and celebrate across the iconic streets of Istanbul, Tokyo, Paris & New York!',
    studio_badge: 'Your character',
    studio_name_label: 'Character Name:',
    studio_name_placeholder: 'Enter your name...',
    studio_model_label: 'Character Model:',
    studio_hat_label: 'Hat & Headwear:',
    studio_color_label: 'Body & Ring Color:',
    btn_quick_play: 'Quick Play',
    btn_quick_play_desc: 'Instantly jump into Istanbul with 3 smart AI bots, zero waiting!',
    btn_play_friends: 'Play with Friends',
    btn_play_friends_desc: 'Host a room in 1 click, invite friends via direct link or QR code!',
    btn_play_bots: 'Play with Bots',
    btn_play_bots_desc: 'Choose 1 to 3 bots and practice offline!',
    btn_how_to_play: 'How to Play',
    btn_how_to_play_desc: 'Board rules, special tiles and pro tips',
    btn_settings: 'Settings',
    btn_settings_desc: 'Audio, music, language and graphics',
    room_code_placeholder: 'ROOM CODE (E.G. ABCD)',
    btn_join_room: 'Join Room',
    server_status_checking: 'Server Status: Checking connection...',
    server_status_php: 'Server Status: PHP Multiplayer Online Ready',
    server_status_node: 'Server Status: Node.js WebSocket Online Ready',
    server_status_offline: 'Server Status: Offline Mode (Local & Bots Ready)',

    // Lobby
    lobby_room_title: 'Room Code:',
    lobby_players_title: 'Players',
    lobby_share_title: 'Invite Friends (Direct Link):',
    btn_copy_link: '📋 Copy Link',
    btn_copied: '✅ Copied!',
    btn_show_qr: '📱 Show QR Code',
    btn_add_bot: '🤖 Add Bot',
    btn_leave_room: '🚪 Leave Room',
    lobby_city_title: 'City / Map Theme:',
    lobby_rounds_title: 'Rounds Count:',
    btn_start_game: '🎉 Start Party!',
    lobby_waiting_host: 'Waiting for room host to start...',

    // HUD & In-game
    hud_your_turn: 'Your Turn! Roll the dice & advance',
    hud_turn_prefix: 'Turn: ',
    hud_turn_waiting: 'Player Turn',
    hud_round_prefix: 'Round: ',
    btn_roll_dice: '🎲 ROLL DICE',
    btn_bag: '🎒 Inventory',
    btn_trade: '🤝 Trade',
    btn_chat: '💬 Chat',
    btn_hud_leave: 'Leave Match',

    // Modals
    roulette_title: '🎡 Fortune Wheel!',
    roulette_spinning: 'Spinning the wheel...',
    roulette_player_at: 'is on Fortune Tile!',
    roulette_won: 'Congratulations, Big Prize Won!',

    leave_modal_title: 'Leave Match',
    leave_modal_desc: 'Are you sure you want to exit the current match and return to the main menu? In multiplayer games, an AI bot will take over your spot.',
    btn_leave_confirm: '🚪 Yes, Leave Room',
    btn_leave_cancel: 'Cancel / Keep Playing',

    settings_title: '⚙️ Game Settings',
    settings_lang_title: '🌐 Language / Dil',
    settings_lang_sub: 'Interface and gameplay language',
    settings_sfx_title: '🔊 Sound Effects',
    settings_sfx_sub: 'Dice, footstep, coin and fanfare sounds',
    settings_music_title: '🎵 Background Music',
    settings_music_sub: 'Synthesized Web Audio party soundtrack',
    settings_dice_title: '🎲 3D Dice Animations',
    settings_dice_sub: 'Turn off for instant dice results',
    settings_lowpower_title: '⚡ Power Saver / Lite Mode',
    settings_lowpower_sub: 'Lightweight graphics for older mobile devices',
    btn_reset_profile: '🔄 Reset Player Profile & Data',
    btn_save_settings: 'Save Settings',

    rules_title: '📖 PartyCraft — How to Play',
    rules_goal_title: '🎯 Objective',
    rules_goal_body: 'Collect the most <strong>Stars (⭐)</strong> and <strong>Coins (🪙)</strong> before all rounds finish! 1 Star equals 20 Coins and holds highest priority on the leaderboard.',
    rules_turn_title: '🎲 Movement & Turns',
    rules_turn_body: 'On your turn, press <strong>ROLL DICE</strong> or tap <strong>SPACE</strong>. Move clockwise along the scenic board circuit. Every time you pass Start, you earn <strong>+10 Coins</strong>!',
    rules_tiles_title: '🗺️ Special Tiles & Hazards',
    rules_items_title: '🎒 Tactical Items',
    rules_close_btn: 'Got it, Back to Game!',

    // Tiles
    tile_start: 'Start (+10 🪙)',
    tile_coin: 'Coin Tile',
    tile_star: 'Star Pedestal',
    tile_mystery: 'Fortune Wheel',
    tile_trap: 'Trap Tile',
    tile_shop: 'Item Shop',
    tile_bank: 'Central Bank',
    tile_jail: 'Police / Jail',
    tile_duel: 'Duel Arena',

    // Titles
    title_novice: 'Novice Explorer',
    title_dice: 'Dice Acrobat',
    title_star: 'Star Hunter',
    title_board: 'Master of Tiles',
    title_party: 'Party Legend'
  },

  de: {
    hero_badge: '🎲 Mehrspieler-Weltreise-Brettspiel',
    hero_title: 'Party<span>Craft</span>',
    hero_subtitle: 'Würfle, rase und feiere in den Straßen von Istanbul, Tokio, Paris und New York!',
    studio_badge: 'Deine Figur',
    studio_name_label: 'Name:',
    studio_name_placeholder: 'Name eingeben...',
    studio_model_label: 'Charaktermodell:',
    studio_hat_label: 'Hut & Kopfbedeckung:',
    studio_color_label: 'Körper- & Ringfarbe:',
    btn_quick_play: 'Schnellspiel',
    btn_quick_play_desc: 'Starte sofort mit 3 cleveren KI-Bots in Istanbul ohne Wartezeit!',
    btn_play_friends: 'Mit Freunden spielen',
    btn_play_friends_desc: 'Erstelle einen Raum mit 1 Klick, lade Freunde per Link oder QR-Code ein!',
    btn_play_bots: 'Gegen Bots spielen',
    btn_play_bots_desc: 'Wähle 1 bis 3 Bots und übe offline!',
    btn_how_to_play: 'Spielanleitung',
    btn_how_to_play_desc: 'Spielregeln, Felder und Taktiken',
    btn_settings: 'Einstellungen',
    btn_settings_desc: 'Audio, Musik, Sprache und Grafik',
    room_code_placeholder: 'RAUMCODE (Z.B. ABCD)',
    btn_join_room: 'Raum beitreten',
    server_status_checking: 'Serverstatus: Verbindung wird geprüft...',
    server_status_php: 'Serverstatus: PHP-Mehrspieler online bereit',
    server_status_node: 'Serverstatus: Node.js WebSocket online bereit',
    server_status_offline: 'Serverstatus: Offline-Modus (Bots & Lokales Spiel)',

    // Lobby
    lobby_room_title: 'Raumcode:',
    lobby_players_title: 'Spieler',
    lobby_share_title: 'Freunde einladen (Direktlink):',
    btn_copy_link: '📋 Link kopieren',
    btn_copied: '✅ Kopiert!',
    btn_show_qr: '📱 QR-Code anzeigen',
    btn_add_bot: '🤖 Bot hinzufügen',
    btn_leave_room: '🚪 Raum verlassen',
    lobby_city_title: 'Stadt / Kartenthema:',
    lobby_rounds_title: 'Rundenzahl:',
    btn_start_game: '🎉 Party starten!',
    lobby_waiting_host: 'Warten auf den Raumleiter...',

    // HUD & In-game
    hud_your_turn: 'Du bist dran! Würfle und ziehe vorwärts',
    hud_turn_prefix: 'Zug: ',
    hud_turn_waiting: 'Spielerzug',
    hud_round_prefix: 'Runde: ',
    btn_roll_dice: '🎲 WÜRFELN',
    btn_bag: '🎒 Inventar',
    btn_trade: '🤝 Handeln',
    btn_chat: '💬 Chat',
    btn_hud_leave: 'Spiel verlassen',

    // Modals
    roulette_title: '🎡 Glücksrad!',
    roulette_spinning: 'Das Rad dreht sich...',
    roulette_player_at: 'ist auf dem Glücksfeld!',
    roulette_won: 'Herzlichen Glückwunsch, Großer Preis gewonnen!',

    leave_modal_title: 'Spiel verlassen',
    leave_modal_desc: 'Möchtest du das Spiel wirklich verlassen und zum Hauptmenü zurückkehren? In Mehrspieler-Räumen übernimmt ein KI-Bot deinen Platz.',
    btn_leave_confirm: '🚪 Ja, Raum verlassen',
    btn_leave_cancel: 'Abbrechen / Weiterspielen',

    settings_title: '⚙️ Spieleinstellungen',
    settings_lang_title: '🌐 Sprache / Language',
    settings_lang_sub: 'Sprache für Menüs und Spieltexte',
    settings_sfx_title: '🔊 Soundeffekte',
    settings_sfx_sub: 'Würfel-, Schritt- und Münzgeräusche',
    settings_music_title: '🎵 Hintergrundmusik',
    settings_music_sub: 'Synthetisierter Web-Audio Partysoundtrack',
    settings_dice_title: '🎲 3D-Würfelanimationen',
    settings_dice_sub: 'Ausschalten für sofortige Würfelergebnisse',
    settings_lowpower_title: '⚡ Energiesparmodus',
    settings_lowpower_sub: 'Schonende Grafik für ältere Mobilgeräte',
    btn_reset_profile: '🔄 Profil & Fortschritt zurücksetzen',
    btn_save_settings: 'Einstellungen speichern',

    rules_title: '📖 PartyCraft — Spielanleitung',
    rules_goal_title: '🎯 Spielziel',
    rules_goal_body: 'Sammle vor Rundenende die meisten <strong>Sterne (⭐)</strong> und <strong>Münzen (🪙)</strong>! 1 Stern = 20 Münzen und hat oberste Priorität in der Rangliste.',
    rules_turn_title: '🎲 Zug & Bewegung',
    rules_turn_body: 'Wenn du an der Reihe bist, drücke <strong>WÜRFELN</strong> oder die Leertaste. Ziehe im Uhrzeigersinn über den Kurs. Jede Runde über Start bringt <strong>+10 Münzen</strong>!',
    rules_tiles_title: '🗺️ Sonderfelder & Gefahren',
    rules_items_title: '🎒 Taktische Gegenstände',
    rules_close_btn: 'Alles klar, zurück zum Spiel!',

    // Tiles
    tile_start: 'Start (+10 🪙)',
    tile_coin: 'Münzfeld',
    tile_star: 'Stern-Podest',
    tile_mystery: 'Glücksrad',
    tile_trap: 'Falle',
    tile_shop: 'Objektladen',
    tile_bank: 'Zentralbank',
    tile_jail: 'Gefängnis / Wache',
    tile_duel: 'Duell-Arena',

    // Titles
    title_novice: 'Neuer Entdecker',
    title_dice: 'Würfel-Akrobat',
    title_star: 'Sternenjäger',
    title_board: 'Meister der Felder',
    title_party: 'Party-Legende'
  },

  es: {
    hero_badge: '🎲 Juego de Mesa Multijugador Vuelta al Mundo',
    hero_title: 'Party<span>Craft</span>',
    hero_subtitle: '¡Tira los dados, compite y festeja por las calles de Estambul, Tokio, París y Nueva York!',
    studio_badge: 'Tu personaje',
    studio_name_label: 'Nombre:',
    studio_name_placeholder: 'Escribe tu nombre...',
    studio_model_label: 'Modelo de Personaje:',
    studio_hat_label: 'Sombrero / Accesorio:',
    studio_color_label: 'Color del Cuerpo:',
    btn_quick_play: 'Partida Rápida',
    btn_quick_play_desc: '¡Empieza de inmediato con 3 bots inteligentes en Estambul sin esperas!',
    btn_play_friends: 'Jugar con Amigos',
    btn_play_friends_desc: '¡Crea una sala en 1 clic, invita amigos por enlace directo o código QR!',
    btn_play_bots: 'Jugar contra Bots',
    btn_play_bots_desc: '¡Elige de 1 a 3 bots y practica sin conexión!',
    btn_how_to_play: 'Cómo Jugar',
    btn_how_to_play_desc: 'Reglas del juego, casillas y tácticas',
    btn_settings: 'Ajustes',
    btn_settings_desc: 'Sonido, música, idioma y gráficos',
    room_code_placeholder: 'CÓDIGO DE SALA (EJ: ABCD)',
    btn_join_room: 'Unirse a Sala',
    server_status_checking: 'Estado del Servidor: Comprobando conexión...',
    server_status_php: 'Estado del Servidor: Multijugador PHP en línea listo',
    server_status_node: 'Estado del Servidor: WebSocket Node.js en línea listo',
    server_status_offline: 'Estado del Servidor: Modo Sin Conexión (Bots y juego local)',

    // Lobby
    lobby_room_title: 'Código de Sala:',
    lobby_players_title: 'Jugadores',
    lobby_share_title: 'Invitar Amigos (Enlace directo):',
    btn_copy_link: '📋 Copiar Enlace',
    btn_copied: '✅ ¡Copiado!',
    btn_show_qr: '📱 Ver Código QR',
    btn_add_bot: '🤖 Añadir Bot',
    btn_leave_room: '🚪 Salir de la Sala',
    lobby_city_title: 'Ciudad / Mapa:',
    lobby_rounds_title: 'Rondas:',
    btn_start_game: '🎉 ¡Empezar Fiesta!',
    lobby_waiting_host: 'Esperando al anfitrión para iniciar...',

    // HUD & In-game
    hud_your_turn: '¡Es tu turno! Tira el dado y avanza',
    hud_turn_prefix: 'Turno: ',
    hud_turn_waiting: 'Turno del Jugador',
    hud_round_prefix: 'Ronda: ',
    btn_roll_dice: '🎲 TIRAR DADO',
    btn_bag: '🎒 Mochila',
    btn_trade: '🤝 Intercambio',
    btn_chat: '💬 Chat',
    btn_hud_leave: 'Salir del Juego',

    // Modals
    roulette_title: '🎡 ¡Ruleta de la Suerte!',
    roulette_spinning: 'La ruleta está girando...',
    roulette_player_at: '¡está en la casilla de la suerte!',
    roulette_won: '¡Felicidades, ganaste el Gran Premio!',

    leave_modal_title: 'Salir de la Partida',
    leave_modal_desc: '¿Seguro que deseas salir al menú principal? En partidas multijugador, un bot de IA ocupará tu lugar.',
    btn_leave_confirm: '🚪 Sí, Salir de la Sala',
    btn_leave_cancel: 'Cancelar / Seguir Jugando',

    settings_title: '⚙️ Ajustes del Juego',
    settings_lang_title: '🌐 Idioma / Language',
    settings_lang_sub: 'Idioma de la interfaz y textos',
    settings_sfx_title: '🔊 Efectos de Sonido',
    settings_sfx_sub: 'Dados, pasos, monedas y fanfarrias',
    settings_music_title: '🎵 Música de Fondo',
    settings_music_sub: 'Banda sonora sintetizada Web Audio',
    settings_dice_title: '🎲 Animaciones 3D del Dado',
    settings_dice_sub: 'Desactiva para resultados instantáneos',
    settings_lowpower_title: '⚡ Modo Ahorro de Energía',
    settings_lowpower_sub: 'Gráficos ligeros para móviles antiguos',
    btn_reset_profile: '🔄 Restablecer Perfil y Datos',
    btn_save_settings: 'Guardar Ajustes',

    rules_title: '📖 PartyCraft — Cómo Jugar',
    rules_goal_title: '🎯 Objetivo del Juego',
    rules_goal_body: '¡Consigue la mayor cantidad de <strong>Estrellas (⭐)</strong> y <strong>Monedas (🪙)</strong> antes de terminar las rondas! 1 Estrella equivale a 20 Monedas y tiene máxima prioridad.',
    rules_turn_title: '🎲 Turnos y Movimiento',
    rules_turn_body: 'En tu turno, pulsa <strong>TIRAR DADO</strong> o barra espaciadora. Avanza en sentido horario por el circuito. ¡Cada vuelta por Salida da <strong>+10 Monedas</strong>!',
    rules_tiles_title: '🗺️ Casillas Especiales y Trampas',
    rules_items_title: '🎒 Objetos Tácticos',
    rules_close_btn: '¡Entendido, volver al juego!',

    // Tiles
    tile_start: 'Salida (+10 🪙)',
    tile_coin: 'Casilla Moneda',
    tile_star: 'Pedestal de Estrella',
    tile_mystery: 'Ruleta de la Suerte',
    tile_trap: 'Casilla Trampa',
    tile_shop: 'Tienda de Objetos',
    tile_bank: 'Banco Central',
    tile_jail: 'Cárcel / Comisaría',
    tile_duel: 'Arena de Duelo',

    // Titles
    title_novice: 'Explorador Novato',
    title_dice: 'Acróbata del Dado',
    title_star: 'Cazador de Estrellas',
    title_board: 'Amo del Tablero',
    title_party: 'Leyenda de la Fiesta'
  },

  fr: {
    hero_badge: '🎲 Jeu de Plateau Multijoueur Tour du Monde',
    hero_title: 'Party<span>Craft</span>',
    hero_subtitle: 'Lancez les dés, faites la course et faites la fête dans les rues d\'Istanbul, Tokyo, Paris et New York !',
    studio_badge: 'Votre personnage',
    studio_name_label: 'Nom du Joueur :',
    studio_name_placeholder: 'Entrez votre nom...',
    studio_model_label: 'Modèle :',
    studio_hat_label: 'Chapeau & Coiffe :',
    studio_color_label: 'Couleur du Corps :',
    btn_quick_play: 'Partie Rapide',
    btn_quick_play_desc: 'Rejoignez immédiatement 3 bots IA à Istanbul sans attendre !',
    btn_play_friends: 'Jouer avec des Amis',
    btn_play_friends_desc: 'Créez une salle en 1 clic, invitez vos amis par lien direct ou QR code !',
    btn_play_bots: 'Jouer contre des Bots',
    btn_play_bots_desc: 'Choisissez de 1 à 3 bots et entraînez-vous hors ligne !',
    btn_how_to_play: 'Comment Jouer',
    btn_how_to_play_desc: 'Règles du jeu, cases et tactiques',
    btn_settings: 'Paramètres',
    btn_settings_desc: 'Audio, musique, langue et graphismes',
    room_code_placeholder: 'CODE DE SALLE (EX: ABCD)',
    btn_join_room: 'Rejoindre la Salle',
    server_status_checking: 'État du Serveur : Vérification de la connexion...',
    server_status_php: 'État du Serveur : Multijoueur PHP en ligne prêt',
    server_status_node: 'État du Serveur : WebSocket Node.js en ligne prêt',
    server_status_offline: 'État du Serveur : Mode Hors Ligne (Bots & Jeu local)',

    // Lobby
    lobby_room_title: 'Code de Salle :',
    lobby_players_title: 'Joueurs',
    lobby_share_title: 'Inviter des Amis (Lien direct) :',
    btn_copy_link: '📋 Copier le Lien' ,
    btn_copied: '✅ Copié !',
    btn_show_qr: '📱 Afficher le QR Code',
    btn_add_bot: '🤖 Ajouter un Bot',
    btn_leave_room: '🚪 Quitter la Salle',
    lobby_city_title: 'Ville / Thème :',
    lobby_rounds_title: 'Nombre de Tours :',
    btn_start_game: '🎉 Lancer la Fête !',
    lobby_waiting_host: 'En attente de l\'hôte pour démarrer...',

    // HUD & In-game
    hud_your_turn: 'À vous de jouer ! Lancez le dé et avancez',
    hud_turn_prefix: 'Tour : ',
    hud_turn_waiting: 'Tour du Joueur',
    hud_round_prefix: 'Manche : ',
    btn_roll_dice: '🎲 LANCER LE DÉ',
    btn_bag: '🎒 Inventaire',
    btn_trade: '🤝 Échange',
    btn_chat: '💬 Chat',
    btn_hud_leave: 'Quitter la Partie',

    // Modals
    roulette_title: '🎡 Roue de la Fortune !',
    roulette_spinning: 'La roue tourne...',
    roulette_player_at: 'est sur la case chance !',
    roulette_won: 'Félicitations, Grand Prix remporté !',

    leave_modal_title: 'Quitter la Partie',
    leave_modal_desc: 'Êtes-vous sûr de vouloir quitter vers le menu principal ? En multijoueur, un bot IA prendra votre place.',
    btn_leave_confirm: '🚪 Oui, Quitter la Salle',
    btn_leave_cancel: 'Annuler / Continuer à Jouer',

    settings_title: '⚙️ Paramètres du Jeu',
    settings_lang_title: '🌐 Langue / Language',
    settings_lang_sub: 'Langue de l\'interface et des textes',
    settings_sfx_title: '🔊 Effets Sonores',
    settings_sfx_sub: 'Dés, pas, pièces et fanfares',
    settings_music_title: '🎵 Musique de Fond',
    settings_music_sub: 'Bande-son festive synthétisée Web Audio',
    settings_dice_title: '🎲 Animations 3D du Dé',
    settings_dice_sub: 'Désactiver pour des résultats instantanés',
    settings_lowpower_title: '⚡ Mode Économie d\'Énergie',
    settings_lowpower_sub: 'Graphismes allégés pour mobiles anciens',
    btn_reset_profile: '🔄 Réinitialiser le Profil',
    btn_save_settings: 'Enregistrer les Paramètres',

    rules_title: '📖 PartyCraft — Comment Jouer',
    rules_goal_title: '🎯 Objectif du Jeu',
    rules_goal_body: 'Récoltez le plus d\'<strong>Étoiles (⭐)</strong> et de <strong>Pièces (🪙)</strong> avant la fin des manches ! 1 Étoile vaut 20 Pièces et prime au classement.',
    rules_turn_title: '🎲 Déplacement et Tours',
    rules_turn_body: 'À votre tour, cliquez sur <strong>LANCER LE DÉ</strong> ou appuyez sur Espace. Avancez dans le sens horaire. Chaque passage par Départ rapporte <strong>+10 Pièces</strong> !',
    rules_tiles_title: '🗺️ Cases Spéciales & Pièges',
    rules_items_title: '🎒 Objets Tactiques',
    rules_close_btn: 'Compris, retour au jeu !',

    // Tiles
    tile_start: 'Départ (+10 🪙)',
    tile_coin: 'Case Pièce',
    tile_star: 'Socle d\'Étoile',
    tile_mystery: 'Roue de la Fortune',
    tile_trap: 'Case Piège',
    tile_shop: 'Boutique d\'Objets',
    tile_bank: 'Banque Centrale',
    tile_jail: 'Prison / Commissariat',
    tile_duel: 'Arène de Duel',

    // Titles
    title_novice: 'Explorateur Novice',
    title_dice: 'Acrobate du Dé',
    title_star: 'Chasseur d\'Étoiles',
    title_board: 'Maître des Cases',
    title_party: 'Légende de la Fête'
  }
};

const SUPPORTED_LANGUAGES = ['tr', 'en', 'de', 'es', 'fr'];

class I18nManager {
  constructor() {
    this.currentLang = this.detectUserLanguage();
  }

  /**
   * Automatically detects the user's preferred language:
   * 1. URL parameter override: ?lang=en / ?lang=de / etc.
   * 2. Saved user preference in localStorage
   * 3. Browser locale from navigator.language / navigator.languages (e.g. US/UK -> en, DE -> de, FR -> fr, ES -> es, TR -> tr)
   * 4. Smart fallback: 'en' for international visitors outside Turkey, 'tr' for Turkish systems.
   */
  detectUserLanguage() {
    // 1. URL Query Parameter Override (?lang=en)
    try {
      if (typeof window !== 'undefined' && window.location && window.location.search) {
        const urlParams = new URLSearchParams(window.location.search);
        const urlLang = urlParams.get('lang');
        if (urlLang && SUPPORTED_LANGUAGES.includes(urlLang.toLowerCase())) {
          const clean = urlLang.toLowerCase();
          try { localStorage.setItem('partycraft_lang', clean); } catch (e) {}
          return clean;
        }
      }
    } catch (e) {}

    // 2. Previously Saved Preference in LocalStorage
    try {
      if (typeof localStorage !== 'undefined') {
        const saved = localStorage.getItem('partycraft_lang');
        if (saved && SUPPORTED_LANGUAGES.includes(saved.toLowerCase())) {
          return saved.toLowerCase();
        }
      }
    } catch (e) {}

    // 3. Browser / System Locale Detection (America, Europe, International)
    try {
      if (typeof navigator !== 'undefined') {
        const locales = navigator.languages || [navigator.language || navigator.userLanguage || ''];
        for (const loc of locales) {
          if (!loc || typeof loc !== 'string') continue;
          const clean = loc.toLowerCase().split('-')[0].split('_')[0]; // 'en-US' -> 'en', 'de-AT' -> 'de'
          if (SUPPORTED_LANGUAGES.includes(clean)) {
            return clean;
          }
        }
      }
    } catch (e) {}

    // 4. Default Fallback: If Turkish system, use 'tr'; otherwise default to international 'en'
    try {
      if (typeof navigator !== 'undefined') {
        const primary = (navigator.language || navigator.userLanguage || '').toLowerCase();
        if (primary.startsWith('tr')) return 'tr';
      }
    } catch (e) {}

    return 'en'; // International English fallback for visitors from America or other countries
  }

  getLang() {
    return this.currentLang;
  }

  t(key, fallback = '') {
    const dict = I18N_DATA[this.currentLang] || I18N_DATA.tr;
    if (dict && dict[key] !== undefined) {
      return dict[key];
    }
    const defaultDict = I18N_DATA.tr;
    return defaultDict[key] !== undefined ? defaultDict[key] : (fallback || key);
  }

  setLang(lang) {
    if (!SUPPORTED_LANGUAGES.includes(lang)) return;
    this.currentLang = lang;
    try {
      localStorage.setItem('partycraft_lang', lang);
    } catch (e) {}

    this.applyTranslations();

    // Notify UI and board if active
    if (window.UIEngine) {
      if (typeof window.UIEngine.onLanguageChanged === 'function') {
        window.UIEngine.onLanguageChanged(lang);
      }
    }
    if (window.BoardMap && typeof window.BoardMap.renderBoard === 'function') {
      window.BoardMap.renderBoard();
    }
  }

  applyTranslations() {
    if (typeof document === 'undefined') return;

    // Update HTML root lang attribute
    try {
      document.documentElement.lang = this.currentLang;
    } catch (e) {}

    // 1. Text content elements
    const elements = document.querySelectorAll('[data-i18n]');
    elements.forEach(el => {
      const key = el.getAttribute('data-i18n');
      const val = this.t(key);
      if (val) {
        if (val.includes('<') && val.includes('>')) {
          el.innerHTML = val;
        } else {
          el.textContent = val;
        }
      }
    });

    // 2. Placeholders
    const inputs = document.querySelectorAll('[data-i18n-placeholder]');
    inputs.forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      const val = this.t(key);
      if (val) {
        el.setAttribute('placeholder', val);
      }
    });

    // 3. Update active state of language toggle buttons
    const langBtns = document.querySelectorAll('.lang-btn, .lang-pill-btn');
    langBtns.forEach(btn => {
      const btnLang = btn.getAttribute('data-lang');
      if (btnLang === this.currentLang) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });
  }
}

// Global instance
window.i18n = new I18nManager();
window.t = (key, fallback) => window.i18n.t(key, fallback);
window.setLanguage = (lang) => window.i18n.setLang(lang);

// Auto-apply translations on initial load
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.i18n.applyTranslations());
  } else {
    window.i18n.applyTranslations();
  }
}


/* =================== 2. QR CODE SVG GENERATOR =================== */
/**
 * Standalone Offline QR Code SVG Generator
 * Based on Kazuhiko Arase's QR Code algorithm (Public Domain / MIT)
 * Zero external libraries, zero CDN, 100% offline pure SVG generation.
 */
(function(window) {
  'use strict';

  // Galois Field and Reed-Solomon tables
  var QRMath = {
    glog: function(n) {
      if (n < 1) throw new Error('glog(' + n + ')');
      return QRMath.LOG_TABLE[n];
    },
    gexp: function(n) {
      while (n < 0) n += 255;
      while (n >= 256) n -= 255;
      return QRMath.EXP_TABLE[n];
    },
    EXP_TABLE: new Array(256),
    LOG_TABLE: new Array(256)
  };

  for (var i = 0; i < 8; i++) QRMath.EXP_TABLE[i] = 1 << i;
  for (var i = 8; i < 256; i++) QRMath.EXP_TABLE[i] = QRMath.EXP_TABLE[i - 4] ^ QRMath.EXP_TABLE[i - 5] ^ QRMath.EXP_TABLE[i - 6] ^ QRMath.EXP_TABLE[i - 8];
  for (var i = 0; i < 255; i++) QRMath.LOG_TABLE[QRMath.EXP_TABLE[i]] = i;

  function QRPolynomial(num, shift) {
    if (num.length === undefined) throw new Error(num.length + '/' + shift);
    var offset = 0;
    while (offset < num.length && num[offset] === 0) offset++;
    this.num = new Array(num.length - offset + shift);
    for (var i = 0; i < num.length - offset; i++) this.num[i] = num[i + offset];
    for (var i = num.length - offset; i < this.num.length; i++) this.num[i] = 0;
  }

  QRPolynomial.prototype = {
    get: function(index) { return this.num[index]; },
    getLength: function() { return this.num.length; },
    multiply: function(e) {
      var num = new Array(this.getLength() + e.getLength() - 1);
      for (var i = 0; i < this.getLength(); i++) {
        for (var j = 0; j < e.getLength(); j++) {
          num[i + j] ^= QRMath.gexp(QRMath.glog(this.get(i)) + QRMath.glog(e.get(j)));
        }
      }
      return new QRPolynomial(num, 0);
    },
    mod: function(e) {
      if (this.getLength() - e.getLength() < 0) return this;
      var ratio = QRMath.glog(this.get(0)) - QRMath.glog(e.get(0));
      var num = new Array(this.getLength());
      for (var i = 0; i < this.getLength(); i++) num[i] = this.get(i);
      for (var i = 0; i < e.getLength(); i++) {
        num[i] ^= QRMath.gexp(QRMath.glog(e.get(i)) + ratio);
      }
      return new QRPolynomial(num, 0).mod(e);
    }
  };

  // QR RS Blocks table for versions 1 to 10 (ECC Level M)
  var RS_BLOCK_TABLE = [
    [1, 26, 16], // v1
    [1, 44, 28], // v2
    [1, 70, 44], // v3
    [2, 50, 32], // v4
    [2, 67, 43], // v5
    [4, 43, 27], // v6
    [4, 53, 31], // v7
    [4, 65, 39], // v8
    [5, 68, 40], // v9
    [5, 78, 46]  // v10
  ];

  function QRCodeModel(typeNumber) {
    this.typeNumber = typeNumber;
    this.modules = null;
    this.moduleCount = 0;
    this.dataList = [];
  }

  QRCodeModel.prototype = {
    addData: function(data) {
      this.dataList.push(data);
    },
    isDark: function(row, col) {
      if (row < 0 || this.moduleCount <= row || col < 0 || this.moduleCount <= col) {
        throw new Error(row + ',' + col);
      }
      return this.modules[row][col];
    },
    getModuleCount: function() {
      return this.moduleCount;
    },
    make: function() {
      this.moduleCount = this.typeNumber * 4 + 17;
      this.modules = new Array(this.moduleCount);
      for (var row = 0; row < this.moduleCount; row++) {
        this.modules[row] = new Array(this.moduleCount);
        for (var col = 0; col < this.moduleCount; col++) {
          this.modules[row][col] = null;
        }
      }
      this.setupPositionProbePattern(0, 0);
      this.setupPositionProbePattern(this.moduleCount - 7, 0);
      this.setupPositionProbePattern(0, this.moduleCount - 7);
      this.setupTimingPattern();
      if (this.typeNumber >= 2) {
        this.setupPositionAdjustPattern();
      }
      this.setupTypeInfo(false, 0);
      this.mapData(this.createData(), 0);
    },
    setupPositionProbePattern: function(row, col) {
      for (var r = -1; r <= 7; r++) {
        if (row + r <= -1 || this.moduleCount <= row + r) continue;
        for (var c = -1; c <= 7; c++) {
          if (col + c <= -1 || this.moduleCount <= col + c) continue;
          if ((0 <= r && r <= 6 && (c === 0 || c === 6)) ||
              (0 <= c && c <= 6 && (r === 0 || r === 6)) ||
              (2 <= r && r <= 4 && 2 <= c && c <= 4)) {
            this.modules[row + r][col + c] = true;
          } else {
            this.modules[row + r][col + c] = false;
          }
        }
      }
    },
    setupTimingPattern: function() {
      for (var r = 8; r < this.moduleCount - 8; r++) {
        if (this.modules[r][6] !== null) continue;
        this.modules[r][6] = (r % 2 === 0);
      }
      for (var c = 8; c < this.moduleCount - 8; c++) {
        if (this.modules[6][c] !== null) continue;
        this.modules[6][c] = (c % 2 === 0);
      }
    },
    setupPositionAdjustPattern: function() {
      var pos = [6, 18, 22, 26, 30, 34][this.typeNumber - 1] || (this.moduleCount - 7);
      var row = pos, col = pos;
      for (var r = -2; r <= 2; r++) {
        for (var c = -2; c <= 2; c++) {
          if (r === -2 || r === 2 || c === -2 || c === 2 || (r === 0 && c === 0)) {
            this.modules[row + r][col + c] = true;
          } else {
            this.modules[row + r][col + c] = false;
          }
        }
      }
    },
    setupTypeInfo: function(test, maskPattern) {
      var data = (1 << 3) | maskPattern; // Level M = 0
      var bits = (data << 10);
      var g = 1335;
      while (bits >= 1024) {
        var shift = Math.floor(Math.log(bits) / Math.LN2) - Math.floor(Math.log(g) / Math.LN2);
        bits ^= (g << shift);
      }
      var format = ((data << 10) | bits) ^ 21522;
      for (var i = 0; i < 15; i++) {
        var mod = (!test && ((format >> i) & 1) === 1);
        if (i < 6) this.modules[i][8] = mod;
        else if (i < 8) this.modules[i + 1][8] = mod;
        else this.modules[this.moduleCount - 15 + i][8] = mod;

        if (i < 8) this.modules[8][this.moduleCount - i - 1] = mod;
        else if (i < 9) this.modules[8][15 - i - 1 + 1] = mod;
        else this.modules[8][15 - i - 1] = mod;
      }
      this.modules[this.moduleCount - 8][8] = !test;
    },
    mapData: function(data, maskPattern) {
      var inc = -1;
      var row = this.moduleCount - 1;
      var bitIndex = 7;
      var byteIndex = 0;
      for (var col = this.moduleCount - 1; col > 0; col -= 2) {
        if (col === 6) col--;
        while (true) {
          for (var c = 0; c < 2; c++) {
            if (this.modules[row][col - c] === null) {
              var dark = false;
              if (byteIndex < data.length) {
                dark = (((data[byteIndex] >>> bitIndex) & 1) === 1);
              }
              var mask = ((row + col - c) % 2 === 0);
              if (mask) dark = !dark;
              this.modules[row][col - c] = dark;
              bitIndex--;
              if (bitIndex === -1) {
                byteIndex++;
                bitIndex = 7;
              }
            }
          }
          row += inc;
          if (row < 0 || this.moduleCount <= row) {
            row -= inc;
            inc = -inc;
            break;
          }
        }
      }
    },
    createData: function() {
      var rsBlock = RS_BLOCK_TABLE[this.typeNumber - 1];
      var totalDataCount = rsBlock[0] * rsBlock[2];
      var buffer = [];
      // 8-bit byte mode indicator (0100)
      buffer.push(4);
      var text = this.dataList.join('');
      var utf8Bytes = [];
      for (var i = 0; i < text.length; i++) {
        var code = text.charCodeAt(i);
        if (code < 128) utf8Bytes.push(code);
        else if (code < 2048) utf8Bytes.push(192 | (code >> 6), 128 | (code & 63));
        else utf8Bytes.push(224 | (code >> 12), 128 | ((code >> 6) & 63), 128 | (code & 63));
      }
      
      // Length
      var bits = [];
      function put(val, len) {
        for (var b = len - 1; b >= 0; b--) {
          bits.push((val >>> b) & 1);
        }
      }
      put(4, 4); // mode
      put(utf8Bytes.length, this.typeNumber < 10 ? 8 : 16); // char count
      for (var i = 0; i < utf8Bytes.length; i++) {
        put(utf8Bytes[i], 8);
      }
      
      // Terminate
      while (bits.length % 8 !== 0) bits.push(0);
      var bytes = [];
      for (var i = 0; i < bits.length; i += 8) {
        var b = 0;
        for (var j = 0; j < 8; j++) b = (b << 1) | bits[i + j];
        bytes.push(b);
      }

      // Pad bytes
      var pad = [236, 17];
      var pidx = 0;
      while (bytes.length < totalDataCount) {
        bytes.push(pad[pidx]);
        pidx = (pidx + 1) % 2;
      }

      // Error correction generator polynomial
      var ecCount = rsBlock[1] - rsBlock[2];
      var gpoly = new QRPolynomial([1], 0);
      for (var i = 0; i < ecCount; i++) {
        gpoly = gpoly.multiply(new QRPolynomial([1, QRMath.gexp(i)], 0));
      }

      var rawPoly = new QRPolynomial(bytes, gpoly.getLength() - 1);
      var modPoly = rawPoly.mod(gpoly);
      var ecBytes = [];
      for (var i = 0; i < gpoly.getLength() - 1; i++) {
        var modIndex = i + modPoly.getLength() - (gpoly.getLength() - 1);
        ecBytes.push(modIndex >= 0 ? modPoly.get(modIndex) : 0);
      }

      return bytes.concat(ecBytes);
    }
  };

  /**
   * Generates a crisp, scalable, standalone SVG string for any URL or text.
   */
  function generateQRCodeSVG(text, size) {
    size = size || 180;
    var len = text.length;
    var type = 3;
    if (len < 14) type = 1;
    else if (len < 26) type = 2;
    else if (len < 42) type = 3;
    else if (len < 62) type = 4;
    else if (len < 84) type = 5;
    else type = 7;

    var qr = new QRCodeModel(type);
    qr.addData(text);
    qr.make();

    var count = qr.getModuleCount();
    var margin = 2;
    var fullSize = count + margin * 2;
    var path = '';

    for (var r = 0; r < count; r++) {
      for (var c = 0; c < count; c++) {
        if (qr.isDark(r, c)) {
          path += 'M' + (c + margin) + ',' + (r + margin) + 'h1v1h-1z ';
        }
      }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + fullSize + ' ' + fullSize + '" width="' + size + '" height="' + size + '" shape-rendering="crispEdges">' +
           '<rect width="100%" height="100%" fill="#FFFFFF" rx="3"/>' +
           '<path d="' + path + '" fill="#0F172A"/>' +
           '</svg>';
  }

  window.generateQRCodeSVG = generateQRCodeSVG;
})(typeof window !== 'undefined' ? window : global);


/* =================== 3. AUDIO SYNTHESIZER =================== */
/**
 * Web Audio API Sound Synthesizer Engine
 * 100% Procedural & Royalty-Free - Zero external dependencies!
 */
class SoundEngine {
  constructor() {
    this.ctx = null;
    this.muted = false;
    this.musicPlaying = false;
    this.musicTimer = null;
    this.musicStep = 0;
  }

  init() {
    if (!this.ctx) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (AudioCtx) {
        this.ctx = new AudioCtx();
      }
    }
    if (this.ctx && this.ctx.state === 'suspended') {
      this.ctx.resume();
    }
  }

  toggleMute() {
    this.muted = !this.muted;
    if (this.muted && this.musicPlaying) {
      this.stopMusic();
    }
    return this.muted;
  }

  playTone(freq, type = 'sine', duration = 0.1, gainVal = 0.15, delay = 0) {
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime + delay;

    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();

    osc.type = type;
    osc.frequency.setValueAtTime(freq, now);

    gain.gain.setValueAtTime(gainVal, now);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

    osc.connect(gain);
    gain.connect(this.ctx.destination);

    osc.start(now);
    osc.stop(now + duration);
  }

  playClick() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(450, now);
    osc.frequency.exponentialRampToValueAtTime(120, now + 0.04);
    gain.gain.setValueAtTime(0.2, now);
    gain.gain.linearRampToValueAtTime(0.001, now + 0.04);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.04);
  }

  playStep() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(320, now);
    osc.frequency.exponentialRampToValueAtTime(540, now + 0.08);
    gain.gain.setValueAtTime(0.18, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.09);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.09);
  }

  playDiceRoll() {
    this.init();
    if (this.muted || !this.ctx) return;
    const clicks = 8;
    for (let i = 0; i < clicks; i++) {
      const delay = i * 0.07;
      const freq = 180 + Math.random() * 220;
      this.playTone(freq, 'triangle', 0.03, 0.15, delay);
    }
  }

  playCoinSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Bouncy two-tone coin arpeggio
    this.playTone(987.77, 'sine', 0.12, 0.22, 0);       // B5
    this.playTone(1318.51, 'triangle', 0.25, 0.28, 0.09); // E6
  }

  playStarFanfare() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Triumphant Mario Party-style trumpet chords
    const notes = [
      { f: 523.25, d: 0.12, t: 0 },    // C5
      { f: 659.25, d: 0.12, t: 0.12 }, // E5
      { f: 783.99, d: 0.12, t: 0.24 }, // G5
      { f: 1046.50, d: 0.45, t: 0.36 } // C6
    ];
    notes.forEach(n => {
      this.playTone(n.f, 'triangle', n.d, 0.25, n.t);
      this.playTone(n.f * 1.002, 'sawtooth', n.d * 0.8, 0.08, n.t);
    });
  }

  playTrapSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(380, now);
    osc.frequency.linearRampToValueAtTime(110, now + 0.35);
    gain.gain.setValueAtTime(0.2, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.35);
  }

  playWarpSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(200, now);
    osc.frequency.exponentialRampToValueAtTime(1200, now + 0.28);
    gain.gain.setValueAtTime(0.22, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.3);
  }

  playCountdown(isFinal = false) {
    this.init();
    if (isFinal) {
      this.playTone(880, 'triangle', 0.35, 0.3);
    } else {
      this.playTone(440, 'sine', 0.15, 0.2);
    }
  }

  playEmoteReaction() {
    this.init();
    if (this.muted || !this.ctx) return;
    this.playTone(600, 'sine', 0.08, 0.15, 0);
    this.playTone(900, 'sine', 0.12, 0.2, 0.07);
  }

  playSlipSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    // Comedic slide whistle up then down + boing
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(300, now);
    osc.frequency.linearRampToValueAtTime(750, now + 0.15);
    osc.frequency.linearRampToValueAtTime(140, now + 0.38);
    gain.gain.setValueAtTime(0.25, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.4);
  }

  playJailSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Clang of iron prison bars
    this.playTone(220, 'sawtooth', 0.3, 0.3, 0);
    this.playTone(180, 'sawtooth', 0.4, 0.25, 0.05);
    this.playTone(110, 'triangle', 0.5, 0.35, 0.08);
  }

  playWheelTick() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(1200 + Math.random() * 300, now);
    osc.frequency.exponentialRampToValueAtTime(180, now + 0.025);
    gain.gain.setValueAtTime(0.18, now);
    gain.gain.linearRampToValueAtTime(0.001, now + 0.025);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.025);
  }

  playLevelUp() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Euphoric triumphant 5-note fanfare
    const notes = [
      { f: 523.25, d: 0.1, t: 0 },
      { f: 659.25, d: 0.1, t: 0.1 },
      { f: 783.99, d: 0.1, t: 0.2 },
      { f: 987.77, d: 0.12, t: 0.3 },
      { f: 1046.50, d: 0.55, t: 0.42 }
    ];
    notes.forEach(n => {
      this.playTone(n.f, 'triangle', n.d, 0.3, n.t);
      this.playTone(n.f * 1.003, 'sine', n.d, 0.15, n.t);
    });
  }

  playScreenShake() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(80, now);
    osc.frequency.linearRampToValueAtTime(35, now + 0.45);
    gain.gain.setValueAtTime(0.35, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.45);
  }

  playChestOpen() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Mystical sparkle arpeggio
    const freqs = [587.33, 739.99, 880.00, 1174.66, 1479.98];
    freqs.forEach((f, idx) => {
      this.playTone(f, 'sine', 0.18, 0.2, idx * 0.06);
    });
  }

  // Procedural Background Music (Charming upbeat lounge arpeggios)
  startMusic() {
    if (this.musicPlaying || this.muted) return;
    this.init();
    if (!this.ctx) return;

    this.musicPlaying = true;
    const bpm = 120;
    const stepTime = (60 / bpm) / 2; // eighth notes

    // Cheerful pentatonic pattern
    const melody = [
      523.25, 0, 659.25, 783.99, 1046.50, 783.99, 659.25, 0,
      587.33, 0, 698.46, 880.00, 1174.66, 880.00, 698.46, 0,
      440.00, 0, 523.25, 659.25, 880.00, 659.25, 523.25, 0,
      392.00, 440.00, 523.25, 659.25, 783.99, 0, 0, 0
    ];

    const bass = [
      130.81, 0, 130.81, 0, 164.81, 0, 196.00, 0,
      146.83, 0, 146.83, 0, 174.61, 0, 220.00, 0,
      110.00, 0, 110.00, 0, 130.81, 0, 164.81, 0,
      98.00, 0, 98.00, 0, 130.81, 0, 196.00, 0
    ];

    const playLoop = () => {
      if (!this.musicPlaying || this.muted) return;
      const mFreq = melody[this.musicStep % melody.length];
      const bFreq = bass[this.musicStep % bass.length];

      if (mFreq > 0) {
        this.playTone(mFreq, 'sine', 0.14, 0.035);
      }
      if (bFreq > 0) {
        this.playTone(bFreq, 'triangle', 0.22, 0.045);
      }

      this.musicStep++;
      this.musicTimer = setTimeout(playLoop, stepTime * 1000);
    };

    playLoop();
  }

  stopMusic() {
    this.musicPlaying = false;
    if (this.musicTimer) {
      clearTimeout(this.musicTimer);
      this.musicTimer = null;
    }
  }

  toggleMusic() {
    if (this.musicPlaying) {
      this.stopMusic();
      return false;
    } else {
      this.startMusic();
      return true;
    }
  }
}

const AudioPlayer = new SoundEngine();


/* =================== 4. PROGRESSION =================== */
/**
 * Player Progression, XP, Level, and Unlockables System
 */
const AVATAR_DEFS = {
  builder: {
    id: 'builder',
    name: 'Minifig Bob',
    requiredLevel: 1,
    desc: 'Neşeli usta lego inşaatçısı.',
    color: '#3B82F6',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="bobHead" cx="40%" cy="35%" r="60%">
          <stop offset="0%" stop-color="#FEF08A"/>
          <stop offset="70%" stop-color="#FACC15"/>
          <stop offset="100%" stop-color="#EAB308"/>
        </radialGradient>
        <linearGradient id="bobHat" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#F97316"/>
          <stop offset="100%" stop-color="#EA580C"/>
        </linearGradient>
      </defs>
      <path d="M12 15 C12 6, 36 6, 36 15 Z" fill="url(#bobHat)"/>
      <ellipse cx="24" cy="15" rx="14" ry="3.5" fill="#C2410C"/>
      <path d="M18 9 Q24 7 30 9" stroke="#FED7AA" stroke-width="1.5" stroke-linecap="round" fill="none"/>
      <rect x="15" y="15" width="18" height="15" rx="4" fill="url(#bobHead)"/>
      <circle cx="20" cy="21" r="2.2" fill="#0F172A"/>
      <circle cx="19.3" cy="20.3" r="0.8" fill="#FFFFFF"/>
      <circle cx="28" cy="21" r="2.2" fill="#0F172A"/>
      <circle cx="27.3" cy="20.3" r="0.8" fill="#FFFFFF"/>
      <ellipse cx="18" cy="24" rx="2" ry="1.2" fill="#F87171" opacity="0.5"/>
      <ellipse cx="30" cy="24" rx="2" ry="1.2" fill="#F87171" opacity="0.5"/>
      <path d="M21 24 Q24 28 27 24" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round" fill="none"/>
      <path d="M11 31 L37 31 L34 45 L14 45 Z" fill="#2563EB"/>
      <rect x="19" y="31" width="10" height="14" fill="#1D4ED8"/>
      <circle cx="16" cy="33" r="1.3" fill="#FBBF24"/>
      <circle cx="32" cy="33" r="1.3" fill="#FBBF24"/>
      <path d="M22 36 L26 40" stroke="#CBD5E1" stroke-width="1.5" stroke-linecap="round"/>
    </svg>`
  },
  dino: {
    id: 'dino',
    name: 'Şapkalı Dino',
    requiredLevel: 1,
    desc: 'Partilere bayılan sevimli yeşil dinozor.',
    color: '#10B981',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="dinoSkin" cx="35%" cy="35%" r="65%">
          <stop offset="0%" stop-color="#6EE7B7"/>
          <stop offset="60%" stop-color="#10B981"/>
          <stop offset="100%" stop-color="#047857"/>
        </radialGradient>
      </defs>
      <polygon points="12,12 8,5 16,10" fill="#F59E0B"/>
      <polygon points="19,8 20,1 23,8" fill="#EF4444"/>
      <polygon points="26,10 32,5 28,12" fill="#3B82F6"/>
      <rect x="12" y="11" width="24" height="20" rx="9" fill="url(#dinoSkin)"/>
      <ellipse cx="24" cy="23" rx="7" ry="4.5" fill="#34D399"/>
      <circle cx="22" cy="22" r="1" fill="#065F46"/>
      <circle cx="26" cy="22" r="1" fill="#065F46"/>
      <circle cx="18" cy="18" r="2.8" fill="#064E3B"/>
      <circle cx="17.2" cy="17" r="1.1" fill="#FFFFFF"/>
      <circle cx="30" cy="18" r="2.8" fill="#064E3B"/>
      <circle cx="29.2" cy="17" r="1.1" fill="#FFFFFF"/>
      <ellipse cx="15" cy="22" rx="2.5" ry="1.5" fill="#F472B6" opacity="0.65"/>
      <ellipse cx="33" cy="22" rx="2.5" ry="1.5" fill="#F472B6" opacity="0.65"/>
      <polygon points="24,2 19,12 29,12" fill="#F43F5E"/>
      <circle cx="24" cy="2" r="1.8" fill="#FDE047"/>
      <path d="M14 31 Q24 29 34 31 L32 45 L16 45 Z" fill="#059669"/>
      <ellipse cx="24" cy="38" rx="6" ry="5" fill="#A7F3D0"/>
    </svg>`
  },
  cat: {
    id: 'cat',
    name: 'Siber Neko',
    requiredLevel: 1,
    desc: 'Hızlı ve şanslı neon siber kedi.',
    color: '#EC4899',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="catSkin" cx="40%" cy="35%" r="60%">
          <stop offset="0%" stop-color="#FBCFE8"/>
          <stop offset="70%" stop-color="#F472B6"/>
          <stop offset="100%" stop-color="#DB2777"/>
        </radialGradient>
      </defs>
      <polygon points="11,18 15,4 23,15" fill="#DB2777"/>
      <polygon points="14,16 16,7 21,14" fill="#FDF2F8"/>
      <polygon points="37,18 33,4 25,15" fill="#DB2777"/>
      <polygon points="34,16 32,7 27,14" fill="#FDF2F8"/>
      <rect x="13" y="13" width="22" height="18" rx="8" fill="url(#catSkin)"/>
      <rect x="11" y="16" width="26" height="5" rx="2.5" fill="#0F172A"/>
      <rect x="13" y="17.5" width="22" height="2" rx="1" fill="#06B6D4"/>
      <ellipse cx="19" cy="22" rx="2.6" ry="3" fill="#0F172A"/>
      <circle cx="18.2" cy="21" r="1.1" fill="#FFFFFF"/>
      <ellipse cx="29" cy="22" rx="2.6" ry="3" fill="#0F172A"/>
      <circle cx="28.2" cy="21" r="1.1" fill="#FFFFFF"/>
      <polygon points="24,24 23,25.5 25,25.5" fill="#9D174D"/>
      <path d="M22 26 Q24 27.5 26 26" stroke="#9D174D" stroke-width="1.3" fill="none"/>
      <line x1="12" y1="23" x2="6" y2="22" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <line x1="12" y1="25" x2="5" y2="26" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <line x1="36" y1="23" x2="42" y2="22" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <line x1="36" y1="25" x2="43" y2="26" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <rect x="16" y="31" width="16" height="13" rx="4" fill="#BE185D"/>
      <circle cx="24" cy="33" r="2.8" fill="#FBBF24"/>
      <circle cx="24" cy="33.8" r="0.8" fill="#78350F"/>
    </svg>`
  },
  astro: {
    id: 'astro',
    name: 'Kozmik Kaşif',
    requiredLevel: 2,
    desc: 'Uzayın derinliklerinden gelen zar ustası.',
    color: '#06B6D4',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="visorGrad" cx="30%" cy="30%" r="70%">
          <stop offset="0%" stop-color="#67E8F9"/>
          <stop offset="50%" stop-color="#0891B2"/>
          <stop offset="100%" stop-color="#083344"/>
        </radialGradient>
      </defs>
      <line x1="24" y1="2" x2="24" y2="8" stroke="#94A3B8" stroke-width="2"/>
      <circle cx="24" cy="2" r="2.2" fill="#EF4444"/>
      <circle cx="24" cy="18" r="13" fill="#E2E8F0"/>
      <ellipse cx="24" cy="18" rx="9" ry="7" fill="url(#visorGrad)"/>
      <path d="M19 14 Q24 12 28 15" stroke="#FFFFFF" stroke-width="1.8" stroke-linecap="round" fill="none" opacity="0.8"/>
      <circle cx="27" cy="19" r="1.2" fill="#FFFFFF" opacity="0.9"/>
      <path d="M13 31 Q24 29 35 31 L33 45 L15 45 Z" fill="#F1F5F9"/>
      <rect x="18" y="33" width="12" height="11" rx="2" fill="#0891B2"/>
      <circle cx="21" cy="37" r="1.2" fill="#22C55E"/>
      <circle cx="27" cy="37" r="1.2" fill="#F59E0B"/>
    </svg>`
  },
  wizard: {
    id: 'wizard',
    name: 'Büyücü Merlin',
    requiredLevel: 2,
    desc: 'Zarları sihirle manipüle eden usta.',
    color: '#8B5CF6',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <linearGradient id="wizHat" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#7C3AED"/>
          <stop offset="100%" stop-color="#4C1D95"/>
        </linearGradient>
      </defs>
      <path d="M24 1 Q34 6 28 17 L12 17 Q18 7 24 1 Z" fill="url(#wizHat)"/>
      <ellipse cx="24" cy="17" rx="14" ry="3.5" fill="#5B21B6"/>
      <circle cx="24" cy="8" r="1.5" fill="#FDE047"/>
      <polygon points="21,12 22,14 24,14 22.5,15 23,17 21,15.5 19,17 19.5,15 18,14 20,14" fill="#FDE047"/>
      <circle cx="24" cy="21" r="7.5" fill="#FED7AA"/>
      <circle cx="21" cy="20" r="1.5" fill="#1E1B4B"/>
      <circle cx="27" cy="20" r="1.5" fill="#1E1B4B"/>
      <path d="M17 23 C14 27, 18 36, 24 36 C30 36, 34 27, 31 23 Z" fill="#F8FAFC"/>
      <path d="M13 34 L35 34 L33 46 L15 46 Z" fill="#6D28D9"/>
      <path d="M24 34 L24 46" stroke="#FDE047" stroke-width="1.8"/>
    </svg>`
  },
  ninja: {
    id: 'ninja',
    name: 'Siber Ninja',
    requiredLevel: 3,
    desc: 'Tuzakları sezen sessiz parti gölgesi.',
    color: '#1E293B',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <path d="M34 16 Q44 14 41 24" stroke="#DC2626" stroke-width="3" fill="none" stroke-linecap="round"/>
      <path d="M34 18 Q42 20 38 28" stroke="#EF4444" stroke-width="2.5" fill="none" stroke-linecap="round"/>
      <rect x="14" y="10" width="20" height="19" rx="7" fill="#0F172A"/>
      <rect x="14" y="12" width="20" height="5" fill="#DC2626"/>
      <rect x="21" y="12.5" width="6" height="4" rx="1" fill="#94A3B8"/>
      <rect x="17" y="18" width="14" height="6" rx="2" fill="#FED7AA"/>
      <circle cx="20" cy="20.5" r="1.6" fill="#0F172A"/>
      <circle cx="19.4" cy="19.8" r="0.6" fill="#EF4444"/>
      <circle cx="28" cy="20.5" r="1.6" fill="#0F172A"/>
      <circle cx="27.4" cy="19.8" r="0.6" fill="#EF4444"/>
      <path d="M13 30 L35 30 L32 45 L16 45 Z" fill="#1E293B"/>
      <line x1="16" y1="31" x2="31" y2="44" stroke="#DC2626" stroke-width="2.5"/>
      <line x1="32" y1="31" x2="17" y2="44" stroke="#DC2626" stroke-width="2.5"/>
    </svg>`
  },
  robot: {
    id: 'robot',
    name: 'Mecha Bot-7',
    requiredLevel: 1,
    desc: 'Hesaplama gücü yüksek tatlı robot dost.',
    color: '#06B6D4',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <line x1="24" y1="2" x2="24" y2="9" stroke="#06B6D4" stroke-width="2.5"/>
      <circle cx="24" cy="3" r="3" fill="#FACC15"/>
      <rect x="11" y="17" width="3" height="6" rx="1" fill="#94A3B8"/>
      <rect x="34" y="17" width="3" height="6" rx="1" fill="#94A3B8"/>
      <rect x="13" y="10" width="22" height="19" rx="5" fill="#64748B"/>
      <rect x="16" y="13" width="16" height="13" rx="3" fill="#0F172A"/>
      <path d="M18 19 Q20 16 22 19" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" fill="none"/>
      <path d="M26 19 Q28 16 30 19" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" fill="none"/>
      <line x1="21" y1="23" x2="27" y2="23" stroke="#22D3EE" stroke-width="1.5" stroke-linecap="round"/>
      <path d="M14 30 L34 30 L32 45 L16 45 Z" fill="#475569"/>
      <rect x="19" y="33" width="10" height="9" rx="2" fill="#1E293B"/>
      <circle cx="22" cy="37" r="1.5" fill="#EF4444"/>
      <circle cx="26" cy="37" r="1.5" fill="#10B981"/>
    </svg>`
  },
  detective: {
    id: 'detective',
    name: 'Dedektif Holmes',
    requiredLevel: 2,
    desc: 'Hileleri ve gizemleri anında çözen dedektif.',
    color: '#78350F',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <path d="M12 16 Q24 5 36 16 Z" fill="#92400E"/>
      <ellipse cx="24" cy="16" rx="15" ry="3.5" fill="#78350F"/>
      <path d="M23 7 Q24 5 25 7" stroke="#FEF3C7" stroke-width="2"/>
      <rect x="15" y="16" width="18" height="15" rx="5" fill="#FED7AA"/>
      <circle cx="19" cy="22" r="2" fill="#1E293B"/>
      <circle cx="18.3" cy="21.3" r="0.7" fill="#FFFFFF"/>
      <circle cx="28" cy="22" r="3.2" fill="none" stroke="#F59E0B" stroke-width="1.8"/>
      <circle cx="28" cy="22" r="1.8" fill="#1E293B"/>
      <line x1="31" y1="23" x2="35" y2="29" stroke="#F59E0B" stroke-width="1.2"/>
      <path d="M21 27 Q24 29 26 26" stroke="#78350F" stroke-width="1.5" fill="none" stroke-linecap="round"/>
      <path d="M12 32 L36 32 L33 46 L15 46 Z" fill="#B45309"/>
      <polygon points="20,32 24,37 28,32" fill="#78350F"/>
    </svg>`
  },
  panda: {
    id: 'panda',
    name: 'Parti Pandası',
    requiredLevel: 3,
    desc: 'Altın toplamayı seven sevimli dev panda.',
    color: '#F97316',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <circle cx="14" cy="11" r="5" fill="#0F172A"/>
      <circle cx="34" cy="11" r="5" fill="#0F172A"/>
      <circle cx="24" cy="21" r="12" fill="#F8FAFC"/>
      <ellipse cx="18" cy="20" rx="3.5" ry="4" fill="#0F172A"/>
      <circle cx="17.5" cy="19.2" r="1.3" fill="#FFFFFF"/>
      <ellipse cx="30" cy="20" rx="3.5" ry="4" fill="#0F172A"/>
      <circle cx="29.5" cy="19.2" r="1.3" fill="#FFFFFF"/>
      <ellipse cx="24" cy="25" rx="2" ry="1.4" fill="#0F172A"/>
      <path d="M22 27 Q24 29 26 27" stroke="#0F172A" stroke-width="1.5" fill="none"/>
      <ellipse cx="13" cy="24" rx="2" ry="1.2" fill="#FDA4AF" opacity="0.6"/>
      <ellipse cx="35" cy="24" rx="2" ry="1.2" fill="#FDA4AF" opacity="0.6"/>
      <polygon points="21,32 27,32 24,35" fill="#EF4444"/>
      <polygon points="21,38 27,38 24,35" fill="#EF4444"/>
      <circle cx="24" cy="35" r="1.5" fill="#FDE047"/>
      <path d="M14 34 L34 34 L32 46 L16 46 Z" fill="#EA580C"/>
    </svg>`
  },
  king: {
    id: 'king',
    name: 'Altın Lego Kralı',
    requiredLevel: 4,
    desc: 'Parti Dünyasının mutlak altın hükümdarı!',
    color: '#EAB308',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <polygon points="12,14 10,4 17,9 24,2 31,9 38,4 36,14" fill="#FACC15" stroke="#CA8A04" stroke-width="1.2"/>
      <circle cx="24" cy="3" r="2" fill="#EF4444"/>
      <circle cx="10" cy="5" r="1.5" fill="#3B82F6"/>
      <circle cx="38" cy="5" r="1.5" fill="#10B981"/>
      <rect x="15" y="14" width="18" height="15" rx="5" fill="#FDE047"/>
      <circle cx="20" cy="20" r="2" fill="#1E293B"/>
      <circle cx="19.3" cy="19.3" r="0.8" fill="#FFFFFF"/>
      <circle cx="28" cy="20" r="2" fill="#1E293B"/>
      <circle cx="27.3" cy="19.3" r="0.8" fill="#FFFFFF"/>
      <path d="M21 24 Q24 28 27 24" stroke="#1E293B" stroke-width="1.8" stroke-linecap="round" fill="none"/>
      <path d="M11 30 L37 30 L34 46 L14 46 Z" fill="#9333EA"/>
      <rect x="17" y="30" width="14" height="16" fill="#F8FAFC"/>
      <circle cx="24" cy="34" r="1.5" fill="#0F172A"/>
      <circle cx="24" cy="40" r="1.5" fill="#0F172A"/>
    </svg>`
  }
};

const HAT_DEFS = {
  none: { id: 'none', name: 'Şapkasız / Sade', requiredLevel: 1, icon: '🙂' },
  party_hat: { id: 'party_hat', name: 'Parti Külahı', requiredLevel: 1, icon: '🎉' },
  crown: { id: 'crown', name: 'Kraliyet Tacı', requiredLevel: 1, icon: '👑' },
  headphones: { id: 'headphones', name: 'DJ Kulaklığı', requiredLevel: 1, icon: '🎧' },
  flower_crown: { id: 'flower_crown', name: 'Çiçek Tacı', requiredLevel: 1, icon: '🌸' },
  pirate: { id: 'pirate', name: 'Korsan Kaptanı', requiredLevel: 2, icon: '🏴‍☠️' },
  wizard: { id: 'wizard', name: 'Büyücü Külahı', requiredLevel: 2, icon: '🧙' },
  cat_ears: { id: 'cat_ears', name: 'Kedi Kulakları', requiredLevel: 2, icon: '🐱' },
  visor: { id: 'visor', name: 'Siber Vizör', requiredLevel: 3, icon: '🕶️' },
  detective: { id: 'detective', name: 'Dedektif Fötrü', requiredLevel: 3, icon: '🕵️' },
  viking: { id: 'viking', name: 'Viking Miğferi', requiredLevel: 4, icon: '⚔️' },
  halo: { id: 'halo', name: 'Melek Halesi', requiredLevel: 4, icon: '😇' }
};

const COLOR_PRESETS = [
  { id: 'blue', hex: '#3B82F6', name: 'Kobalt Mavi' },
  { id: 'red', hex: '#EF4444', name: 'Alev Kırmızı' },
  { id: 'green', hex: '#10B981', name: 'Zümrüt Yeşil' },
  { id: 'amber', hex: '#F59E0B', name: 'Güneş Sarısı' },
  { id: 'purple', hex: '#8B5CF6', name: 'Mistik Mor' },
  { id: 'pink', hex: '#EC4899', name: 'Neon Pembe' },
  { id: 'cyan', hex: '#06B6D4', name: 'Siber Turkuaz' },
  { id: 'dark', hex: '#1E293B', name: 'Gece Grisi' }
];

const DICE_SKINS = {
  default: { id: 'default', name: 'Klasik Beyaz', requiredLevel: 1, color: '#FFFFFF', dotColor: '#1E293B', border: '#CBD5E1' },
  emerald: { id: 'emerald', name: 'Zümrüt Yeşil', requiredLevel: 3, color: '#10B981', dotColor: '#FFFFFF', border: '#059669' },
  ruby: { id: 'ruby', name: 'Alev Kırmızı', requiredLevel: 6, color: '#EF4444', dotColor: '#FEF08A', border: '#B91C1C' },
  neon: { id: 'neon', name: 'Siber Neon', requiredLevel: 10, color: '#06B6D4', dotColor: '#082F49', border: '#0E7490' },
  galaxy: { id: 'galaxy', name: 'Kozmik Galaksi', requiredLevel: 16, color: '#8B5CF6', dotColor: '#FDF4FF', border: '#6D28D9' },
  gold: { id: 'gold', name: 'Saf Altın Zar', requiredLevel: 22, color: '#FACC15', dotColor: '#78350F', border: '#CA8A04' }
};

const TITLES = [
  { id: 't1', title: 'Acemi Gezgin', title_en: 'Novice Explorer', title_de: 'Neuer Entdecker', title_es: 'Explorador Novato', title_fr: 'Explorateur Novice', level: 1 },
  { id: 't2', title: 'Şanslı Tilki', title_en: 'Lucky Fox', title_de: 'Glücksfuchs', title_es: 'Zorro Afortunado', title_fr: 'Renard Chanceux', level: 3 },
  { id: 't3', title: 'Zar Cambazı', title_en: 'Dice Acrobat', title_de: 'Würfel-Akrobat', title_es: 'Acróbata del Dado', title_fr: 'Acrobate du Dé', level: 6 },
  { id: 't4', title: 'Tuzak Bükücü', title_en: 'Trap Bender', title_de: 'Fallenmeister', title_es: 'Domador de Trampas', title_fr: 'Maître des Pièges', level: 10 },
  { id: 't5', title: 'Yıldız Avcısı', title_en: 'Star Hunter', title_de: 'Sternenjäger', title_es: 'Cazador de Estrellas', title_fr: 'Chasseur d\'Étoiles', level: 15 },
  { id: 't6', title: 'Parti Efsanesi', title_en: 'Party Legend', title_de: 'Party-Legende', title_es: 'Leyenda de la Fiesta', title_fr: 'Légende de la Fête', level: 22 }
];

const EMOTE_DEFS = {
  laugh: { id: 'laugh', char: '😂', name: 'Kahkaha', level: 1 },
  cool: { id: 'cool', char: '😎', name: 'Havalı', level: 1 },
  shock: { id: 'shock', char: '😱', name: 'Şok', level: 1 },
  party: { id: 'party', char: '🥳', name: 'Parti', level: 2 },
  fire: { id: 'fire', char: '🔥', name: 'Alev', level: 3 },
  skull: { id: 'skull', char: '💀', name: 'GG', level: 5 },
  rocket: { id: 'rocket', char: '🚀', name: 'Roket', level: 8 },
  crown: { id: 'crown', char: '👑', name: 'Kral', level: 12 }
};

class ProgressionManager {
  constructor() {
    this.storageKey = 'partycraft_player_profile';
    this.profile = this.loadProfile();
  }

  loadProfile() {
    try {
      const data = localStorage.getItem(this.storageKey);
      if (data) {
        const parsed = JSON.parse(data);
        if (!parsed.hat) parsed.hat = 'none';
        if (!parsed.color) parsed.color = '#3B82F6';
        return parsed;
      }
    } catch (e) {
      console.warn('Storage read error:', e);
    }

    // Default Profile
    const defaultProfile = {
      sessionId: 'sess_' + Math.random().toString(36).substr(2, 9),
      name: 'Oyuncu_' + Math.floor(Math.random() * 899 + 100),
      level: 1,
      xp: 0,
      avatar: 'builder',
      hat: 'none',
      color: '#3B82F6',
      diceSkin: 'default',
      title: 'Acemi Gezgin',
      stats: {
        gamesPlayed: 0,
        gamesWon: 0,
        starsCollected: 0,
        coinsEarned: 0,
        minigamesWon: 0
      }
    };
    this.saveProfile(defaultProfile);
    return defaultProfile;
  }

  saveProfile(profile = this.profile) {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify(profile));
    } catch (e) {
      console.warn('Storage save error:', e);
    }
  }

  getXpRequired(level = this.profile.level) {
    return level * 140;
  }

  addXp(amount) {
    this.profile.xp += amount;
    let leveledUp = false;
    let oldLevel = this.profile.level;

    while (this.profile.xp >= this.getXpRequired(this.profile.level)) {
      this.profile.xp -= this.getXpRequired(this.profile.level);
      this.profile.level++;
      leveledUp = true;
    }

    this.saveProfile();
    return {
      leveledUp,
      oldLevel,
      newLevel: this.profile.level,
      currentXp: this.profile.xp,
      neededXp: this.getXpRequired(this.profile.level)
    };
  }

  setAvatar(avatarId) {
    const def = AVATAR_DEFS[avatarId];
    if (def && this.profile.level >= def.requiredLevel) {
      this.profile.avatar = avatarId;
      this.saveProfile();
      return true;
    }
    return false;
  }

  setHat(hatId) {
    const def = HAT_DEFS[hatId];
    if (def && this.profile.level >= def.requiredLevel) {
      this.profile.hat = hatId;
      this.saveProfile();
      return true;
    }
    return false;
  }

  setColor(hexColor) {
    if (hexColor && /^#[0-9A-Fa-f]{6}$/.test(hexColor)) {
      this.profile.color = hexColor;
      this.saveProfile();
      return true;
    }
    return false;
  }

  getUnlockedHats() {
    return Object.values(HAT_DEFS).filter(h => this.profile.level >= h.requiredLevel);
  }

  setDiceSkin(skinId) {
    const def = DICE_SKINS[skinId];
    if (def && this.profile.level >= def.requiredLevel) {
      this.profile.diceSkin = skinId;
      this.saveProfile();
      return true;
    }
    return false;
  }

  setTitle(titleStr) {
    this.profile.title = titleStr;
    this.saveProfile();
  }

  setName(newName) {
    if (newName && newName.trim().length > 0) {
      this.profile.name = newName.trim().substr(0, 16);
      this.saveProfile();
    }
  }

  getUnlockedAvatars() {
    return Object.values(AVATAR_DEFS).filter(a => this.profile.level >= a.requiredLevel);
  }

  getUnlockedDiceSkins() {
    return Object.values(DICE_SKINS).filter(d => this.profile.level >= d.requiredLevel);
  }

  getUnlockedTitles() {
    return TITLES.filter(t => this.profile.level >= t.level);
  }

  getDisplayTitle(titleStr) {
    const tStr = titleStr || this.profile.title || 'Acemi Gezgin';
    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const match = TITLES.find(t => t.title === tStr || t.title_en === tStr || t.title_de === tStr || t.title_es === tStr || t.title_fr === tStr);
    if (match) {
      if (lang === 'en') return match.title_en;
      if (lang === 'de') return match.title_de;
      if (lang === 'es') return match.title_es;
      if (lang === 'fr') return match.title_fr;
      return match.title;
    }
    return tStr;
  }

  getUnlockedEmotes() {
    return Object.values(EMOTE_DEFS).filter(e => this.profile.level >= e.level);
  }
}

const PlayerProgression = new ProgressionManager();


/* =================== 5. BOARD MAP RENDERER =================== */
/**
 * Wonder Board Map Engine - Ultra-Crisp High-DPI Edition
 * Features 3 Interactive Maps (Tropics, Cyber Station, Candy Valley),
 * High-DPI auto-scaling, Anti-Overlap Token Cluster Layout & Interactive Tile Inspector.
 */

const MAP_THEMES = {
  istanbul: {
    id: 'istanbul',
    name: 'İstanbul - Boğazın İncisi',
    desc: 'Tarihi yarımada, Galata Kulesi, Boğaz Köprüsü ve Ayasofya!',
    bgColor: '#0B0F19',
    islandBase: '#1E293B',
    roadBase: '#334155',
    roadBorder: '#0284C7',
    landmark: { name: 'Kız Kulesi & Vapur', x: 880, y: 520, icon: '🏰' }
  },
  tokyo: {
    id: 'tokyo',
    name: 'Tokyo - Neon Şehri',
    desc: 'Shibuya neonları, Tokyo Kulesi, Skytree ve Torii tapınakları!',
    bgColor: '#060714',
    islandBase: '#0F172A',
    roadBase: '#1E1B4B',
    roadBorder: '#06B6D4',
    landmark: { name: 'Tokyo Kulesi & Tapınak', x: 880, y: 520, icon: '🗼' }
  },
  paris: {
    id: 'paris',
    name: 'Paris - Işıklar Şehri',
    desc: 'Eyfel Kulesi, Zafer Takı, Sen Nehri ve Louvre Piramidi!',
    bgColor: '#090D1A',
    islandBase: '#14532D',
    roadBase: '#1E293B',
    roadBorder: '#38BDF8',
    landmark: { name: 'Eyfel & Louvre', x: 880, y: 520, icon: '🗼' }
  },
  newyork: {
    id: 'newyork',
    name: 'New York - Büyük Elma',
    desc: 'Empire State, Özgürlük Heykeli, Brooklyn Köprüsü ve sarı taksiler!',
    bgColor: '#05070F',
    islandBase: '#1E293B',
    roadBase: '#334155',
    roadBorder: '#F59E0B',
    landmark: { name: 'Özgürlük Heykeli', x: 880, y: 520, icon: '🗽' }
  },
  tropics: {
    id: 'tropics',
    aliasOf: 'istanbul',
    name: 'İstanbul - Boğazın İncisi',
    desc: 'Tarihi yarımada, Galata Kulesi, Boğaz Köprüsü ve Ayasofya!',
    bgColor: '#0F172A',
    islandBase: '#FEF3C7',
    roadBase: '#E2E8F0',
    roadBorder: '#0284C7',
    landmark: { name: 'Kız Kulesi & Vapur', x: 880, y: 520, icon: '🏰' }
  },
  cyber: {
    id: 'cyber',
    aliasOf: 'tokyo',
    name: 'Tokyo - Neon Şehri',
    desc: 'Shibuya neonları, Tokyo Kulesi, Skytree ve Torii tapınakları!',
    bgColor: '#090A1A',
    islandBase: '#1E1B4B',
    roadBase: '#1E293B',
    roadBorder: '#06B6D4',
    landmark: { name: 'Tokyo Kulesi & Tapınak', x: 880, y: 520, icon: '🗼' }
  },
  candy: {
    id: 'candy',
    aliasOf: 'paris',
    name: 'Paris - Işıklar Şehri',
    desc: 'Eyfel Kulesi, Zafer Takı, Sen Nehri ve Louvre Piramidi!',
    bgColor: '#1E1B4B',
    islandBase: '#FCE7F3',
    roadBase: '#FFE4E6',
    roadBorder: '#EC4899',
    landmark: { name: 'Eyfel & Louvre', x: 880, y: 520, icon: '🗼' }
  }
};

const BOARD_TILES = [
  { id: 0, type: 'start', title: 'Başlangıç', desc: '+10 Altın Geçiş Bonusu!', icon: 'flag', color: '#10B981' },
  { id: 1, type: 'coin', amount: 4, title: 'Altın Karosu', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 2, type: 'mystery', title: 'Şans Kutusu', desc: 'Sürpriz bir olay seni bekliyor!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 3, type: 'coin', amount: 5, title: 'Altın Karosu', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 4, type: 'trap', penalty: 4, title: 'Tuzak Karosu', desc: '-4 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
  { id: 5, type: 'shop', title: 'Eşya Dükkanı', desc: 'Güçlü eşyalar satın al!', icon: 'shopping-bag', color: '#F59E0B' },
  { id: 6, type: 'coin', amount: 6, title: 'Büyük Altın', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 7, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
  { id: 8, type: 'warp', target: 16, title: 'Hava Geçidi', desc: '16. karoya ışınlan!', icon: 'zap', color: '#06B6D4' },
  { id: 9, type: 'coin', amount: 4, title: 'Altın Karosu', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 10, type: 'duel', title: 'Düello Meydanı', desc: 'Rakipten altın kap!', icon: 'swords', color: '#EC4899' },
  { id: 11, type: 'tax', title: 'Korsan / Vergi', desc: '%25 Altın cezası en fakir oyuncuya dağıtılır!', icon: 'coins', color: '#E11D48' },
  { id: 12, type: 'mystery', title: 'Görünmez Sandık', desc: 'Rulet çarkını çevir!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 13, type: 'coin', amount: 7, title: 'Zengin Kuyu', desc: '+7 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 14, type: 'shop', title: 'Gezgin Satıcı', desc: 'Nadir taktik eşyaları!', icon: 'shopping-bag', color: '#F59E0B' },
  { id: 15, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
  { id: 16, type: 'coin', amount: 4, title: 'Altın Karosu', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 17, type: 'jail', title: 'Kodes / Hapis', desc: '1 tur kilitli kal veya 5 altın kefalet öde!', icon: 'shield', color: '#DC2626' },
  { id: 18, type: 'mystery', title: 'Sihirli Çark', desc: 'Büyük ödüller veya sürprizler!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 19, type: 'coin', amount: 5, title: 'Altın Karosu', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 20, type: 'duel', title: 'Altın Arenası', desc: 'Rakiplerle düello!', icon: 'swords', color: '#EC4899' },
  { id: 21, type: 'freeze', title: 'Buz Tuzağı', desc: 'Donma etkisi! 1 tur eşya kullanamazsın!', icon: 'zap', color: '#0284C7' },
  { id: 22, type: 'coin', amount: 6, title: 'Altın Karosu', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 23, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
  { id: 24, type: 'reverse', title: 'Ters Yön Laneti', desc: 'Pusula bozuldu! Sonraki zarda ters yöne yürü!', icon: 'undo-2', color: '#9333EA' },
  { id: 25, type: 'mystery', title: 'Hazine Sandığı', desc: 'Şansını sına!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 26, type: 'shop', title: 'Parti Marketi', desc: 'Zafer için eşya kuşan!', icon: 'shopping-bag', color: '#F59E0B' },
  { id: 27, type: 'coin', amount: 8, title: 'Dev Hazine', desc: '+8 Altın!', icon: 'coins', color: '#3B82F6' },
  { id: 28, type: 'trap', penalty: 4, title: 'Tuzak Karosu', desc: '-4 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
  { id: 29, type: 'duel', title: 'Büyük Hesaplaşma', desc: 'Liderden altın arakla!', icon: 'swords', color: '#EC4899' },
  { id: 30, type: 'coin', amount: 5, title: 'Altın Karosu', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 31, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' }
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

class BoardRenderer {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
    
    // Virtual resolution (game coordinates)
    this.virtualWidth = 1100;
    this.virtualHeight = 760;
    this.dpr = 1;
    this.baseScale = 1;
    this.offsetX = 0;
    this.offsetY = 0;

    this.tileCoords = this.calculateTilePositions();
    this.playerOffsets = {}; // playerId -> { currentX, currentY, hopOffset }
    this.animationFrame = null;
    this.starPulse = 0;
    this.autoFollow = true;
    this.selectedTileIndex = null;
    this.activeTheme = 'istanbul';

    // Interactive Landmark state
    this.landmarkPulse = 0;
    this.particles = [];

    // Viewport pan & zoom (Center-origin tracking)
    this.cameraFocus = { x: 550, y: 380 };
    this.camera = { dragX: 0, dragY: 0, zoom: 1 };
    this.isDragging = false;
    this.dragStart = { x: 0, y: 0 };
    this.dragDistance = 0;

    this.resizeCanvas();
    this.bindEvents();
    this.startLoop();
  }

  setTheme(themeId) {
    if (MAP_THEMES[themeId]) {
      this.activeTheme = MAP_THEMES[themeId].aliasOf || themeId;
    }
  }

  resizeCanvas() {
    if (!this.canvas) return;
    const parent = this.canvas.parentElement;
    const rect = parent.getBoundingClientRect();
    // High-DPI sharpness without lag: phones (small, very dense screens) gain little above 1.5x
    const dpr = Math.min(window.devicePixelRatio || 1, window.innerWidth < 700 ? 1.5 : 2);

    this.dpr = dpr;
    this.canvas.width = Math.round(rect.width * dpr);
    this.canvas.height = Math.round(rect.height * dpr);
    this.canvas.style.width = `${rect.width}px`;
    this.canvas.style.height = `${rect.height}px`;

    // Uniform fit scale
    const scaleX = rect.width / this.virtualWidth;
    const scaleY = rect.height / this.virtualHeight;
    this.baseScale = Math.min(scaleX, scaleY);
    this.offsetX = (rect.width - (this.virtualWidth * this.baseScale)) / 2;
    this.offsetY = (rect.height - (this.virtualHeight * this.baseScale)) / 2;
  }

  calculateTilePositions() {
    // Dynamic, organic, fluid racetrack (Mario Party / Dokapon style)
    // Curving gracefully across scenic bays, bridges, coastal promenades and skyline heights
    return [
      { x: 206, y: 492 }, // 0: Start Meydanı (Liman Başlangıç)
      { x: 267, y: 530 }, // 1: Sahil Kordonu
      { x: 329, y: 564 }, // 2: Balıkçı İskelesi
      { x: 395, y: 592 }, // 3: Çeşme Meydanı
      { x: 463, y: 613 }, // 4: Boğaz Yolu
      { x: 533, y: 626 }, // 5: Marina Kordonu
      { x: 604, y: 629 }, // 6: Kıyı Parkı
      { x: 675, y: 623 }, // 7: Star Pedestal
      { x: 745, y: 608 }, // 8: Doğu Sahili
      { x: 813, y: 586 }, // 9: Hisar Yokuşu
      { x: 877, y: 555 }, // 10: Düello Meydanı
      { x: 937, y: 517 }, // 11: Köprü Yaklaşımı
      { x: 990, y: 469 }, // 12: Şans Kemeri
      { x: 1028, y: 409 }, // 13: Seyir Tepesi
      { x: 1034, y: 339 }, // 14: Kule Yolu
      { x: 1000, y: 277 }, // 15: Star Pedestal
      { x: 944, y: 235 }, // 16: Hava Geçidi
      { x: 878, y: 207 }, // 17: Kodes & Hisar
      { x: 809, y: 191 }, // 18: Sihirli Çark
      { x: 738, y: 182 }, // 19: Panorama Terası
      { x: 667, y: 180 }, // 20: Çamlıca Zirvesi
      { x: 595, y: 179 }, // 21: Donma Vadisi
      { x: 524, y: 177 }, // 22: Saray Sırtı
      { x: 453, y: 174 }, // 23: Star Pedestal
      { x: 381, y: 174 }, // 24: Ters Rüzgar Tepesi
      { x: 310, y: 179 }, // 25: Sandık Bahçesi
      { x: 241, y: 194 }, // 26: Kapalıçarşı Kemeri
      { x: 174, y: 220 }, // 27: Hazine Meydanı
      { x: 118, y: 263 }, // 28: Dikenli Vadi
      { x: 88, y: 326 },  // 29: Büyük Arena
      { x: 104, y: 394 }, // 30: Gülhane Yolu
      { x: 149, y: 449 }  // 31: Star Kaidesi
    ];
  }

  bindEvents() {
    if (!this.canvas) return;

    window.addEventListener('resize', () => this.resizeCanvas());

    // Mouse Controls (Center-origin offset)
    this.canvas.addEventListener('mousedown', (e) => {
      this.isDragging = true;
      this.dragDistance = 0;
      this.dragStart = { x: e.clientX - this.camera.dragX, y: e.clientY - this.camera.dragY };
    });

    window.addEventListener('mousemove', (e) => {
      if (this.isDragging) {
        const dx = e.clientX - (this.dragStart.x + this.camera.dragX);
        const dy = e.clientY - (this.dragStart.y + this.camera.dragY);
        this.dragDistance += Math.hypot(dx, dy);
        this.camera.dragX = e.clientX - this.dragStart.x;
        this.camera.dragY = e.clientY - this.dragStart.y;
        this.autoFollow = false;
      }
    });

    window.addEventListener('mouseup', (e) => {
      if (this.isDragging && this.dragDistance < 6) {
        // Was a Click! (not a drag)
        this.handleCanvasClick(e.clientX, e.clientY);
      }
      this.isDragging = false;
    });

    // Touch Controls
    let touchStart = { x: 0, y: 0 };
    this.canvas.addEventListener('touchstart', (e) => {
      if (e.touches.length === 1) {
        this.isDragging = true;
        this.dragDistance = 0;
        touchStart = {
          x: e.touches[0].clientX - this.camera.dragX,
          y: e.touches[0].clientY - this.camera.dragY
        };
      }
    }, { passive: true });

    this.canvas.addEventListener('touchmove', (e) => {
      if (this.isDragging && e.touches.length === 1) {
        const dx = e.touches[0].clientX - (touchStart.x + this.camera.dragX);
        const dy = e.touches[0].clientY - (touchStart.y + this.camera.dragY);
        this.dragDistance += Math.hypot(dx, dy);
        this.camera.dragX = e.touches[0].clientX - touchStart.x;
        this.camera.dragY = e.touches[0].clientY - touchStart.y;
        this.autoFollow = false;
      }
    }, { passive: true });

    this.canvas.addEventListener('touchend', (e) => {
      if (this.isDragging && this.dragDistance < 10 && e.changedTouches.length === 1) {
        this.handleCanvasClick(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
      }
      this.isDragging = false;
    });

    // Mouse Wheel Zoom
    this.canvas.addEventListener('wheel', (e) => {
      e.preventDefault();
      const zoomFactor = e.deltaY < 0 ? 1.08 : 0.92;
      this.camera.zoom = Math.max(0.65, Math.min(2.0, this.camera.zoom * zoomFactor));
    }, { passive: false });
  }

  handleCanvasClick(screenX, screenY) {
    const rect = this.canvas.getBoundingClientRect();
    const canvasX = (screenX - rect.left) * (this.canvas.width / rect.width) / this.dpr;
    const canvasY = (screenY - rect.top) * (this.canvas.height / rect.height) / this.dpr;
    const viewportW = this.canvas.width / this.dpr;
    const viewportH = this.canvas.height / this.dpr;

    const totalScale = this.baseScale * this.camera.zoom;
    const worldX = ((canvasX - (viewportW / 2 + this.camera.dragX)) / totalScale) + this.cameraFocus.x;
    const worldY = ((canvasY - (viewportH / 2 + this.camera.dragY)) / totalScale) + this.cameraFocus.y;

    // Check click on interactive Landmark
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.tropics;
    const lm = theme.landmark;
    if (Math.hypot(worldX - lm.x, worldY - lm.y) < 44) {
      this.triggerLandmarkInteraction(lm.x, lm.y);
      return;
    }

    // Check click on any tile
    for (let i = 0; i < this.tileCoords.length; i++) {
      const pos = this.tileCoords[i];
      if (Math.hypot(worldX - pos.x, worldY - pos.y) < 36) {
        this.selectTile(i);
        return;
      }
    }
  }

  triggerLandmarkInteraction(x, y) {
    AudioPlayer.playStarFanfare();
    // Burst particles
    for (let i = 0; i < 18; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 2 + Math.random() * 4;
      this.particles.push({
        x, y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        life: 1.0,
        color: ['#FBBF24', '#38BDF8', '#EC4899', '#10B981'][Math.floor(Math.random() * 4)]
      });
    }
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.tropics;
    if (window.UI) UI.showToast(`✨ ${theme.landmark.name} ile etkileşime geçtin!`, 'info');
  }

  selectTile(index) {
    this.selectedTileIndex = index;
    AudioPlayer.playClick();

    const roomState = window.CURRENT_ROOM_STATE;
    const currentBoard = (roomState && roomState.board) ? roomState.board : BOARD_TILES;
    const tile = currentBoard[index];
    const playersOnTile = roomState ? roomState.players.filter(p => p.position === index) : [];
    
    // Distance from active player
    let stepsAway = 0;
    if (roomState && roomState.players) {
      const activeP = roomState.players[roomState.turnIndex];
      if (activeP) {
        stepsAway = (index - activeP.position + currentBoard.length) % currentBoard.length;
      }
    }

    if (window.UI && window.UI.showTileInspector) {
      window.UI.showTileInspector(tile, index, playersOnTile, stepsAway);
    }
  }

  focusPlayer(player) {
    if (!player || !this.autoFollow) return;
    const anim = this.playerHopState && this.playerHopState[player.id];
    const px = anim ? anim.displayX : (this.tileCoords[player.position] ? this.tileCoords[player.position].x : 550);
    const py = anim ? (anim.displayY + anim.hopArcY) : (this.tileCoords[player.position] ? this.tileCoords[player.position].y : 380);
    // Smooth lerp focus directly onto character token without drift
    this.cameraFocus.x += (px - this.cameraFocus.x) * 0.08;
    this.cameraFocus.y += (py - this.cameraFocus.y) * 0.08;
  }

  resetCamera() {
    this.camera = { dragX: 0, dragY: 0, zoom: 1 };
    this.cameraFocus = { x: 550, y: 380 };
    this.autoFollow = true;
  }

  zoomIn() {
    this.camera.zoom = Math.min(2.0, this.camera.zoom * 1.15);
  }

  zoomOut() {
    this.camera.zoom = Math.max(0.65, this.camera.zoom * 0.85);
  }

  startLoop() {
    const render = () => {
      this.draw();
      this.animationFrame = requestAnimationFrame(render);
    };
    this.animationFrame = requestAnimationFrame(render);
  }

  draw() {
    if (!this.ctx) return;
    const ctx = this.ctx;

    // Reset transform & clear
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    const viewportW = this.canvas.width / this.dpr;
    const viewportH = this.canvas.height / this.dpr;

    // Apply high-DPI
    ctx.scale(this.dpr, this.dpr);

    // Deep dark celestial background for entire viewport (No white borders on any screen!)
    const currentTheme = MAP_THEMES[this.activeTheme] || MAP_THEMES.istanbul;
    ctx.fillStyle = currentTheme.bgColor || '#0B0F19';
    ctx.fillRect(0, 0, viewportW, viewportH);

    // Apply Center-Origin Camera Projection (Camera stays 100% centered on character on zoom!)
    ctx.translate(viewportW / 2 + this.camera.dragX, viewportH / 2 + this.camera.dragY);
    const totalScale = this.baseScale * this.camera.zoom;
    ctx.scale(totalScale, totalScale);
    ctx.translate(-this.cameraFocus.x, -this.cameraFocus.y);

    // 1. Draw Environment Backdrop (City-specific)
    this.drawEnvironment(ctx);

    // 2. Draw Road Pathways with Directional Flow Chevrons
    this.drawPathways(ctx);

    // 3. Draw Projected Step Trajectory
    this.drawStepTrajectory(ctx);

    // 4. Draw 3D Stylized Platform Tiles
    const roomState = window.CURRENT_ROOM_STATE;
    const currentBoard = (roomState && roomState.board) ? roomState.board : BOARD_TILES;
    const activeStar = roomState ? roomState.activeStarTile : 7;
    const traps = roomState ? (roomState.trapsOnBoard || {}) : {};

    for (let i = 0; i < this.tileCoords.length; i++) {
      const tile = currentBoard[i] || BOARD_TILES[i];
      const pos = this.tileCoords[i];
      const isStarTile = (i === activeStar);
      const isSelected = (i === this.selectedTileIndex);
      const hasTrap = !!traps[i];
      this.drawPlatformTile(ctx, i, tile, pos, isStarTile, isSelected, hasTrap);
    }

    // 5. Draw Interactive Landmark
    this.drawLandmark(ctx);

    // 6. Draw Dramatic Turn Spotlight
    this.drawTurnSpotlight(ctx);

    // 7. Draw Particles
    this.drawParticles(ctx);

    // 8. Draw Spatially Separated, Non-Overlapping Player Tokens (Y-Sorted)
    if (roomState && roomState.players) {
      this.drawPlayerTokens(ctx, roomState.players);
      const activePlayer = roomState.players[roomState.turnIndex];
      if (activePlayer && this.autoFollow) {
        this.focusPlayer(activePlayer);
      }
    }

    this.starPulse += 0.04;
  }

  drawEnvironment(ctx) {
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.istanbul;
    ctx.save();

    // 1. Full-Canvas Thematic Sky & Atmosphere (Twinkling stars, sunset glow, clouds)
    this.drawSkyAndAtmosphere(ctx, theme);

    // 2. Full-Canvas Terrain & Water Bodies (Replaces the empty void with living diorama)
    this.drawWaterAndTerrain(ctx, theme);

    // 3. Iconic City Architecture & Living Scenery
    const themeKey = theme.aliasOf || this.activeTheme;
    if (themeKey === 'tokyo') {
      this.drawTokyoEnvironment(ctx);
    } else if (themeKey === 'paris') {
      this.drawParisEnvironment(ctx);
    } else if (themeKey === 'newyork') {
      this.drawNewYorkEnvironment(ctx);
    } else {
      this.drawIstanbulEnvironment(ctx);
    }

    // 4. Ambient Street Lamps, Animated Vehicles & Foliage (Eliminates any bare canvas)
    this.drawAmbientWorldDetails(ctx, theme);

    ctx.restore();
  }

  /* ----------------------------------------------------
     ATMOSPHERIC SKY & HORIZON (Full 1100x760 Canvas)
     ---------------------------------------------------- */
  drawSkyAndAtmosphere(ctx, theme) {
    const themeKey = theme.aliasOf || this.activeTheme;

    // Atmospheric Gradient Backdrop
    const skyGrad = ctx.createLinearGradient(0, 0, 0, 760);
    if (themeKey === 'tokyo') {
      skyGrad.addColorStop(0, '#060714');
      skyGrad.addColorStop(0.35, '#0F1035');
      skyGrad.addColorStop(0.7, '#1E1B4B');
      skyGrad.addColorStop(1, '#090A1A');
    } else if (themeKey === 'paris') {
      skyGrad.addColorStop(0, '#1E1B4B');
      skyGrad.addColorStop(0.4, '#4C1D95');
      skyGrad.addColorStop(0.75, '#831843');
      skyGrad.addColorStop(1, '#3B0764');
    } else if (themeKey === 'newyork') {
      skyGrad.addColorStop(0, '#030712');
      skyGrad.addColorStop(0.4, '#0F172A');
      skyGrad.addColorStop(0.75, '#1E293B');
      skyGrad.addColorStop(1, '#111827');
    } else {
      // Istanbul Twilight
      skyGrad.addColorStop(0, '#0A192F');
      skyGrad.addColorStop(0.35, '#0F2B48');
      skyGrad.addColorStop(0.65, '#1E3A8A');
      skyGrad.addColorStop(0.85, '#D97706');
      skyGrad.addColorStop(1, '#78350F');
    }

    ctx.fillStyle = skyGrad;
    ctx.fillRect(-2500, -2500, 6000, 6000);

    // Twinkling Stars in upper sky
    ctx.save();
    const starCoords = [
      { x: 90, y: 35, s: 1.6 }, { x: 210, y: 55, s: 1.2 }, { x: 330, y: 30, s: 2.0 },
      { x: 470, y: 50, s: 1.4 }, { x: 590, y: 25, s: 1.8 }, { x: 710, y: 45, s: 1.3 },
      { x: 840, y: 30, s: 2.2 }, { x: 970, y: 60, s: 1.5 }, { x: 1040, y: 40, s: 1.7 },
      { x: 150, y: 95, s: 1.3 }, { x: 380, y: 110, s: 1.1 }, { x: 670, y: 90, s: 1.6 },
      { x: 890, y: 105, s: 1.4 }
    ];
    starCoords.forEach((st, i) => {
      const alpha = 0.35 + 0.65 * Math.abs(Math.sin(this.starPulse * 2.2 + i * 1.5));
      ctx.fillStyle = `rgba(255, 255, 255, ${alpha})`;
      ctx.beginPath();
      ctx.arc(st.x, st.y, st.s, 0, Math.PI * 2);
      ctx.fill();
    });

    // Glowing Celestial Body
    if (themeKey === 'tokyo') {
      ctx.save();
      ctx.fillStyle = '#F43F5E';
      ctx.shadowColor = '#F43F5E';
      ctx.shadowBlur = 25;
      ctx.beginPath();
      ctx.arc(930, 85, 24, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = 'rgba(244, 63, 94, 0.2)';
      ctx.beginPath();
      ctx.arc(930, 85, 38, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    } else {
      ctx.save();
      ctx.translate(140, 75);
      ctx.fillStyle = '#FEF08A';
      ctx.shadowColor = '#FDE047';
      ctx.shadowBlur = 18;
      ctx.beginPath();
      ctx.arc(0, 0, 16, 0, Math.PI * 2);
      ctx.fill();
      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath();
      ctx.arc(6, -4, 14, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }

    // Drifting Stylized Sunset Clouds
    ctx.fillStyle = (themeKey === 'paris') ? 'rgba(252, 231, 243, 0.25)' : 'rgba(255, 255, 255, 0.14)';
    const cloudOffsets = [0, 380, 760, 1140];
    cloudOffsets.forEach((co, idx) => {
      const cx = ((this.starPulse * 14 + co) % 1320) - 120;
      const cy = 45 + (idx % 2) * 35;
      ctx.beginPath();
      ctx.arc(cx, cy, 20, 0, Math.PI * 2);
      ctx.arc(cx + 20, cy - 8, 26, 0, Math.PI * 2);
      ctx.arc(cx + 44, cy, 18, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.restore();
  }

  /* ----------------------------------------------------
     BASE WATERWAYS & 3D FLOATING DIORAMA TERRAIN
     ---------------------------------------------------- */
  drawWaterAndTerrain(ctx, theme) {
    const themeKey = theme.aliasOf || this.activeTheme;
    ctx.save();

    // 1. Surrounding Atmospheric Ocean / Void Base
    const oceanGrad = ctx.createRadialGradient(550, 390, 200, 550, 390, 950);
    if (themeKey === 'tokyo') {
      oceanGrad.addColorStop(0, '#090D1E');
      oceanGrad.addColorStop(0.6, '#050711');
      oceanGrad.addColorStop(1, '#020308');
    } else if (themeKey === 'paris') {
      oceanGrad.addColorStop(0, '#0D1527');
      oceanGrad.addColorStop(0.6, '#080C18');
      oceanGrad.addColorStop(1, '#03050B');
    } else if (themeKey === 'newyork') {
      oceanGrad.addColorStop(0, '#0B132B');
      oceanGrad.addColorStop(0.6, '#060B18');
      oceanGrad.addColorStop(1, '#020409');
    } else {
      oceanGrad.addColorStop(0, '#0C2340');
      oceanGrad.addColorStop(0.6, '#081729');
      oceanGrad.addColorStop(1, '#030810');
    }
    ctx.fillStyle = oceanGrad;
    ctx.fillRect(-2500, -2500, 6000, 6000);

    // 2. Volumetric Drop Shadow under the Floating Diorama Island
    ctx.beginPath();
    ctx.ellipse(550, 440, 540, 310, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
    ctx.fill();

    // 3. 3D Floating Island Cliff Underside (Layered Bedrock Strata)
    ctx.beginPath();
    ctx.ellipse(550, 422, 528, 305, 0, 0, Math.PI); // bottom half of lower rim
    ctx.ellipse(550, 392, 528, 305, 0, Math.PI, 0, true); // bottom half of upper rim reversed
    ctx.closePath();
    const cliffGrad = ctx.createLinearGradient(0, 390, 0, 428);
    cliffGrad.addColorStop(0, '#1E293B');
    cliffGrad.addColorStop(0.5, '#0F172A');
    cliffGrad.addColorStop(1, '#020617');
    ctx.fillStyle = cliffGrad;
    ctx.fill();

    // Cliff vertical rock strata ribs
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
    ctx.lineWidth = 1.5;
    for (let angle = 0.2; angle < Math.PI - 0.2; angle += 0.14) {
      const rx = 528 * Math.cos(angle);
      const ry1 = 392 + 305 * Math.sin(angle);
      const ry2 = 422 + 305 * Math.sin(angle);
      ctx.beginPath();
      ctx.moveTo(550 + rx, ry1);
      ctx.lineTo(550 + rx, ry2);
      ctx.stroke();
    }

    // 4. Coastal Shoreline & Golden Sand Beach Shelf
    ctx.beginPath();
    ctx.ellipse(550, 390, 528, 305, 0, 0, Math.PI * 2);
    const sandGrad = ctx.createLinearGradient(550, 85, 550, 695);
    if (themeKey === 'tokyo') {
      sandGrad.addColorStop(0, '#1E1B4B');
      sandGrad.addColorStop(1, '#312E81');
    } else {
      sandGrad.addColorStop(0, '#FDE68A');
      sandGrad.addColorStop(0.5, '#F59E0B');
      sandGrad.addColorStop(1, '#D97706');
    }
    ctx.fillStyle = sandGrad;
    ctx.fill();

    // Water shoreline foam line
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.ellipse(550, 390, 529, 306, 0, 0, Math.PI * 2);
    ctx.stroke();

    // 5. Main Island Plateau (Lush Green Parklands / City Ground)
    ctx.beginPath();
    ctx.ellipse(550, 388, 508, 292, 0, 0, Math.PI * 2);
    const parkGrad = ctx.createRadialGradient(550, 388, 50, 550, 388, 500);
    if (themeKey === 'paris') {
      parkGrad.addColorStop(0, '#166534'); // Rich Parisian garden green
      parkGrad.addColorStop(0.7, '#14532D');
      parkGrad.addColorStop(1, '#0F3E22');
    } else if (themeKey === 'tokyo') {
      parkGrad.addColorStop(0, '#0F172A');
      parkGrad.addColorStop(0.7, '#1E1B4B');
      parkGrad.addColorStop(1, '#090D1A');
    } else if (themeKey === 'newyork') {
      parkGrad.addColorStop(0, '#1F2937');
      parkGrad.addColorStop(0.65, '#166534'); // Central Park lushness
      parkGrad.addColorStop(1, '#111827');
    } else {
      // Istanbul
      parkGrad.addColorStop(0, '#1E293B');
      parkGrad.addColorStop(0.65, '#14532D');
      parkGrad.addColorStop(1, '#0F172A');
    }
    ctx.fillStyle = parkGrad;
    ctx.fill();

    // 6. Inner Cobblestone Promenade Ring
    ctx.strokeStyle = (themeKey === 'tokyo') ? 'rgba(56, 189, 248, 0.22)' : 'rgba(255, 255, 255, 0.12)';
    ctx.lineWidth = 14;
    ctx.beginPath();
    ctx.ellipse(550, 388, 435, 235, 0, 0, Math.PI * 2);
    ctx.stroke();

    // 7. Radial Parisian/City Boulevards connecting center to racetrack
    ctx.strokeStyle = (themeKey === 'tokyo') ? 'rgba(56, 189, 248, 0.15)' : 'rgba(255, 255, 255, 0.08)';
    ctx.lineWidth = 10;
    const radAngles = [0, Math.PI / 4, 3 * Math.PI / 4, Math.PI, 5 * Math.PI / 4, 7 * Math.PI / 4];
    radAngles.forEach(ang => {
      ctx.beginPath();
      ctx.moveTo(550 + Math.cos(ang) * 90, 388 + Math.sin(ang) * 55);
      ctx.lineTo(550 + Math.cos(ang) * 420, 388 + Math.sin(ang) * 225);
      ctx.stroke();
    });

    ctx.restore();
  }

  /* ----------------------------------------------------
     1. İSTANBUL ENVIRONMENT (Boğaz, Galata, Ayasofya, Köprü)
     ---------------------------------------------------- */
  drawIstanbulEnvironment(ctx) {
    // 1. Bosphorus Strait Water Channel (Boğaz)
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(380, 50);
    ctx.bezierCurveTo(460, 220, 340, 420, 520, 730);
    ctx.lineTo(660, 730);
    ctx.bezierCurveTo(490, 420, 610, 220, 520, 50);
    ctx.closePath();
    const waterGrad = ctx.createLinearGradient(400, 50, 600, 730);
    waterGrad.addColorStop(0, '#0284C7');
    waterGrad.addColorStop(0.5, '#0369A1');
    waterGrad.addColorStop(1, '#075985');
    ctx.fillStyle = waterGrad;
    ctx.fill();

    // Animated water ripples
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
    ctx.lineWidth = 1.8;
    for (let r = 0; r < 6; r++) {
      const rx = 440 + Math.sin(this.starPulse + r * 1.5) * 40;
      const ry = 140 + r * 90 + Math.cos(this.starPulse + r) * 15;
      ctx.beginPath();
      ctx.arc(rx, ry, 22 + r * 4, 0.2 * Math.PI, 0.8 * Math.PI);
      ctx.stroke();
    }

    // 2. Şehir Hatları Vapuru (Istanbul Ferry)
    const ferryBob = Math.sin(this.starPulse * 1.5) * 3;
    const fx = 470, fy = 360 + ferryBob;
    ctx.save();
    ctx.translate(fx, fy);
    // Hull
    ctx.beginPath();
    ctx.moveTo(-36, 10); ctx.lineTo(36, 10); ctx.lineTo(28, 24); ctx.lineTo(-28, 24); ctx.closePath();
    ctx.fillStyle = '#1E293B'; ctx.fill();
    ctx.beginPath();
    ctx.moveTo(-38, 2); ctx.lineTo(38, 2); ctx.lineTo(34, 10); ctx.lineTo(-34, 10); ctx.closePath();
    ctx.fillStyle = '#FFFFFF'; ctx.fill();
    // Cabins
    ctx.fillStyle = '#F8FAFC';
    ctx.fillRect(-24, -12, 48, 14);
    ctx.fillStyle = '#0284C7';
    for (let p = -18; p <= 18; p += 9) {
      ctx.fillRect(p, -8, 5, 5);
    }
    // Smokestack & Chimney smoke
    ctx.fillStyle = '#EF4444';
    ctx.fillRect(-4, -20, 8, 8);
    ctx.fillStyle = '#FBBF24';
    ctx.fillRect(-4, -16, 8, 2);
    // Smoke puffs
    ctx.fillStyle = 'rgba(255, 255, 255, 0.45)';
    ctx.beginPath();
    ctx.arc(-2 + Math.sin(this.starPulse * 2) * 4, -26, 4, 0, Math.PI * 2);
    ctx.arc(-5 + Math.sin(this.starPulse * 2.2) * 6, -34, 6, 0, Math.PI * 2);
    ctx.fill();
    // Turkish Flag
    ctx.fillStyle = '#DC2626';
    ctx.fillRect(28, -6, 10, 6);
    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 5px sans-serif';
    ctx.fillText('☪', 33, -2);
    ctx.restore();

    // 3. 15 Temmuz Şehitler Köprüsü (Bosphorus Bridge)
    ctx.save();
    ctx.strokeStyle = '#94A3B8';
    ctx.lineWidth = 4;
    // European Tower
    ctx.beginPath(); ctx.moveTo(400, 110); ctx.lineTo(400, 200); ctx.stroke();
    // Asian Tower
    ctx.beginPath(); ctx.moveTo(520, 95); ctx.lineTo(520, 185); ctx.stroke();
    // Bridge roadway deck
    ctx.strokeStyle = '#334155';
    ctx.lineWidth = 6;
    ctx.beginPath(); ctx.moveTo(350, 160); ctx.lineTo(570, 145); ctx.stroke();
    // Catenary Cables
    ctx.strokeStyle = '#CBD5E1';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(350, 160);
    ctx.quadraticCurveTo(460, 175, 570, 145);
    ctx.stroke();
    // Traffic dots (glowing red and white lights)
    const trafficOffset = (this.starPulse * 20) % 200;
    ctx.fillStyle = '#EF4444';
    ctx.fillRect(360 + (trafficOffset % 190), 158, 4, 2);
    ctx.fillStyle = '#FEF08A';
    ctx.fillRect(550 - (trafficOffset % 190), 148, 4, 2);
    ctx.restore();

    // 4. Galata Kulesi (Galata Tower) at Europe side (x: 230, y: 190)
    ctx.save();
    ctx.translate(230, 190);
    // Base shadow
    ctx.beginPath(); ctx.ellipse(0, 30, 22, 10, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(15, 23, 42, 0.15)'; ctx.fill();
    // Stone Tower Shaft
    ctx.fillStyle = '#D6D3D1';
    ctx.fillRect(-16, -25, 32, 55);
    // Masonry accent rings
    ctx.strokeStyle = '#A8A29E'; ctx.lineWidth = 2;
    ctx.strokeRect(-16, -25, 32, 55);
    ctx.beginPath(); ctx.moveTo(-16, -5); ctx.lineTo(16, -5); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(-16, 12); ctx.lineTo(16, 12); ctx.stroke();
    // Arched windows
    ctx.fillStyle = '#1E293B';
    ctx.beginPath(); ctx.arc(-6, 2, 3, Math.PI, 0); ctx.rect(-9, 2, 6, 6); ctx.fill();
    ctx.beginPath(); ctx.arc(6, 2, 3, Math.PI, 0); ctx.rect(3, 2, 6, 6); ctx.fill();
    // Observation Balcony (360 viewing terrace)
    ctx.fillStyle = '#78716C';
    ctx.fillRect(-20, -32, 40, 7);
    ctx.strokeStyle = '#44403C'; ctx.lineWidth = 1.5;
    ctx.strokeRect(-20, -32, 40, 7);
    // Upper stone turret
    ctx.fillStyle = '#E7E5E4';
    ctx.fillRect(-13, -46, 26, 14);
    // Conical lead/copper Roof
    ctx.beginPath();
    ctx.moveTo(-18, -46); ctx.lineTo(18, -46); ctx.lineTo(0, -78); ctx.closePath();
    ctx.fillStyle = '#475569'; ctx.fill();
    ctx.strokeStyle = '#334155'; ctx.lineWidth = 2; ctx.stroke();
    // Golden Spire & Crescent
    ctx.fillStyle = '#F59E0B';
    ctx.fillRect(-1.5, -88, 3, 10);
    ctx.beginPath(); ctx.arc(0, -90, 4, 0.4 * Math.PI, 1.6 * Math.PI); ctx.stroke();
    // Title label
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Galata Kulesi', 0, 44);
    ctx.restore();

    // 5. Ayasofya & Sultanahmet (Historic Peninsula) at x: 670, y: 200
    ctx.save();
    ctx.translate(670, 200);
    // Monumental terracotta facade
    ctx.fillStyle = '#B45309';
    ctx.fillRect(-35, -15, 70, 45);
    // Central Grand Dome
    ctx.beginPath();
    ctx.arc(0, -15, 24, Math.PI, 0);
    ctx.fillStyle = '#78716C'; ctx.fill();
    ctx.strokeStyle = '#44403C'; ctx.lineWidth = 2; ctx.stroke();
    // Half-domes
    ctx.beginPath(); ctx.arc(-22, -10, 14, Math.PI, 0); ctx.fillStyle = '#A8A29E'; ctx.fill();
    ctx.beginPath(); ctx.arc(22, -10, 14, Math.PI, 0); ctx.fillStyle = '#A8A29E'; ctx.fill();
    // Dome Spire
    ctx.fillStyle = '#F59E0B';
    ctx.fillRect(-1, -44, 2, 8);
    // Four Soaring Pencil Minarets
    const minaretOffsets = [-44, -36, 36, 44];
    minaretOffsets.forEach(mx => {
      ctx.fillStyle = '#E7E5E4';
      ctx.fillRect(mx - 3, -48, 6, 78);
      // Minaret Balconies (Şerefe)
      ctx.fillStyle = '#78716C';
      ctx.fillRect(mx - 5, -28, 10, 4);
      ctx.fillRect(mx - 5, -42, 10, 4);
      // Conical pointed spire
      ctx.beginPath();
      ctx.moveTo(mx - 3, -48); ctx.lineTo(mx + 3, -48); ctx.lineTo(mx, -64); ctx.closePath();
      ctx.fillStyle = '#475569'; ctx.fill();
    });
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Ayasofya-i Kebir', 0, 42);
    ctx.restore();

    // 6. Kız Kulesi (Maiden's Tower) on its own islet at (740, 390)
    ctx.save();
    ctx.translate(740, 390);
    ctx.fillStyle = '#78716C';
    ctx.beginPath(); ctx.ellipse(0, 16, 26, 12, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#E7E5E4';
    ctx.fillRect(-12, -10, 24, 24);
    ctx.strokeStyle = '#A8A29E'; ctx.lineWidth = 1.5; ctx.strokeRect(-12, -10, 24, 24);
    ctx.fillStyle = '#D6D3D1'; ctx.fillRect(-8, -22, 16, 12);
    ctx.beginPath(); ctx.arc(0, -22, 8, Math.PI, 0); ctx.fillStyle = '#DC2626'; ctx.fill();
    // Sweeping Lighthouse Beam
    const ltAngle = this.starPulse * 1.2;
    const ltLen = 130;
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(0, -22);
    ctx.lineTo(Math.cos(ltAngle - 0.25) * ltLen, -22 + Math.sin(ltAngle - 0.25) * ltLen * 0.5);
    ctx.lineTo(Math.cos(ltAngle + 0.25) * ltLen, -22 + Math.sin(ltAngle + 0.25) * ltLen * 0.5);
    ctx.closePath();
    const ltGrad = ctx.createRadialGradient(0, -22, 4, 0, -22, ltLen);
    ltGrad.addColorStop(0, 'rgba(254, 240, 138, 0.8)');
    ltGrad.addColorStop(1, 'rgba(254, 240, 138, 0)');
    ctx.fillStyle = ltGrad;
    ctx.fill();
    ctx.restore();
    ctx.font = 'bold 8px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Kız Kulesi', 0, 34);
    ctx.restore();

    // 7. Taksim Nostaljik Kırmızı Tramvay on Rails
    ctx.save();
    const tramX = 180 + Math.sin(this.starPulse * 0.8) * 20;
    const tramY = 360;
    ctx.translate(tramX, tramY);
    ctx.strokeStyle = '#64748B'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(-45, 14); ctx.lineTo(45, 14); ctx.moveTo(-45, 18); ctx.lineTo(45, 18); ctx.stroke();
    ctx.fillStyle = '#DC2626'; ctx.fillRect(-18, -12, 36, 22);
    ctx.fillStyle = '#FEF08A'; ctx.fillRect(-18, 0, 36, 4);
    ctx.fillStyle = '#38BDF8';
    ctx.fillRect(-14, -8, 7, 7); ctx.fillRect(-4, -8, 8, 7); ctx.fillRect(7, -8, 7, 7);
    ctx.fillStyle = '#1E293B'; ctx.fillRect(-14, 10, 6, 4); ctx.fillRect(8, 10, 6, 4);
    ctx.strokeStyle = '#1E293B'; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.moveTo(0, -12); ctx.lineTo(4, -24); ctx.lineTo(12, -24); ctx.stroke();
    ctx.font = 'bold 7px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Nostaljik Tramvay', 0, 28);
    ctx.restore();

    // 8. Flying Seagulls (Martılar) in the sky
    ctx.strokeStyle = '#1E293B';
    ctx.lineWidth = 1.5;
    const gulls = [
      { x: 310, y: 120 }, { x: 340, y: 105 }, { x: 620, y: 110 }, { x: 580, y: 90 }
    ];
    gulls.forEach((g, idx) => {
      const wingBob = Math.sin(this.starPulse * 3 + idx) * 3;
      ctx.beginPath();
      ctx.moveTo(g.x - 7, g.y + wingBob);
      ctx.quadraticCurveTo(g.x - 3, g.y - 4, g.x, g.y);
      ctx.quadraticCurveTo(g.x + 3, g.y - 4, g.x + 7, g.y + wingBob);
      ctx.stroke();
    });

    ctx.restore();
  }

  /* ----------------------------------------------------
     2. TOKYO ENVIRONMENT (Neon, Skytree, Tokyo Kulesi, Torii)
     ---------------------------------------------------- */
  drawTokyoEnvironment(ctx) {
    ctx.save();
    // 1. Cyber Grid Flooring
    ctx.strokeStyle = 'rgba(6, 182, 212, 0.12)';
    ctx.lineWidth = 1;
    for (let x = 80; x < 1020; x += 45) {
      ctx.beginPath(); ctx.moveTo(x, 60); ctx.lineTo(x, 700); ctx.stroke();
    }
    for (let y = 60; y < 700; y += 45) {
      ctx.beginPath(); ctx.moveTo(80, y); ctx.lineTo(1020, y); ctx.stroke();
    }

    // 2. Tokyo Tower (Red and White Steel Truss Pyramid) at x: 250, y: 200
    ctx.save();
    ctx.translate(250, 200);
    // Red/White Truss Body
    ctx.lineWidth = 3;
    ctx.strokeStyle = '#EF4444';
    ctx.beginPath();
    ctx.moveTo(-28, 50); ctx.lineTo(-12, -10); ctx.lineTo(12, -10); ctx.lineTo(28, 50);
    ctx.stroke();
    // Lattice crosses
    ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(-24, 40); ctx.lineTo(24, 20); ctx.moveTo(24, 40); ctx.lineTo(-24, 20);
    ctx.stroke();
    // Observation Deck
    ctx.fillStyle = '#FFFFFF'; ctx.fillRect(-18, -10, 36, 12);
    ctx.fillStyle = '#EF4444'; ctx.fillRect(-14, -22, 28, 12);
    // Needle mast
    ctx.strokeStyle = '#EF4444'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(0, -22); ctx.lineTo(0, -68); ctx.stroke();
    // Top beacon light
    ctx.fillStyle = '#FEF08A';
    ctx.beginPath(); ctx.arc(0, -68, 4, 0, Math.PI * 2); ctx.fill();
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#E2E8F0'; ctx.textAlign = 'center';
    ctx.fillText('Tokyo Kulesi', 0, 64);
    ctx.restore();

    // 3. Tokyo Skytree (Futuristic Mast) at x: 720, y: 170
    ctx.save();
    ctx.translate(720, 170);
    ctx.strokeStyle = '#E2E8F0'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(-16, 60); ctx.lineTo(-6, -30); ctx.lineTo(6, -30); ctx.lineTo(16, 60); ctx.stroke();
    // Skytree observation spheres
    ctx.fillStyle = '#06B6D4';
    ctx.beginPath(); ctx.ellipse(0, 10, 20, 7, 0, 0, Math.PI * 2); ctx.fill();
    ctx.beginPath(); ctx.ellipse(0, -20, 14, 5, 0, 0, Math.PI * 2); ctx.fill();
    // Antenna & Pulsing Cyan Beacon
    ctx.strokeStyle = '#38BDF8'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(0, -30); ctx.lineTo(0, -80); ctx.stroke();
    const beaconAlpha = 0.5 + Math.sin(this.starPulse * 4) * 0.5;
    ctx.fillStyle = `rgba(34, 211, 238, ${beaconAlpha})`;
    ctx.beginPath(); ctx.arc(0, -80, 6, 0, Math.PI * 2); ctx.fill();
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#E2E8F0'; ctx.textAlign = 'center';
    ctx.fillText('Tokyo Skytree', 0, 74);
    ctx.restore();

    // 4. Shinto Torii Gate at x: 450, y: 460
    ctx.save();
    ctx.translate(450, 460);
    ctx.fillStyle = '#DC2626';
    // Twin vertical columns
    ctx.fillRect(-22, -26, 6, 36); ctx.fillRect(16, -26, 6, 36);
    // Main top crossbar (Kasagi)
    ctx.fillRect(-32, -34, 64, 7);
    ctx.fillStyle = '#0F172A'; ctx.fillRect(-34, -36, 68, 3);
    // Tie-beam (Nuki)
    ctx.fillStyle = '#DC2626'; ctx.fillRect(-25, -20, 50, 4);
    // Hanging glowing chōchin paper lanterns
    ctx.fillStyle = '#F59E0B';
    ctx.beginPath(); ctx.arc(-10, -12, 4, 0, Math.PI * 2); ctx.arc(10, -12, 4, 0, Math.PI * 2); ctx.fill();
    ctx.restore();

    // 5. Holographic Shibuya / Shinjuku Neon Billboards
    const neonSigns = [
      { x: 380, y: 220, text: '渋谷 NEON', color: '#06B6D4' },
      { x: 570, y: 220, text: 'ラーメン 🍜', color: '#F59E0B' },
      { x: 840, y: 340, text: 'ゲーム 🎮', color: '#EC4899' }
    ];
    neonSigns.forEach(s => {
      ctx.save();
      ctx.translate(s.x, s.y);
      ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
      ctx.roundRect(-36, -14, 72, 28, 6);
      ctx.fill();
      ctx.strokeStyle = s.color; ctx.lineWidth = 2;
      ctx.shadowColor = s.color; ctx.shadowBlur = 10;
      ctx.stroke();
      ctx.font = 'bold 10px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(s.text, 0, 0);
      ctx.restore();
    });

    // 6. Sakura Blossom Tree with drifting petals
    ctx.save();
    ctx.translate(850, 450);
    ctx.fillStyle = '#78350F'; ctx.fillRect(-4, 0, 8, 22);
    ctx.fillStyle = '#F472B6';
    ctx.beginPath();
    ctx.arc(-10, -12, 14, 0, Math.PI * 2);
    ctx.arc(10, -14, 16, 0, Math.PI * 2);
    ctx.arc(0, -22, 18, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    ctx.restore();
  }

  /* ----------------------------------------------------
     3. PARİS ENVIRONMENT (Eyfel, Zafer Takı, Sen Nehri, Louvre)
     ---------------------------------------------------- */
  drawParisEnvironment(ctx) {
    ctx.save();

    // 1. River Seine (Sen Nehri) with Deep Water Flow
    ctx.beginPath();
    ctx.moveTo(350, 60); ctx.bezierCurveTo(440, 220, 360, 440, 600, 720);
    ctx.lineTo(720, 720); ctx.bezierCurveTo(480, 440, 560, 220, 470, 60);
    ctx.closePath();
    const seineGrad = ctx.createLinearGradient(350, 60, 650, 720);
    seineGrad.addColorStop(0, '#0284C7');
    seineGrad.addColorStop(0.5, '#0369A1');
    seineGrad.addColorStop(1, '#075985');
    ctx.fillStyle = seineGrad;
    ctx.fill();

    // Seine Water Ripples
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
    ctx.lineWidth = 1.5;
    for (let r = 0; r < 5; r++) {
      const rx = 440 + Math.sin(this.starPulse + r * 1.4) * 30;
      const ry = 180 + r * 95;
      ctx.beginPath();
      ctx.arc(rx, ry, 20 + r * 3, 0.2 * Math.PI, 0.8 * Math.PI);
      ctx.stroke();
    }

    // Cruise Boat (Bateau-Mouche) on Seine
    const boatX = 490 + Math.sin(this.starPulse * 0.8) * 22;
    const boatY = 460 + Math.cos(this.starPulse * 0.8) * 18;
    ctx.save();
    ctx.translate(boatX, boatY);
    ctx.rotate(0.32);
    ctx.fillStyle = '#FFFFFF';
    ctx.beginPath();
    ctx.moveTo(-16, 5); ctx.lineTo(16, 5); ctx.lineTo(12, 13); ctx.lineTo(-12, 13); ctx.closePath();
    ctx.fill();
    ctx.fillStyle = '#0284C7';
    ctx.fillRect(-10, 1, 20, 4);
    ctx.fillStyle = '#FEF08A';
    ctx.fillRect(-7, 2, 3, 2); ctx.fillRect(-1, 2, 3, 2); ctx.fillRect(5, 2, 3, 2);
    ctx.restore();

    // 2. Arched Pont Neuf Stone Bridge
    ctx.fillStyle = '#E2E8F0';
    ctx.fillRect(400, 280, 110, 22);
    ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 2;
    ctx.strokeRect(400, 280, 110, 22);
    // Bridge Arch cutouts
    ctx.fillStyle = '#0369A1';
    ctx.beginPath(); ctx.arc(430, 302, 12, Math.PI, 0); ctx.arc(480, 302, 12, Math.PI, 0); ctx.fill();
    // Bridge balustrade details
    ctx.strokeStyle = '#64748B'; ctx.lineWidth = 1.2;
    for (let bx = 410; bx <= 500; bx += 10) {
      ctx.beginPath(); ctx.moveTo(bx, 280); ctx.lineTo(bx, 286); ctx.stroke();
    }

    // 3. 3D Parisian Haussmann Buildings (Living city quarters)
    const buildings = [
      { x: 330, y: 350, w: 32, h: 26 },
      { x: 375, y: 385, w: 28, h: 24 },
      { x: 740, y: 330, w: 34, h: 28 },
      { x: 785, y: 365, w: 30, h: 26 },
      { x: 670, y: 380, w: 26, h: 24 }
    ];
    buildings.forEach(b => {
      ctx.save();
      ctx.translate(b.x, b.y);
      // Drop Shadow
      ctx.fillStyle = 'rgba(0,0,0,0.3)';
      ctx.fillRect(-b.w / 2 + 2, b.h / 2, b.w, 4);
      // Stone facade
      ctx.fillStyle = '#E2E8F0';
      ctx.fillRect(-b.w / 2, -b.h / 2, b.w, b.h);
      ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 1;
      ctx.strokeRect(-b.w / 2, -b.h / 2, b.w, b.h);
      // Blue-grey zinc mansard roof
      ctx.beginPath();
      ctx.moveTo(-b.w / 2 - 2, -b.h / 2);
      ctx.lineTo(-b.w / 2 + 4, -b.h / 2 - 8);
      ctx.lineTo(b.w / 2 - 4, -b.h / 2 - 8);
      ctx.lineTo(b.w / 2 + 2, -b.h / 2);
      ctx.closePath();
      ctx.fillStyle = '#475569';
      ctx.fill();
      // Lit warm windows
      ctx.fillStyle = '#FEF08A';
      for (let wx = -b.w / 2 + 5; wx <= b.w / 2 - 8; wx += 8) {
        for (let wy = -b.h / 2 + 4; wy <= b.h / 2 - 6; wy += 8) {
          ctx.fillRect(wx, wy, 4, 5);
        }
      }
      ctx.restore();
    });

    // 4. French Formal Garden Parterres (Champs de Mars) & Fountain
    ctx.save();
    ctx.translate(290, 275);
    // Green hedge borders
    ctx.strokeStyle = '#15803D'; ctx.lineWidth = 3;
    ctx.strokeRect(-24, -16, 48, 32);
    ctx.fillStyle = '#14532D'; ctx.fillRect(-22, -14, 44, 28);
    // Center Fountain
    ctx.beginPath(); ctx.arc(0, 0, 7, 0, Math.PI * 2); ctx.fillStyle = '#38BDF8'; ctx.fill();
    ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5; ctx.stroke();
    // Fountain water spray
    const sprayH = 4 + Math.sin(this.starPulse * 4) * 2;
    ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
    ctx.beginPath(); ctx.arc(0, -sprayH, 2, 0, Math.PI * 2); ctx.fill();
    ctx.restore();

    // 5. Tour Eiffel (Eiffel Tower) with Rotating Golden Spotlight at x: 240, y: 200
    ctx.save();
    ctx.translate(240, 200);
    // Grand Ground Arch
    ctx.strokeStyle = '#78716C'; ctx.lineWidth = 3.5;
    ctx.beginPath(); ctx.moveTo(-32, 50); ctx.lineTo(-14, 10); ctx.lineTo(14, 10); ctx.lineTo(32, 50); ctx.stroke();
    ctx.beginPath(); ctx.arc(0, 50, 18, Math.PI, 0); ctx.stroke();
    // 1st and 2nd Level Decks
    ctx.fillStyle = '#57534E';
    ctx.fillRect(-22, 10, 44, 7);
    ctx.fillRect(-15, -15, 30, 6);
    // Middle tapered shaft
    ctx.beginPath(); ctx.moveTo(-13, 10); ctx.lineTo(-7, -15); ctx.lineTo(7, -15); ctx.lineTo(13, 10); ctx.stroke();
    // Top Spire
    ctx.beginPath(); ctx.moveTo(-6, -15); ctx.lineTo(0, -68); ctx.lineTo(6, -15); ctx.stroke();
    // Golden Twinkle Lights
    ctx.fillStyle = '#FDE047';
    for (let i = 0; i < 6; i++) {
      if (Math.sin(this.starPulse * 3 + i) > 0) {
        ctx.fillRect(-8 + (i % 3) * 8, 4 - i * 8, 2, 2);
      }
    }
    // Rotating Sweeping Beacon Spotlight Beam
    const beaconAngle = this.starPulse * 0.7;
    const beamLength = 160;
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(0, -68);
    ctx.lineTo(Math.cos(beaconAngle - 0.2) * beamLength, -68 + Math.sin(beaconAngle - 0.2) * beamLength * 0.4);
    ctx.lineTo(Math.cos(beaconAngle + 0.2) * beamLength, -68 + Math.sin(beaconAngle + 0.2) * beamLength * 0.4);
    ctx.closePath();
    const beamGrad = ctx.createRadialGradient(0, -68, 5, 0, -68, beamLength);
    beamGrad.addColorStop(0, 'rgba(253, 224, 71, 0.75)');
    beamGrad.addColorStop(1, 'rgba(253, 224, 71, 0)');
    ctx.fillStyle = beamGrad;
    ctx.fill();
    ctx.restore();

    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center';
    ctx.fillText('Eyfel Kulesi', 0, 65);
    ctx.restore();

    // 6. Arc de Triomphe (Zafer Takı) at x: 700, y: 210
    ctx.save();
    ctx.translate(700, 210);
    ctx.fillStyle = '#E7E5E4';
    ctx.fillRect(-26, -20, 52, 45);
    ctx.strokeStyle = '#A8A29E'; ctx.lineWidth = 2;
    ctx.strokeRect(-26, -20, 52, 45);
    // Center Arch Vault
    ctx.fillStyle = '#1E293B';
    ctx.beginPath();
    ctx.arc(0, 5, 12, Math.PI, 0); ctx.rect(-12, 5, 24, 20); ctx.fill();
    ctx.stroke();
    // Attic cornice frieze
    ctx.fillStyle = '#D6D3D1'; ctx.fillRect(-30, -28, 60, 8);
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center';
    ctx.fillText('Zafer Takı', 0, 40);
    ctx.restore();

    // 7. Louvre Glass Pyramid (Louvre Piramidi) at x: 460, y: 460
    ctx.save();
    ctx.translate(460, 460);
    ctx.beginPath();
    ctx.moveTo(-28, 20); ctx.lineTo(28, 20); ctx.lineTo(0, -18); ctx.closePath();
    ctx.fillStyle = 'rgba(217, 249, 157, 0.55)'; ctx.fill();
    ctx.strokeStyle = '#CA8A04'; ctx.lineWidth = 2; ctx.stroke();
    // Diamond facets
    ctx.beginPath();
    ctx.moveTo(-14, 20); ctx.lineTo(0, -18); ctx.lineTo(14, 20);
    ctx.moveTo(-28, 20); ctx.lineTo(0, 2); ctx.lineTo(28, 20);
    ctx.stroke();
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center';
    ctx.fillText('Louvre Piramidi', 0, 32);
    ctx.restore();

    ctx.restore();
  }

  /* ----------------------------------------------------
     4. NEW YORK ENVIRONMENT (Empire State, Özgürlük Heykeli, Köprü)
     ---------------------------------------------------- */
  drawNewYorkEnvironment(ctx) {
    ctx.save();

    // 1. Empire State Building at x: 260, y: 190
    ctx.save();
    ctx.translate(260, 190);
    // Stepped Art Deco Towers
    ctx.fillStyle = '#CBD5E1';
    ctx.fillRect(-22, 10, 44, 45);
    ctx.fillRect(-16, -15, 32, 25);
    ctx.fillRect(-10, -40, 20, 25);
    // Mooring Mast & Needle
    ctx.fillStyle = '#94A3B8'; ctx.fillRect(-4, -60, 8, 20);
    ctx.strokeStyle = '#EF4444'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(0, -60); ctx.lineTo(0, -82); ctx.stroke();
    // Glowing top beacon
    ctx.fillStyle = '#EF4444';
    ctx.beginPath(); ctx.arc(0, -82, 4, 0, Math.PI * 2); ctx.fill();
    // Office Windows
    ctx.fillStyle = '#FEF08A';
    for (let wy = -35; wy < 40; wy += 8) {
      ctx.fillRect(-7, wy, 3, 4);
      ctx.fillRect(4, wy, 3, 4);
    }
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Empire State', 0, 68);
    ctx.restore();

    // 2. Chrysler Building Spire at x: 420, y: 180
    ctx.save();
    ctx.translate(420, 180);
    ctx.fillStyle = '#94A3B8';
    ctx.fillRect(-14, 0, 28, 50);
    // Sunburst crown arcs
    ctx.fillStyle = '#E2E8F0';
    ctx.beginPath(); ctx.arc(0, 0, 12, Math.PI, 0); ctx.fill();
    ctx.beginPath(); ctx.arc(0, -10, 8, Math.PI, 0); ctx.fill();
    // Stainless Needle
    ctx.strokeStyle = '#E2E8F0'; ctx.lineWidth = 2.5;
    ctx.beginPath(); ctx.moveTo(0, -10); ctx.lineTo(0, -55); ctx.stroke();
    ctx.restore();

    // 3. Statue of Liberty (Özgürlük Heykeli) at x: 740, y: 200
    ctx.save();
    ctx.translate(740, 200);
    // Star Fort Pedestal
    ctx.fillStyle = '#78716C';
    ctx.fillRect(-20, 15, 40, 30);
    ctx.fillStyle = '#A8A29E';
    ctx.fillRect(-14, -5, 28, 20);
    // Copper Statue (Oxidized Green)
    ctx.fillStyle = '#34D399';
    // Draped Toga Robe
    ctx.beginPath();
    ctx.moveTo(-10, -5); ctx.lineTo(-14, 20); ctx.lineTo(14, 20); ctx.lineTo(10, -5); ctx.closePath();
    ctx.fill();
    // Head & 7-spike Crown
    ctx.beginPath(); ctx.arc(0, -12, 6, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#059669'; ctx.lineWidth = 1.5;
    for (let s = -3; s <= 3; s++) {
      ctx.beginPath(); ctx.moveTo(s * 2, -16); ctx.lineTo(s * 3.5, -22); ctx.stroke();
    }
    // Raised Right Arm & Golden Torch
    ctx.strokeStyle = '#34D399'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(6, -8); ctx.lineTo(16, -26); ctx.stroke();
    // Golden Flame Beacon
    ctx.fillStyle = '#F59E0B';
    ctx.beginPath(); ctx.ellipse(16, -29, 4, 6, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(251, 191, 36, 0.4)';
    ctx.beginPath(); ctx.arc(16, -29, 9, 0, Math.PI * 2); ctx.fill();

    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Özgürlük Heykeli', 0, 58);
    ctx.restore();

    // 4. Brooklyn Bridge at x: 530, y: 470
    ctx.save();
    ctx.translate(530, 470);
    // Gothic Twin Arches
    ctx.fillStyle = '#78716C';
    ctx.fillRect(-22, -26, 44, 38);
    ctx.fillStyle = '#FEF08A';
    ctx.beginPath();
    ctx.arc(-10, -8, 6, Math.PI, 0); ctx.rect(-16, -8, 12, 20); ctx.fill();
    ctx.beginPath();
    ctx.arc(10, -8, 6, Math.PI, 0); ctx.rect(4, -8, 12, 20); ctx.fill();
    // Suspension Cables
    ctx.strokeStyle = '#475569'; ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(-50, -10); ctx.quadraticCurveTo(-20, 4, 0, -26);
    ctx.quadraticCurveTo(20, 4, 50, -10);
    ctx.stroke();
    ctx.restore();

    // 5. Yellow New York Cabs
    const cabPos = (this.starPulse * 30) % 300;
    ctx.fillStyle = '#FACC15';
    ctx.fillRect( cabPos + 320, 680, 24, 12);
    ctx.fillStyle = '#1E293B';
    ctx.fillRect( cabPos + 323, 689, 5, 5);
    ctx.fillRect( cabPos + 336, 689, 5, 5);
    ctx.fillStyle = '#EF4444';
    ctx.fillRect( cabPos + 342, 682, 2, 4);

    ctx.restore();
  }

  /* ----------------------------------------------------
     AMBIENT WORLD DETAILS (Street Lamps, Light Cones, Trees, Foliage)
     ---------------------------------------------------- */
  drawAmbientWorldDetails(ctx, theme) {
    ctx.save();

    // 1. Street Lamps with Warm Glowing Cones along the track
    const lampCoords = [
      { x: 267, y: 560 }, { x: 533, y: 655 }, { x: 813, y: 615 }, { x: 990, y: 500 },
      { x: 878, y: 175 }, { x: 524, y: 145 }, { x: 241, y: 165 }, { x: 118, y: 295 }
    ];

    lampCoords.forEach(lamp => {
      // Warm ground light circle
      ctx.fillStyle = 'rgba(254, 240, 138, 0.18)';
      ctx.beginPath();
      ctx.ellipse(lamp.x, lamp.y + 6, 22, 10, 0, 0, Math.PI * 2);
      ctx.fill();

      // Lamp post & glowing head
      ctx.fillStyle = '#1E293B';
      ctx.fillRect(lamp.x - 1.5, lamp.y - 18, 3, 18);
      ctx.fillStyle = '#FEF08A';
      ctx.beginPath();
      ctx.arc(lamp.x, lamp.y - 19, 4, 0, Math.PI * 2);
      ctx.fill();
    });

    // 2. Isometric 3D Puffy Cartoon Trees (Fills empty spaces with greenery)
    const treeCoords = [
      { x: 270, y: 350, s: 1.1 }, { x: 310, y: 380, s: 0.9 }, { x: 280, y: 420, s: 1.2 },
      { x: 820, y: 280, s: 1.0 }, { x: 860, y: 310, s: 1.2 }, { x: 790, y: 340, s: 0.9 },
      { x: 410, y: 530, s: 1.1 }, { x: 670, y: 530, s: 1.0 }, { x: 490, y: 260, s: 1.2 },
      { x: 580, y: 260, s: 1.1 }, { x: 370, y: 280, s: 0.9 }, { x: 720, y: 270, s: 1.0 }
    ];

    treeCoords.forEach(t => {
      ctx.save();
      ctx.translate(t.x, t.y);
      ctx.scale(t.s, t.s);

      // Shadow
      ctx.fillStyle = 'rgba(15, 23, 42, 0.12)';
      ctx.beginPath();
      ctx.ellipse(0, 12, 16, 7, 0, 0, Math.PI * 2);
      ctx.fill();

      // Trunk
      ctx.fillStyle = '#78350F';
      ctx.fillRect(-3, 0, 6, 12);

      // Foliage Layers (Puffy Cartoon Tree)
      ctx.fillStyle = '#15803D';
      ctx.beginPath();
      ctx.arc(0, -6, 16, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = '#22C55E';
      ctx.beginPath();
      ctx.arc(-2, -10, 13, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = '#86EFAC';
      ctx.beginPath();
      ctx.arc(-5, -14, 6, 0, Math.PI * 2);
      ctx.fill();

      ctx.restore();
    });

    ctx.restore();
  }

  drawPathways(ctx) {
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.istanbul;
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Connector drop shadow
    ctx.beginPath();
    for (let i = 0; i < this.tileCoords.length; i++) {
      const p1 = this.tileCoords[i];
      const p2 = this.tileCoords[(i + 1) % this.tileCoords.length];
      if (i === 0) ctx.moveTo(p1.x, p1.y + 12);
      ctx.lineTo(p2.x, p2.y + 12);
    }
    ctx.strokeStyle = 'rgba(15, 23, 42, 0.12)';
    ctx.lineWidth = 26;
    ctx.stroke();

    // Road Base
    ctx.beginPath();
    for (let i = 0; i < this.tileCoords.length; i++) {
      const p1 = this.tileCoords[i];
      const p2 = this.tileCoords[(i + 1) % this.tileCoords.length];
      if (i === 0) ctx.moveTo(p1.x, p1.y);
      ctx.lineTo(p2.x, p2.y);
    }
    ctx.strokeStyle = theme.roadBase;
    ctx.lineWidth = 22;
    ctx.stroke();

    // Dotted center line
    ctx.strokeStyle = theme.roadBorder;
    ctx.lineWidth = 3.5;
    ctx.setLineDash([8, 12]);
    ctx.stroke();
    ctx.setLineDash([]);

    // Forward Direction Chevrons (Kutucuklar üzerinde gidişat akışı)
    for (let i = 0; i < this.tileCoords.length; i++) {
      const p1 = this.tileCoords[i];
      const p2 = this.tileCoords[(i + 1) % this.tileCoords.length];
      const mx = (p1.x + p2.x) / 2;
      const my = (p1.y + p2.y) / 2;
      const angle = Math.atan2(p2.y - p1.y, p2.x - p1.x);

      // Gentle forward pulse shift
      const pulseShift = (Math.sin(this.starPulse * 2.5 + i * 0.45) + 1) * 0.5;

      ctx.save();
      ctx.translate(mx, my);
      ctx.rotate(angle);
      ctx.fillStyle = theme.roadBorder;
      ctx.globalAlpha = 0.45 + pulseShift * 0.45;

      // Draw forward-pointing chevron
      ctx.beginPath();
      ctx.moveTo(-6, -6);
      ctx.lineTo(4, 0);
      ctx.lineTo(-6, 6);
      ctx.lineTo(-3, 0);
      ctx.closePath();
      ctx.fill();
      ctx.restore();
    }

    ctx.restore();
  }

  /* ----------------------------------------------------
     STEP TRAJECTORY PREVIEW (Gidişat Yolu ve Hedef Karosu)
     ---------------------------------------------------- */
  drawStepTrajectory(ctx) {
    const roomState = window.CURRENT_ROOM_STATE;
    if (!roomState || !roomState.players || roomState.players.length === 0) return;

    const activePlayer = roomState.players[roomState.turnIndex];
    if (!activePlayer) return;

    const startIdx = activePlayer.position;
    const previewSteps = 5;

    ctx.save();
    // Glowing connecting line along future steps
    ctx.setLineDash([6, 8]);
    ctx.lineDashOffset = -this.starPulse * 24;
    ctx.strokeStyle = 'rgba(251, 191, 36, 0.75)';
    ctx.lineWidth = 3.5;
    ctx.beginPath();

    const startPos = this.tileCoords[startIdx];
    if (startPos) {
      ctx.moveTo(startPos.x, startPos.y);

      for (let step = 1; step <= previewSteps; step++) {
        const nextIdx = (startIdx + step) % this.tileCoords.length;
        const nextPos = this.tileCoords[nextIdx];
        ctx.lineTo(nextPos.x, nextPos.y);
      }
      ctx.stroke();
    }
    ctx.setLineDash([]);

    // Floating step progression tags (+1, +2, +3... Target)
    for (let step = 1; step <= previewSteps; step++) {
      const nextIdx = (startIdx + step) % this.tileCoords.length;
      const p = this.tileCoords[nextIdx];
      if (!p) continue;
      const isTarget = (step === previewSteps);

      ctx.save();
      ctx.translate(p.x, p.y - 28);
      if (isTarget) {
        const bounce = Math.sin(this.starPulse * 3.5) * 4;
        ctx.translate(0, bounce);
        ctx.beginPath();
        ctx.arc(0, 0, 14, 0, Math.PI * 2);
        ctx.fillStyle = '#EF4444';
        ctx.shadowColor = '#EF4444';
        ctx.shadowBlur = 10;
        ctx.fill();
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 10px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('🎯', 0, 0);
      } else {
        ctx.beginPath();
        ctx.arc(0, 0, 9, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
        ctx.fill();
        ctx.strokeStyle = '#FBBF24';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.fillStyle = '#FBBF24';
        ctx.font = 'bold 8px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(`+${step}`, 0, 0);
      }
      ctx.restore();
    }

    ctx.restore();
  }

  /* ----------------------------------------------------
     DRAMATIC TURN SPOTLIGHT (Sıranın Kimde Olduğu Apaydın)
     ---------------------------------------------------- */
  drawTurnSpotlight(ctx) {
    const roomState = window.CURRENT_ROOM_STATE;
    if (!roomState || !roomState.players || roomState.players.length === 0) return;

    const activePlayer = roomState.players[roomState.turnIndex];
    if (!activePlayer) return;

    const anim = this.playerHopState && this.playerHopState[activePlayer.id];
    const px = anim ? anim.displayX : (this.tileCoords[activePlayer.position] ? this.tileCoords[activePlayer.position].x : 550);
    const py = anim ? (anim.displayY + anim.hopArcY) : (this.tileCoords[activePlayer.position] ? this.tileCoords[activePlayer.position].y : 380);

    ctx.save();

    // 1. Conical Volumetric Spotlight Beam from Sky
    const spotGrad = ctx.createLinearGradient(px, 0, px, py + 15);
    spotGrad.addColorStop(0, 'rgba(251, 191, 36, 0.03)');
    spotGrad.addColorStop(0.5, 'rgba(251, 191, 36, 0.12)');
    spotGrad.addColorStop(1, 'rgba(251, 191, 36, 0.32)');

    ctx.beginPath();
    ctx.moveTo(px - 18, 0);
    ctx.lineTo(px + 18, 0);
    ctx.lineTo(px + 48, py + 14);
    ctx.lineTo(px - 48, py + 14);
    ctx.closePath();
    ctx.fillStyle = spotGrad;
    ctx.fill();

    // 2. Pulsing Golden Halo Ring on the Floor
    const haloRadius = 26 + Math.sin(this.starPulse * 3.5) * 6;
    ctx.beginPath();
    ctx.ellipse(px, py + 10, haloRadius, haloRadius * 0.45, 0, 0, Math.PI * 2);
    ctx.strokeStyle = '#FBBF24';
    ctx.lineWidth = 3;
    ctx.shadowColor = '#FBBF24';
    ctx.shadowBlur = 14;
    ctx.stroke();

    // 3. Elegant Floating 3D Pointer Diamond (Unobtrusive & Never collides with name tags)
    const isMe = (window.GameClient && window.GameClient.player && window.GameClient.player.sessionId === activePlayer.sessionId);
    const bob = Math.sin(this.starPulse * 4) * 4;
    ctx.translate(px, py - 64 + bob);

    // Glowing 3D Diamond Indicator
    const diamondColor = isMe ? '#10B981' : '#F59E0B';
    const diamondBorder = isMe ? '#6EE7B7' : '#FDE047';

    ctx.save();
    ctx.shadowColor = diamondColor;
    ctx.shadowBlur = 12;

    // Diamond shape
    ctx.beginPath();
    ctx.moveTo(0, -9);
    ctx.lineTo(8, 0);
    ctx.lineTo(0, 9);
    ctx.lineTo(-8, 0);
    ctx.closePath();
    ctx.fillStyle = diamondColor;
    ctx.fill();
    ctx.strokeStyle = diamondBorder;
    ctx.lineWidth = 2;
    ctx.stroke();

    // Diamond facet highlight
    ctx.beginPath();
    ctx.moveTo(0, -9);
    ctx.lineTo(8, 0);
    ctx.lineTo(0, 0);
    ctx.closePath();
    ctx.fillStyle = 'rgba(255, 255, 255, 0.45)';
    ctx.fill();

    // Downward pointer tip
    ctx.beginPath();
    ctx.moveTo(-4, 7);
    ctx.lineTo(4, 7);
    ctx.lineTo(0, 13);
    ctx.closePath();
    ctx.fillStyle = diamondBorder;
    ctx.fill();
    ctx.restore();

    ctx.restore();
  }

  drawPlatformTile(ctx, index, tile, pos, isStarTile, isSelected, hasTrap) {
    const w = 68;
    const h = 50;
    const x = pos.x - w / 2;
    const y = pos.y - h / 2;

    const isHazard = (tile.type === 'trap' || tile.type === 'tax' || tile.type === 'jail' || tile.type === 'slip' || tile.type === 'freeze');
    const isBank = (tile.type === 'bank');
    const isStart = (tile.type === 'start');

    ctx.save();

    // 1. Selected Tile Pulsing Ring
    if (isSelected) {
      ctx.beginPath();
      ctx.roundRect(x - 6, y - 6, w + 12, h + 12, 20);
      ctx.strokeStyle = '#38BDF8';
      ctx.lineWidth = 3.5;
      ctx.stroke();
    }

    // 2. Star Pedestal Volumetric Aura
    if (isStarTile) {
      const pulseSize = 14 + Math.sin(this.starPulse * 1.5) * 6;
      ctx.beginPath();
      ctx.ellipse(pos.x, pos.y + 6, (w / 2) + pulseSize, (h / 2) + pulseSize * 0.7, 0, 0, Math.PI * 2);
      const starGrad = ctx.createRadialGradient(pos.x, pos.y, 8, pos.x, pos.y, (w / 2) + pulseSize);
      starGrad.addColorStop(0, 'rgba(251, 191, 36, 0.85)');
      starGrad.addColorStop(0.5, 'rgba(245, 158, 11, 0.35)');
      starGrad.addColorStop(1, 'rgba(245, 158, 11, 0)');
      ctx.fillStyle = starGrad;
      ctx.fill();
    }

    // 3. Hazard Tile Danger Aura
    if (isHazard) {
      const hazardPulse = 6 + Math.sin(this.starPulse * 2.5) * 4;
      ctx.beginPath();
      ctx.ellipse(pos.x, pos.y + 8, (w / 2) + hazardPulse, (h / 2) + hazardPulse * 0.6, 0, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(239, 68, 68, 0.16)';
      ctx.fill();
    }

    // 4. Soft Ground Shadow
    ctx.beginPath();
    ctx.ellipse(pos.x, pos.y + (h / 2) + 6, w * 0.48, 10, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(15, 23, 42, 0.22)';
    ctx.fill();

    // 5. 3D Extruded Bevel Base (Side Skirt)
    ctx.beginPath();
    ctx.roundRect(x, y + 6, w, h, 14);
    if (isHazard) {
      ctx.fillStyle = '#1A0C0E';
    } else if (isBank) {
      ctx.fillStyle = '#064E3B';
    } else {
      ctx.fillStyle = this.darkenColor(tile.color || '#3B82F6', 0.4);
    }
    ctx.fill();

    // 6. Top Island Platform Surface
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, 14);
    if (isHazard) {
      const lavaGrad = ctx.createLinearGradient(x, y, x, y + h);
      lavaGrad.addColorStop(0, '#261115');
      lavaGrad.addColorStop(0.6, '#38161D');
      lavaGrad.addColorStop(1, '#1A0A0D');
      ctx.fillStyle = lavaGrad;
    } else if (isBank) {
      const bankGrad = ctx.createLinearGradient(x, y, x, y + h);
      bankGrad.addColorStop(0, '#059669');
      bankGrad.addColorStop(1, '#047857');
      ctx.fillStyle = bankGrad;
    } else {
      const surfaceGrad = ctx.createLinearGradient(x, y, x, y + h);
      surfaceGrad.addColorStop(0, tile.color || '#3B82F6');
      surfaceGrad.addColorStop(1, this.darkenColor(tile.color || '#3B82F6', 0.18));
      ctx.fillStyle = surfaceGrad;
    }
    ctx.fill();

    // 7. Bevel Border Stroke
    if (isHazard) {
      ctx.strokeStyle = '#EF4444';
      ctx.lineWidth = 2.5;
    } else if (isStarTile) {
      ctx.strokeStyle = '#FDE047';
      ctx.lineWidth = 3;
    } else {
      ctx.strokeStyle = '#FFFFFF';
      ctx.lineWidth = 2.2;
    }
    ctx.stroke();

    // 8. Hazard Visual Details (Glowing Molten Lava Cracks & Hazard Stripes)
    if (isHazard) {
      ctx.save();
      // Glowing lava crack veins
      ctx.strokeStyle = '#F97316';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(x + 10, y + 14);
      ctx.lineTo(x + 24, y + 26);
      ctx.lineTo(x + 32, y + 18);
      ctx.lineTo(x + 52, y + 36);
      ctx.stroke();

      ctx.strokeStyle = '#FEF08A';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(x + 12, y + 15);
      ctx.lineTo(x + 24, y + 26);
      ctx.stroke();

      // Caution corner tags
      ctx.fillStyle = '#EF4444';
      ctx.font = 'bold 8px sans-serif';
      ctx.fillText('⚠️', x + w - 12, y + 12);
      ctx.restore();
    }

    // 9. Center Emblem Badge
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, 14, 0, Math.PI * 2);
    ctx.fillStyle = isHazard ? '#1E1B2E' : '#FFFFFF';
    ctx.fill();
    ctx.strokeStyle = isHazard ? '#EF4444' : (isBank ? '#F59E0B' : this.darkenColor(tile.color || '#3B82F6', 0.2));
    ctx.lineWidth = 2;
    ctx.stroke();

    // Center Icon
    let iconSymbol = '🪙';
    if (tile.type === 'start') iconSymbol = '🏁';
    else if (tile.type === 'star') iconSymbol = '⭐';
    else if (tile.type === 'bank') iconSymbol = '🏦';
    else if (tile.type === 'trap') iconSymbol = '💥';
    else if (tile.type === 'mystery') iconSymbol = '🎁';
    else if (tile.type === 'shop') iconSymbol = '🛍️';
    else if (tile.type === 'warp') iconSymbol = '🌀';
    else if (tile.type === 'slip') iconSymbol = '🍌';
    else if (tile.type === 'duel') iconSymbol = '⚔️';
    else if (tile.type === 'jail') iconSymbol = '⛓️';
    else if (tile.type === 'tax') iconSymbol = '💸';
    else if (tile.type === 'freeze') iconSymbol = '🧊';
    else if (tile.type === 'reverse') iconSymbol = '🔄';

    ctx.font = 'bold 13px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(iconSymbol, pos.x, pos.y + 1);

    // 10. Tile Number Badge
    ctx.beginPath();
    ctx.arc(x + 10, y + 8, 8, 0, Math.PI * 2);
    ctx.fillStyle = isHazard ? '#EF4444' : '#1E293B';
    ctx.fill();
    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 8px sans-serif';
    ctx.fillText(index.toString(), x + 10, y + 9);

    // 11. Floating Golden Star Flag
    if (isStarTile) {
      const starBob = Math.sin(this.starPulse * 1.5) * 8;
      ctx.save();
      ctx.translate(pos.x, y - 32 + starBob);
      ctx.font = '32px sans-serif';
      ctx.textAlign = 'center';
      ctx.shadowColor = '#F59E0B';
      ctx.shadowBlur = 14;
      ctx.fillText('⭐', 0, 0);
      ctx.restore();
    }

    // 12. Trap Marker
    if (hasTrap) {
      ctx.save();
      ctx.translate(pos.x, y - 16);
      ctx.font = '22px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('🍌', 0, 0);
      ctx.restore();
    }

    ctx.restore();
  }

  drawLandmark(ctx) {
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.tropics;
    const lm = theme.landmark;
    ctx.save();
    ctx.translate(lm.x, lm.y);

    // Landmark Shadow
    ctx.beginPath();
    ctx.ellipse(0, 16, 28, 12, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(15, 23, 42, 0.15)';
    ctx.fill();

    // Bobbing Landmark icon
    const bob = Math.sin(this.starPulse) * 4;
    ctx.font = '38px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(lm.icon, 0, bob);

    // Interactive Callout Tag
    ctx.font = 'bold 10px sans-serif';
    ctx.fillStyle = 'rgba(15, 23, 42, 0.8)';
    ctx.fillText(lm.name, 0, 32);

    ctx.restore();
  }

  drawParticles(ctx) {
    for (let i = this.particles.length - 1; i >= 0; i--) {
      const p = this.particles[i];
      p.x += p.vx;
      p.y += p.vy;
      p.life -= 0.035;

      if (p.life <= 0) {
        this.particles.splice(i, 1);
        continue;
      }

      ctx.beginPath();
      ctx.arc(p.x, p.y, 4 * p.life, 0, Math.PI * 2);
      ctx.fillStyle = p.color;
      ctx.globalAlpha = p.life;
      ctx.fill();
      ctx.globalAlpha = 1.0;
    }
  }

  /* --------------------------------------------------------------------------
     MONOPOLY-STYLE TILE-BY-TILE HOPPING & CHIBI CHARACTER TOKEN ENGINE
     -------------------------------------------------------------------------- */
  drawPlayerTokens(ctx, players) {
    if (!this.playerHopState) this.playerHopState = {};

    // 1. Group players by target/current tile position for cluster spacing
    const positionGroups = {};
    players.forEach(p => {
      positionGroups[p.position] = positionGroups[p.position] || [];
      positionGroups[p.position].push(p);
    });

    const tokensToRender = [];

    players.forEach((player) => {
      const tilePos = this.tileCoords[player.position] || { x: 110, y: 620 };
      const group = positionGroups[player.position];
      const count = group.length;
      const indexInGroup = group.indexOf(player);

      let clusterDx = 0;
      let clusterDy = 0;
      if (count === 2) {
        clusterDx = (indexInGroup === 0 ? -24 : 24);
      } else if (count >= 3) {
        const angle = (indexInGroup / count) * Math.PI * 2 - Math.PI / 2;
        clusterDx = Math.cos(angle) * 32;
        clusterDy = Math.sin(angle) * 22;
      }

      // Initialize state if first time seeing player
      if (!this.playerHopState[player.id]) {
        this.playerHopState[player.id] = {
          currentTile: player.position,
          targetTile: player.position,
          lastServerPos: player.position,
          hopQueue: [],
          hopProgress: 0,
          displayX: tilePos.x + clusterDx,
          displayY: tilePos.y + clusterDy,
          hopArcY: 0,
          scaleX: 1,
          scaleY: 1,
          isHopping: false
        };
      }

      const pState = this.playerHopState[player.id];

      // Detect server position change -> Enqueue intermediate tiles for Monopoly hop!
      if (player.position !== pState.lastServerPos) {
        const from = pState.currentTile;
        const to = player.position;
        pState.lastServerPos = player.position;

        if (from !== to) {
          const forwardSteps = (to - from + 32) % 32;
          const backwardSteps = (from - to + 32) % 32;
          const isReverse = !!player.reverseNext || (backwardSteps < forwardSteps && backwardSteps <= 6);

          if (isReverse) {
            for (let s = 1; s <= backwardSteps; s++) {
              pState.hopQueue.push((from - s + 32) % 32);
            }
          } else {
            for (let s = 1; s <= forwardSteps; s++) {
              pState.hopQueue.push((from + s) % 32);
            }
          }
          pState.isHopping = true;
        }
      }

      // Animate Hopping or Idle Breathing
      if (pState.hopQueue.length > 0) {
        const targetTile = pState.hopQueue[0];
        const fromPos = this.tileCoords[pState.currentTile] || { x: 110, y: 620 };
        const toPos = this.tileCoords[targetTile] || { x: 110, y: 620 };

        // Advance hop progress smoothly (accelerated by turbo speed & bot multiplier)
        const speedMultiplier = (window.GAME_SPEED || 1) * (player.isBot ? 1.35 : 1);
        pState.hopProgress += 0.042 * speedMultiplier;
        const p = Math.min(1, pState.hopProgress);

        // Linear ground position interpolation
        pState.displayX = fromPos.x + (toPos.x - fromPos.x) * p;
        pState.displayY = fromPos.y + (toPos.y - fromPos.y) * p;

        // Parabolic vertical jump arc (height = 32px)
        pState.hopArcY = -Math.sin(p * Math.PI) * 32;

        // Squash & Stretch physics
        if (p < 0.2) {
          const sub = p / 0.2;
          pState.scaleX = 1 + 0.15 * (1 - sub);
          pState.scaleY = 0.85 + 0.15 * sub;
        } else if (p < 0.8) {
          pState.scaleX = 0.88;
          pState.scaleY = 1.18;
        } else {
          const sub = (p - 0.8) / 0.2;
          pState.scaleX = 0.88 + 0.32 * sub;
          pState.scaleY = 1.18 - 0.38 * sub;
        }

        // Check if step completed
        if (pState.hopProgress >= 1) {
          pState.currentTile = pState.hopQueue.shift();
          pState.hopProgress = 0;
          pState.hopArcY = 0;
          pState.scaleX = 1;
          pState.scaleY = 1;

          // Sound effect on each hop landing
          if (window.AudioPlayer && window.AudioPlayer.playStep) {
            window.AudioPlayer.playStep();
          }

          // Emit dust puff particles at landing tile
          for (let d = 0; d < 4; d++) {
            this.particles.push({
              x: toPos.x + (Math.random() - 0.5) * 18,
              y: toPos.y + 12 + (Math.random() - 0.5) * 6,
              vx: (Math.random() - 0.5) * 2.2,
              vy: -Math.random() * 1.8 - 0.4,
              size: Math.random() * 3 + 2,
              color: 'rgba(226, 232, 240, 0.85)',
              life: 1,
              decay: 0.05
            });
          }

          // Destination reached!
          if (pState.hopQueue.length === 0) {
            pState.isHopping = false;
            // Celebratory star particles burst on final arrival
            for (let s = 0; s < 10; s++) {
              const ang = Math.random() * Math.PI * 2;
              const spd = Math.random() * 3.5 + 2;
              this.particles.push({
                x: toPos.x,
                y: toPos.y - 12,
                vx: Math.cos(ang) * spd,
                vy: Math.sin(ang) * spd - 2,
                size: Math.random() * 4 + 2,
                color: ['#FBBF24', '#38BDF8', '#F472B6', '#10B981'][Math.floor(Math.random() * 4)],
                life: 1,
                decay: 0.035
              });
            }
          }
        }
      } else {
        // Idle state: smooth slide to cluster offset & gentle breathing bob
        const targetX = tilePos.x + clusterDx;
        const targetY = tilePos.y + clusterDy;
        pState.displayX += (targetX - pState.displayX) * 0.2;
        pState.displayY += (targetY - pState.displayY) * 0.2;

        const idleBob = Math.sin(this.starPulse * 2.5 + player.name.charCodeAt(0)) * 1.8;
        pState.hopArcY = idleBob;
        pState.scaleX = 1;
        pState.scaleY = 1;
      }

      tokensToRender.push({
        player,
        floorX: pState.displayX,
        floorY: pState.displayY,
        px: pState.displayX,
        py: pState.displayY + pState.hopArcY,
        scaleX: pState.scaleX,
        scaleY: pState.scaleY,
        hopArcY: pState.hopArcY,
        clusterCount: count
      });
    });

    // 3. DEPTH SORTING (Y-Sort): Render tokens from top to bottom
    tokensToRender.sort((a, b) => a.floorY - b.floorY);

    // 4. Render each Chibi Token cleanly
    tokensToRender.forEach(item => {
      this.renderMinifigToken(
        ctx,
        item.player,
        item.px,
        item.py,
        item.floorY,
        item.clusterCount > 1,
        item.scaleX,
        item.scaleY,
        item.hopArcY
      );
    });
  }

  /* --------------------------------------------------------------------------
     PREMIUM 3D CHIBI CHARACTER TOKEN RENDERER
     -------------------------------------------------------------------------- */
  renderMinifigToken(ctx, player, px, py, floorY, isClustered, scaleX = 1, scaleY = 1, hopArcY = 0) {
    const isCurrent = window.CURRENT_ROOM_STATE && 
                      window.CURRENT_ROOM_STATE.players[window.CURRENT_ROOM_STATE.turnIndex] &&
                      window.CURRENT_ROOM_STATE.players[window.CURRENT_ROOM_STATE.turnIndex].id === player.id;

    ctx.save();

    // 1. Dynamic Ground Shadow (Stays on floor, shrinks & fades as token hops higher)
    const jumpHeight = Math.max(0, -hopArcY);
    const shadowScale = Math.max(0.35, 1 - (jumpHeight / 50));
    const shadowAlpha = Math.max(0.08, 0.28 * (1 - (jumpHeight / 45)));

    ctx.beginPath();
    ctx.ellipse(px, floorY + 8, 18 * shadowScale, 9 * shadowScale, 0, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(15, 23, 42, ${shadowAlpha})`;
    ctx.fill();

    // 2. Base Colored Ring on Floor
    ctx.beginPath();
    ctx.ellipse(px, floorY + 7, 15 * shadowScale, 7 * shadowScale, 0, 0, Math.PI * 2);
    ctx.strokeStyle = player.color || '#3B82F6';
    ctx.lineWidth = 2.5 * shadowScale;
    ctx.stroke();

    // Apply Token Squash & Stretch Transform
    ctx.translate(px, py);
    ctx.scale(scaleX, scaleY);

    // 3. Shield Bubble if active
    if (player.shieldActive > 0) {
      ctx.beginPath();
      ctx.arc(0, -18, 28, 0, Math.PI * 2);
      ctx.strokeStyle = '#38BDF8';
      ctx.lineWidth = 2.5;
      ctx.fillStyle = 'rgba(56, 189, 248, 0.2)';
      ctx.fill();
      ctx.stroke();
    }

    // 3.1 Ghost Cloak Ethereal Aura if active
    if (player.ghostCloakActive > 0) {
      ctx.save();
      ctx.beginPath();
      ctx.arc(0, -18, 30, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(6, 182, 212, 0.25)';
      ctx.shadowColor = '#22D3EE';
      ctx.shadowBlur = 12;
      ctx.fill();
      ctx.strokeStyle = '#67E8F9';
      ctx.lineWidth = 2;
      ctx.setLineDash([4, 3]);
      ctx.stroke();
      ctx.restore();
    }

    // 4. Ice Block if frozen
    if (player.frozenTurns > 0) {
      ctx.beginPath();
      ctx.roundRect(-20, -42, 40, 48, 8);
      ctx.fillStyle = 'rgba(186, 230, 253, 0.55)';
      ctx.strokeStyle = '#0284C7';
      ctx.lineWidth = 2;
      ctx.fill();
      ctx.stroke();
    }

    const avatarId = player.avatar || 'builder';
    const mainColor = player.color || '#3B82F6';

    // 5. Draw Archetype-Specific Chibi Silhouette & Features
    if (avatarId === 'dino') {
      // --- CUTE DINO CHIBI ---
      // Dorsal crest spikes
      ctx.fillStyle = '#F59E0B';
      ctx.beginPath(); ctx.moveTo(-6, -34); ctx.lineTo(-12, -40); ctx.lineTo(-2, -37); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(0, -38); ctx.lineTo(0, -46); ctx.lineTo(6, -38); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(6, -34); ctx.lineTo(12, -40); ctx.lineTo(2, -37); ctx.closePath(); ctx.fill();

      // Green body & chubby head
      ctx.beginPath(); ctx.roundRect(-14, -20, 28, 22, 6); ctx.fillStyle = '#059669'; ctx.fill();
      ctx.beginPath(); ctx.roundRect(-13, -37, 26, 20, 9); ctx.fillStyle = '#10B981'; ctx.fill();
      ctx.strokeStyle = '#047857'; ctx.lineWidth = 1.5; ctx.stroke();

      // Light belly
      ctx.beginPath(); ctx.ellipse(0, -11, 7, 7, 0, 0, Math.PI * 2); ctx.fillStyle = '#A7F3D0'; ctx.fill();

      // Cute snout
      ctx.beginPath(); ctx.ellipse(0, -26, 8, 5, 0, 0, Math.PI * 2); ctx.fillStyle = '#34D399'; ctx.fill();
      ctx.fillStyle = '#065F46';
      ctx.beginPath(); ctx.arc(-2.5, -26.5, 1, 0, Math.PI * 2); ctx.arc(2.5, -26.5, 1, 0, Math.PI * 2); ctx.fill();

      // Big anime eyes
      ctx.fillStyle = '#064E3B';
      ctx.beginPath(); ctx.arc(-5.5, -31, 2.5, 0, Math.PI * 2); ctx.arc(5.5, -31, 2.5, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-6.2, -32, 1, 0, Math.PI * 2); ctx.arc(4.8, -32, 1, 0, Math.PI * 2); ctx.fill();

      // Rosy cheeks
      ctx.fillStyle = '#F472B6';
      ctx.beginPath(); ctx.ellipse(-9, -27, 2.5, 1.3, 0, 0, Math.PI * 2); ctx.ellipse(9, -27, 2.5, 1.3, 0, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'cat') {
      // --- SIBER NEKO (CAT) CHIBI ---
      // Curled kitty tail behind
      ctx.strokeStyle = mainColor; ctx.lineWidth = 3.5; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(10, -12); ctx.quadraticCurveTo(22, -18, 18, -30); ctx.stroke();

      // Kitty ears with pink inner fluff
      ctx.fillStyle = mainColor;
      ctx.beginPath(); ctx.moveTo(-11, -33); ctx.lineTo(-14, -46); ctx.lineTo(-4, -36); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(11, -33); ctx.lineTo(14, -46); ctx.lineTo(4, -36); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#FDF2F8';
      ctx.beginPath(); ctx.moveTo(-10, -34); ctx.lineTo(-12, -42); ctx.lineTo(-5, -36); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(10, -34); ctx.lineTo(12, -42); ctx.lineTo(5, -36); ctx.closePath(); ctx.fill();

      // Body & chubby face
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 21, 6); ctx.fillStyle = mainColor; ctx.fill();
      ctx.beginPath(); ctx.roundRect(-12, -36, 24, 19, 8); ctx.fillStyle = '#F472B6'; ctx.fill();
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5; ctx.stroke();

      // Cyber visor band
      ctx.fillStyle = '#0F172A'; ctx.fillRect(-12, -34, 24, 4);
      ctx.fillStyle = '#06B6D4'; ctx.fillRect(-10, -33, 20, 2);

      // Cute big cat eyes
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.ellipse(-5, -27, 2.4, 2.8, 0, 0, Math.PI * 2); ctx.ellipse(5, -27, 2.4, 2.8, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-5.8, -28, 1, 0, Math.PI * 2); ctx.arc(4.2, -28, 1, 0, Math.PI * 2); ctx.fill();

      // Cute nose & whiskers
      ctx.fillStyle = '#9D174D'; ctx.beginPath(); ctx.moveTo(0, -24.5); ctx.lineTo(-1.5, -23); ctx.lineTo(1.5, -23); ctx.closePath(); ctx.fill();
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(-9, -24); ctx.lineTo(-15, -25); ctx.moveTo(-9, -22); ctx.lineTo(-15, -21); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(9, -24); ctx.lineTo(15, -25); ctx.moveTo(9, -22); ctx.lineTo(15, -21); ctx.stroke();

      // Gold collar bell
      ctx.fillStyle = '#FBBF24'; ctx.beginPath(); ctx.arc(0, -18, 3, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'astro') {
      // --- SPACE EXPLORER CHIBI ---
      // Spacesuit torso
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 21, 5); ctx.fillStyle = '#F1F5F9'; ctx.fill();
      ctx.strokeStyle = '#CBD5E1'; ctx.lineWidth = 2; ctx.stroke();
      ctx.fillStyle = mainColor; ctx.beginPath(); ctx.roundRect(-8, -16, 16, 12, 3); ctx.fill();
      ctx.fillStyle = '#22C55E'; ctx.beginPath(); ctx.arc(-3, -10, 1.5, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(3, -10, 1.5, 0, Math.PI * 2); ctx.fill();

      // Big Round Space Helmet
      ctx.beginPath(); ctx.arc(0, -32, 13, 0, Math.PI * 2); ctx.fillStyle = '#E2E8F0'; ctx.fill();
      ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 1.5; ctx.stroke();

      // Iridescent Cyan/Gold Visor
      ctx.beginPath(); ctx.ellipse(0, -32, 9, 7.5, 0, 0, Math.PI * 2); ctx.fillStyle = '#0891B2'; ctx.fill();
      ctx.strokeStyle = '#67E8F9'; ctx.lineWidth = 1.5; ctx.stroke();
      // Glint
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.arc(0, -32, 6, 1.1 * Math.PI, 1.6 * Math.PI); ctx.stroke();

    } else if (avatarId === 'robot') {
      // --- MECHA ROBOT CHIBI ---
      // Top Antenna with glowing bulb
      ctx.strokeStyle = '#06B6D4'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(0, -37); ctx.lineTo(0, -45); ctx.stroke();
      ctx.fillStyle = '#FACC15'; ctx.beginPath(); ctx.arc(0, -46, 3, 0, Math.PI * 2); ctx.fill();

      // Metallic Chassis Torso
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 21, 4); ctx.fillStyle = '#475569'; ctx.fill();
      ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 1.5; ctx.stroke();
      ctx.fillStyle = '#1E293B'; ctx.fillRect(-7, -15, 14, 9);

      // Square Monitor Head
      ctx.beginPath(); ctx.roundRect(-12, -37, 24, 18, 4); ctx.fillStyle = '#64748B'; ctx.fill();
      ctx.strokeStyle = '#334155'; ctx.lineWidth = 1.5; ctx.stroke();

      // CRT Face Screen
      ctx.fillStyle = '#0F172A'; ctx.beginPath(); ctx.roundRect(-9, -34, 18, 12, 2.5); ctx.fill();

      // Cyan Glowing Anime Eyes ( ^ ◡ ^ )
      ctx.strokeStyle = '#38BDF8'; ctx.lineWidth = 1.8; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(-6, -29); ctx.lineTo(-4, -31); ctx.lineTo(-2, -29); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(2, -29); ctx.lineTo(4, -31); ctx.lineTo(6, -29); ctx.stroke();
      ctx.beginPath(); ctx.arc(0, -26, 2, 0.1 * Math.PI, 0.9 * Math.PI); ctx.stroke();

    } else if (avatarId === 'wizard') {
      // --- WIZARD MERLIN CHIBI ---
      // Robes
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 22, 5); ctx.fillStyle = '#6D28D9'; ctx.fill();
      ctx.strokeStyle = '#FDE047'; ctx.lineWidth = 1.5; ctx.stroke();

      // Head & White Cloud Beard
      ctx.beginPath(); ctx.arc(0, -28, 9, 0, Math.PI * 2); ctx.fillStyle = '#FED7AA'; ctx.fill();
      ctx.fillStyle = '#F8FAFC';
      ctx.beginPath(); ctx.moveTo(-8, -26); ctx.quadraticCurveTo(0, -14, 8, -26); ctx.closePath(); ctx.fill();

      // Wizard Hat
      ctx.fillStyle = '#7C3AED';
      ctx.beginPath(); ctx.ellipse(0, -35, 13, 3.5, 0, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.moveTo(-9, -35); ctx.quadraticCurveTo(-1, -50, 4, -58); ctx.lineTo(7, -35); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#FDE047'; ctx.beginPath(); ctx.arc(0, -44, 2, 0, Math.PI * 2); ctx.fill();

      // Eyes
      ctx.fillStyle = '#1E1B4B';
      ctx.beginPath(); ctx.arc(-3.5, -28, 1.4, 0, Math.PI * 2); ctx.arc(3.5, -28, 1.4, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'ninja') {
      // --- SHINOBI NINJA CHIBI ---
      // Headband tails flying
      ctx.strokeStyle = '#DC2626'; ctx.lineWidth = 3; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(11, -33); ctx.quadraticCurveTo(20, -32, 18, -22); ctx.stroke();

      // Ninja Gi Torso
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 21, 5); ctx.fillStyle = '#1E293B'; ctx.fill();
      ctx.strokeStyle = '#DC2626'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(-10, -19); ctx.lineTo(10, 0); ctx.stroke();

      // Dark Mask Cowl
      ctx.beginPath(); ctx.roundRect(-11, -37, 22, 19, 7); ctx.fillStyle = '#0F172A'; ctx.fill();

      // Red Forehead Band
      ctx.fillStyle = '#DC2626'; ctx.fillRect(-11, -35, 22, 4);

      // Eye Slit & Intense Eyes
      ctx.fillStyle = '#FED7AA'; ctx.fillRect(-7, -29, 14, 5);
      ctx.fillStyle = '#DC2626';
      ctx.beginPath(); ctx.arc(-3.5, -26.5, 1.5, 0, Math.PI * 2); ctx.arc(3.5, -26.5, 1.5, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'detective') {
      // --- DETECTIVE CHIBI ---
      // Trenchcoat
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 22, 5); ctx.fillStyle = '#B45309'; ctx.fill();
      ctx.strokeStyle = '#78350F'; ctx.lineWidth = 1.5; ctx.stroke();

      // Chibi Head
      ctx.beginPath(); ctx.roundRect(-11, -36, 22, 18, 6); ctx.fillStyle = '#FED7AA'; ctx.fill();

      // Deerstalker Cap
      ctx.fillStyle = '#78350F';
      ctx.beginPath(); ctx.ellipse(0, -36, 14, 3.5, 0, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.roundRect(-9, -46, 18, 11, [5, 5, 1, 1]); ctx.fillStyle = '#92400E'; ctx.fill();

      // Monocle Right Eye
      ctx.strokeStyle = '#F59E0B'; ctx.lineWidth = 1.8;
      ctx.beginPath(); ctx.arc(4, -27, 3, 0, Math.PI * 2); ctx.stroke();
      ctx.fillStyle = '#1E293B';
      ctx.beginPath(); ctx.arc(-4, -27, 1.8, 0, Math.PI * 2); ctx.arc(4, -27, 1.8, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'panda') {
      // --- CHIBI PANDA ---
      // Black Ears
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.arc(-9, -38, 4.5, 0, Math.PI * 2); ctx.arc(9, -38, 4.5, 0, Math.PI * 2); ctx.fill();

      // Torso & Red Bowtie
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 22, 6); ctx.fillStyle = '#EA580C'; ctx.fill();
      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(-3, -17, 2.5, 0, Math.PI * 2); ctx.arc(3, -17, 2.5, 0, Math.PI * 2); ctx.fill();

      // Round White Panda Head
      ctx.beginPath(); ctx.arc(0, -28, 12, 0, Math.PI * 2); ctx.fillStyle = '#F8FAFC'; ctx.fill();
      ctx.strokeStyle = '#0F172A'; ctx.lineWidth = 1.2; ctx.stroke();

      // Black Eye Patches
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.ellipse(-5, -29, 3, 3.8, 0, 0, Math.PI * 2); ctx.ellipse(5, -29, 3, 3.8, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-5.8, -30, 1.2, 0, Math.PI * 2); ctx.arc(4.2, -30, 1.2, 0, Math.PI * 2); ctx.fill();

      // Nose & Blush
      ctx.fillStyle = '#0F172A'; ctx.beginPath(); ctx.arc(0, -25, 1.5, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FDA4AF'; ctx.beginPath(); ctx.arc(-8, -26, 2, 0, Math.PI * 2); ctx.arc(8, -26, 2, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'king') {
      // --- ROYAL KING CHIBI ---
      // Royal Cape
      ctx.beginPath(); ctx.roundRect(-15, -19, 30, 22, 5); ctx.fillStyle = '#9333EA'; ctx.fill();
      ctx.fillStyle = '#F8FAFC'; ctx.fillRect(-6, -19, 12, 22);

      // Head & Golden Crown
      ctx.beginPath(); ctx.roundRect(-11, -35, 22, 17, 5); ctx.fillStyle = '#FDE047'; ctx.fill();
      ctx.fillStyle = '#FACC15'; ctx.strokeStyle = '#CA8A04'; ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(-11, -35); ctx.lineTo(-12, -47); ctx.lineTo(-5, -41); ctx.lineTo(0, -49); ctx.lineTo(5, -41); ctx.lineTo(12, -47); ctx.lineTo(11, -35);
      ctx.closePath(); ctx.fill(); ctx.stroke();

      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(0, -44, 2, 0, Math.PI * 2); ctx.fill();

      // Eyes & Smile
      ctx.fillStyle = '#1E293B';
      ctx.beginPath(); ctx.arc(-4, -28, 1.8, 0, Math.PI * 2); ctx.arc(4, -28, 1.8, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.arc(0, -24, 3, 0.1 * Math.PI, 0.9 * Math.PI); ctx.stroke();

    } else {
      // --- BUILDER (MINIFIG BOB) DEFAULT ---
      // Blue Dungarees Torso
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 22, 5); ctx.fillStyle = mainColor; ctx.fill();
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 2; ctx.stroke();

      // Yellow Head
      ctx.beginPath(); ctx.roundRect(-10, -35, 20, 17, 5); ctx.fillStyle = '#FCD34D'; ctx.fill();
      ctx.strokeStyle = '#1E293B'; ctx.lineWidth = 1.5; ctx.stroke();

      // Hardhat / Stud
      ctx.beginPath(); ctx.ellipse(0, -35, 12, 3.5, 0, 0, Math.PI * 2); ctx.fillStyle = '#EA580C'; ctx.fill();
      ctx.beginPath(); ctx.arc(0, -37, 7, Math.PI, 0); ctx.fillStyle = '#F97316'; ctx.fill();

      // Cute Face
      ctx.fillStyle = '#1E293B';
      ctx.beginPath(); ctx.arc(-4, -28, 1.8, 0, Math.PI * 2); ctx.arc(4, -28, 1.8, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-4.6, -28.8, 0.8, 0, Math.PI * 2); ctx.arc(3.4, -28.8, 0.8, 0, Math.PI * 2); ctx.fill();

      ctx.strokeStyle = '#1E293B'; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.arc(0, -24, 3.5, 0.1 * Math.PI, 0.9 * Math.PI, false); ctx.stroke();

      ctx.fillStyle = '#FDA4AF';
      ctx.beginPath(); ctx.ellipse(-7.5, -25.5, 2, 1.2, 0, 0, Math.PI * 2); ctx.ellipse(7.5, -25.5, 2, 1.2, 0, 0, Math.PI * 2); ctx.fill();
    }

    // 6. Dynamic Headwear / Hats (if equipped)
    const hat = player.hat;
    if (hat === 'crown') {
      ctx.fillStyle = '#FBBF24'; ctx.strokeStyle = '#D97706'; ctx.lineWidth = 1.2;
      ctx.beginPath();
      ctx.moveTo(-9, -37); ctx.lineTo(-9, -48); ctx.lineTo(-4, -42); ctx.lineTo(0, -50); ctx.lineTo(4, -42); ctx.lineTo(9, -48); ctx.lineTo(9, -37);
      ctx.closePath(); ctx.fill(); ctx.stroke();
      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(0, -44, 2, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'headphones') {
      // DJ Headphones
      ctx.strokeStyle = '#0F172A'; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.arc(0, -36, 13, Math.PI, 0); ctx.stroke();
      ctx.fillStyle = '#06B6D4'; ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.roundRect(-16, -34, 5, 11, 2); ctx.fill(); ctx.stroke();
      ctx.beginPath(); ctx.roundRect(11, -34, 5, 11, 2); ctx.fill(); ctx.stroke();
    } else if (hat === 'flower_crown') {
      // Flower Crown
      const flowers = ['#F43F5E', '#FBBF24', '#38BDF8', '#A855F7'];
      for (let f = 0; f < 4; f++) {
        ctx.fillStyle = flowers[f];
        ctx.beginPath(); ctx.arc(-9 + f * 6, -36, 2.5, 0, Math.PI * 2); ctx.fill();
      }
    } else if (hat === 'halo') {
      // Angel Halo
      ctx.strokeStyle = '#FDE047'; ctx.lineWidth = 2.5;
      ctx.shadowColor = '#FACC15'; ctx.shadowBlur = 8;
      ctx.beginPath(); ctx.ellipse(0, -47, 10, 3.5, 0, 0, Math.PI * 2); ctx.stroke();
      ctx.shadowBlur = 0;
    } else if (hat === 'pirate') {
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.moveTo(-15, -36); ctx.quadraticCurveTo(0, -54, 15, -36); ctx.quadraticCurveTo(0, -43, -15, -36); ctx.closePath(); ctx.fill();
      ctx.strokeStyle = '#F59E0B'; ctx.lineWidth = 1.2; ctx.stroke();
      ctx.fillStyle = '#FFFFFF'; ctx.beginPath(); ctx.arc(0, -43, 2.5, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'wizard') {
      ctx.fillStyle = '#7C3AED'; ctx.beginPath(); ctx.ellipse(0, -36, 13, 4, 0, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.moveTo(-10, -36); ctx.quadraticCurveTo(-2, -54, 5, -62); ctx.lineTo(8, -36); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#FDE047'; ctx.beginPath(); ctx.arc(0, -45, 2, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'cat_ears') {
      ctx.fillStyle = mainColor;
      ctx.beginPath(); ctx.moveTo(-9, -36); ctx.lineTo(-6, -46); ctx.lineTo(-2, -36); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(2, -36); ctx.lineTo(6, -46); ctx.lineTo(9, -36); ctx.closePath(); ctx.fill();
    } else if (hat === 'party_hat') {
      ctx.fillStyle = '#EF4444';
      ctx.beginPath(); ctx.moveTo(-8, -37); ctx.lineTo(8, -37); ctx.lineTo(0, -56); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#38BDF8'; ctx.beginPath(); ctx.arc(0, -57, 3, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'visor') {
      ctx.fillStyle = '#06B6D4'; ctx.shadowColor = '#06B6D4'; ctx.shadowBlur = 8;
      ctx.fillRect(-10, -30, 20, 5); ctx.shadowBlur = 0;
      ctx.fillStyle = '#FFFFFF'; ctx.fillRect(-8, -29, 16, 1.5);
    } else if (hat === 'detective') {
      ctx.fillStyle = '#78350F'; ctx.beginPath(); ctx.ellipse(0, -37, 14, 3.5, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#92400E'; ctx.beginPath(); ctx.roundRect(-8, -47, 16, 11, [4, 4, 1, 1]); ctx.fill();
    } else if (hat === 'viking') {
      ctx.fillStyle = '#64748B'; ctx.beginPath(); ctx.arc(0, -36, 10, Math.PI, 0); ctx.fill();
      ctx.fillStyle = '#FEF08A';
      ctx.beginPath(); ctx.moveTo(-9, -37); ctx.quadraticCurveTo(-16, -43, -15, -49); ctx.quadraticCurveTo(-11, -43, -6, -39); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(9, -37); ctx.quadraticCurveTo(16, -43, 15, -49); ctx.quadraticCurveTo(11, -43, 6, -39); ctx.closePath(); ctx.fill();
    }

    // 7. Name tag — only above the pawn whose turn it is, to keep the board quiet
    if (isCurrent) {
      ctx.font = "700 10px 'Figtree', sans-serif";
      const displayName = player.name.length > 12 ? player.name.substr(0, 11) + '…' : player.name;
      const tagText = displayName;
      const textWidth = ctx.measureText(tagText).width;
      const pillWidth = textWidth + 20;
      const pillHeight = 18;

      ctx.beginPath();
      ctx.roundRect(-pillWidth / 2, -56, pillWidth, pillHeight, 9);
      ctx.fillStyle = '#FFF4DE';
      ctx.fill();
      ctx.strokeStyle = '#E8B44A';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      ctx.fillStyle = '#2B1D12';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(tagText, 0, -47);

      // Active Buff Badges (Double Dice, Lucky Die, Ghost)
      let buffOffset = pillWidth / 2 + 10;
      if (player.doubleDiceActive) {
        ctx.beginPath();
        ctx.arc(buffOffset, -47, 8, 0, Math.PI * 2);
        ctx.fillStyle = '#F59E0B';
        ctx.fill();
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 1.2;
        ctx.stroke();
        ctx.font = '9px sans-serif';
        ctx.fillText('🎲', buffOffset, -46);
        buffOffset += 18;
      }
      if (player.fixedRollNext) {
        ctx.beginPath();
        ctx.arc(buffOffset, -47, 8, 0, Math.PI * 2);
        ctx.fillStyle = '#10B981';
        ctx.fill();
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 1.2;
        ctx.stroke();
        ctx.font = 'bold 9px sans-serif';
        ctx.fillStyle = '#FFFFFF';
        ctx.fillText(player.fixedRollNext, buffOffset, -47);
        buffOffset += 18;
      }
    }

    ctx.restore();
  }

  darkenColor(hex, factor = 0.2) {
    if (!hex || hex[0] !== '#') return '#1E293B';
    let r = parseInt(hex.substr(1, 2), 16);
    let g = parseInt(hex.substr(3, 2), 16);
    let b = parseInt(hex.substr(5, 2), 16);
    r = Math.floor(Math.max(0, r * (1 - factor)));
    g = Math.floor(Math.max(0, g * (1 - factor)));
    b = Math.floor(Math.max(0, b * (1 - factor)));
    return `rgb(${r}, ${g}, ${b})`;
  }
}

window.BoardRenderer = BoardRenderer;


/* =================== 5.5. WORLD MAPS (CITY DIORAMAS & TRACKS) =================== */
/**
 * World Maps — hand-built city dioramas for the board renderer.
 *
 * Each city has its own track shape (32 spaces sampled from a closed spline),
 * its own geography (coastlines, rivers, districts, landmarks) and its own
 * ambient motion. The static scenery is rendered once into an offscreen
 * canvas per city; only the moving details are drawn every frame.
 *
 * This file patches BoardRenderer (board-map.js) instead of rewriting it, so
 * cameras, tokens, hop animations and tile clicks keep working unchanged.
 */
(function () {
  'use strict';
  if (typeof BoardRenderer === 'undefined') return;

  const W = 1100;
  const H = 760;
  // The map is a physical board lying on the felt table:
  //   BM     = printed board margin around the 1100x760 world (frame lives here)
  //   THICK  = visible board thickness below the top face
  //   PAD    = cache area around the world (board + thickness + cast shadow)
  const BM = 44;
  const THICK = 16;
  const PAD = BM + 70;
  const BOARD_RADIUS = 30;
  // Static layer resolution. Phones render the board small, so 1x is plenty there.
  const CACHE_SCALE = (typeof window !== 'undefined' && window.innerWidth && window.innerWidth < 700) ? 1.25 : 2;
  const SPRITE_SCALE = 2;
  // Light comes from the top-left everywhere (shadows fall to the bottom-right)
  const SHADOW_DX = 4;
  const SHADOW_DY = 4;

  // ---------------------------------------------------------------------------
  // Geometry helpers
  // ---------------------------------------------------------------------------
  function seeded(seed) {
    let s = seed >>> 0;
    return () => {
      s = (Math.imul(s, 1664525) + 1013904223) >>> 0;
      return s / 4294967296;
    };
  }

  // Closed Catmull-Rom spline through control points -> dense polyline
  function spline(points, steps) {
    const out = [];
    const n = points.length;
    for (let i = 0; i < n; i++) {
      const p0 = points[(i - 1 + n) % n];
      const p1 = points[i];
      const p2 = points[(i + 1) % n];
      const p3 = points[(i + 2) % n];
      for (let s = 0; s < steps; s++) {
        const t = s / steps;
        const t2 = t * t;
        const t3 = t2 * t;
        out.push({
          x: 0.5 * ((2 * p1[0]) + (-p0[0] + p2[0]) * t + (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2 + (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3),
          y: 0.5 * ((2 * p1[1]) + (-p0[1] + p2[1]) * t + (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2 + (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3)
        });
      }
    }
    return out;
  }

  // n points at equal arc length along a closed polyline, starting at its first point
  function resample(poly, n) {
    const lens = [0];
    for (let i = 1; i <= poly.length; i++) {
      const a = poly[i - 1];
      const b = poly[i % poly.length];
      lens.push(lens[i - 1] + Math.hypot(b.x - a.x, b.y - a.y));
    }
    const total = lens[lens.length - 1];
    const out = [];
    let seg = 0;
    for (let k = 0; k < n; k++) {
      const target = (total * k) / n;
      while (lens[seg + 1] < target) seg++;
      const a = poly[seg];
      const b = poly[(seg + 1) % poly.length];
      const f = (target - lens[seg]) / Math.max(1e-6, lens[seg + 1] - lens[seg]);
      out.push({ x: Math.round(a.x + (b.x - a.x) * f), y: Math.round(a.y + (b.y - a.y) * f) });
    }
    return { points: out, length: total };
  }

  function distToPolyline(x, y, poly) {
    let best = Infinity;
    for (let i = 0; i < poly.length; i++) {
      const a = poly[i];
      const b = poly[(i + 1) % poly.length];
      const dx = b.x - a.x;
      const dy = b.y - a.y;
      const l2 = dx * dx + dy * dy || 1;
      let t = ((x - a.x) * dx + (y - a.y) * dy) / l2;
      t = Math.max(0, Math.min(1, t));
      const d = Math.hypot(x - (a.x + t * dx), y - (a.y + t * dy));
      if (d < best) best = d;
    }
    return best;
  }

  function inPoly(x, y, poly) {
    let inside = false;
    for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      const xi = poly[i][0], yi = poly[i][1], xj = poly[j][0], yj = poly[j][1];
      if (((yi > y) !== (yj > y)) && (x < ((xj - xi) * (y - yi)) / (yj - yi) + xi)) inside = !inside;
    }
    return inside;
  }

  function smoothShape(ctx, pts, steps) {
    const dense = spline(pts, steps || 10);
    ctx.beginPath();
    dense.forEach((p, i) => (i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y)));
    ctx.closePath();
    return dense;
  }

  // Open band (river) along control points
  function bandPath(ctx, pts) {
    ctx.beginPath();
    pts.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
  }

  function polyFill(ctx, pts, fill) {
    ctx.beginPath();
    pts.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
    ctx.closePath();
    ctx.fillStyle = fill;
    ctx.fill();
  }

  // Oblique extruded block: (x, y) is the front-bottom-left corner
  function block(ctx, x, y, w, h, depth, c, rand, windows) {
    const dx = depth * 0.55;
    const dy = -depth * 0.42;
    // cast shadow toward the bottom-right
    ctx.fillStyle = 'rgba(15, 10, 20, 0.22)';
    ctx.beginPath();
    ctx.moveTo(x + w, y); ctx.lineTo(x + w + h * 0.35, y + h * 0.12); ctx.lineTo(x + h * 0.35, y + h * 0.12 + 2); ctx.lineTo(x, y);
    ctx.fill();
    polyFill(ctx, [[x + w, y], [x + w + dx, y + dy], [x + w + dx, y + dy - h], [x + w, y - h]], c.side);
    ctx.fillStyle = c.front;
    ctx.fillRect(x, y - h, w, h);
    // ambient occlusion at the foot of the facade
    const ao = ctx.createLinearGradient(0, y - Math.min(h, 14), 0, y);
    ao.addColorStop(0, 'rgba(0,0,0,0)');
    ao.addColorStop(1, 'rgba(0,0,0,0.22)');
    ctx.fillStyle = ao;
    ctx.fillRect(x, y - Math.min(h, 14), w, Math.min(h, 14));
    polyFill(ctx, [[x, y - h], [x + dx, y - h + dy], [x + w + dx, y - h + dy], [x + w, y - h]], c.top);
    // sunlit roof edge
    ctx.strokeStyle = 'rgba(255, 245, 225, 0.35)';
    ctx.lineWidth = 0.8;
    ctx.beginPath(); ctx.moveTo(x, y - h); ctx.lineTo(x + w, y - h); ctx.stroke();
    if (windows && h > 14) {
      const cols = Math.max(1, Math.floor((w - 4) / 6));
      const rows = Math.max(1, Math.floor((h - 6) / 7));
      for (let r = 0; r < rows; r++) {
        for (let k = 0; k < cols; k++) {
          const lit = rand() < windows.lit;
          ctx.fillStyle = lit ? windows.on : windows.off;
          ctx.fillRect(x + 3 + k * 6, y - h + 4 + r * 7, 3, 4);
        }
      }
    }
  }

  // Scatter items over land, away from the track and from keep-out circles
  function scatter(rand, count, opts) {
    const out = [];
    let guard = count * 30;
    while (out.length < count && guard-- > 0) {
      const x = opts.x0 + rand() * (opts.x1 - opts.x0);
      const y = opts.y0 + rand() * (opts.y1 - opts.y0);
      if (opts.isLand && !opts.isLand(x, y)) continue;
      if (opts.track && distToPolyline(x, y, opts.track) < (opts.clear || 52)) continue;
      if (opts.keepOut && opts.keepOut.some(k => Math.hypot(x - k[0], y - k[1]) < k[2])) continue;
      if (out.some(o => Math.hypot(o.x - x, o.y - y) < (opts.spacing || 14))) continue;
      out.push({ x, y, r: rand() });
    }
    return out.sort((a, b) => a.y - b.y);
  }

  // Neighbourhood placement: a jittered street grid, so cities read as blocks and streets
  function gridScatter(rand, opts) {
    const out = [];
    const [sx, sy] = opts.step;
    const every = opts.streetEvery || 0;
    let row = 0;
    for (let y = opts.y0; y < opts.y1; y += sy, row++) {
      let col = 0;
      for (let x = opts.x0; x < opts.x1; x += sx, col++) {
        if (every && (col % every === every - 1 || row % every === every - 1)) continue;
        if (rand() < (opts.skip || 0)) continue;
        let px = x + (rand() - 0.5) * (opts.jitter || 0);
        let py = y + (rand() - 0.5) * (opts.jitter || 0);
        if (opts.transform) [px, py] = opts.transform(px, py);
        if (opts.isLand && !opts.isLand(px, py)) continue;
        if (opts.track && distToPolyline(px, py, opts.track) < (opts.clear || 52)) continue;
        if (opts.keepOut && opts.keepOut.some(k => Math.hypot(px - k[0], py - k[1]) < k[2])) continue;
        out.push({ x: px, y: py, r: rand() });
      }
    }
    return out.sort((a, b) => a.y - b.y);
  }

  // Lit tree: cast shadow, trunk, dark base canopy, mid canopy, sunlit highlight
  function tree(ctx, x, y, r, c1, c2) {
    ctx.fillStyle = 'rgba(20, 14, 10, 0.28)';
    ctx.beginPath();
    ctx.ellipse(x + r * 0.6 + SHADOW_DX, y + SHADOW_DY * 0.5, r * 1.05, r * 0.5, 0.35, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#5A3E2B';
    ctx.fillRect(x - 1, y - r * 0.5, 2, r * 0.55);
    ctx.fillStyle = shade(c1, -0.22);
    ctx.beginPath(); ctx.arc(x + r * 0.12, y - r * 0.35, r, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = c1;
    ctx.beginPath(); ctx.arc(x - r * 0.05, y - r * 0.55, r * 0.86, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = c2;
    ctx.beginPath(); ctx.arc(x - r * 0.32, y - r * 0.82, r * 0.46, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(255, 250, 220, 0.22)';
    ctx.beginPath(); ctx.arc(x - r * 0.42, y - r * 0.92, r * 0.2, 0, Math.PI * 2); ctx.fill();
  }

  // Lighten (+) or darken (-) a #rrggbb colour
  function shade(hex, f) {
    if (typeof hex !== 'string' || hex[0] !== '#' || hex.length !== 7) return hex;
    const n = parseInt(hex.slice(1), 16);
    const ch = (v) => Math.max(0, Math.min(255, Math.round(f >= 0 ? v + (255 - v) * f : v * (1 + f))));
    return `rgb(${ch((n >> 16) & 255)}, ${ch((n >> 8) & 255)}, ${ch(n & 255)})`;
  }

  // Small house with a two-plane gabled roof, lit from the top-left
  function house(ctx, x, y, w, h, wall, roof, rand) {
    const d = w * 0.5;            // depth of the side wall
    const dx = d * 0.55, dy = -d * 0.42;
    // contact shadow
    ctx.fillStyle = 'rgba(20, 14, 10, 0.3)';
    ctx.beginPath();
    ctx.moveTo(x + w, y); ctx.lineTo(x + w + dx + 6, y + 3); ctx.lineTo(x + 4, y + 4); ctx.lineTo(x, y);
    ctx.fill();
    // side wall (in shade) and front wall (lit)
    polyFill(ctx, [[x + w, y], [x + w + dx, y + dy], [x + w + dx, y + dy - h], [x + w, y - h]], shade(wall, -0.24));
    ctx.fillStyle = wall;
    ctx.fillRect(x, y - h, w, h);
    // gable roof: lit plane + shaded plane
    const ridgeY = y - h - w * 0.42;
    polyFill(ctx, [[x - 1.5, y - h], [x + w / 2, ridgeY], [x + w / 2 + dx, ridgeY + dy], [x - 1.5 + dx, y - h + dy]], shade(roof, 0.12));
    polyFill(ctx, [[x + w / 2, ridgeY], [x + w + 1.5, y - h], [x + w + 1.5 + dx, y - h + dy], [x + w / 2 + dx, ridgeY + dy]], shade(roof, -0.18));
    polyFill(ctx, [[x - 1.5, y - h], [x + w / 2, ridgeY], [x + w + 1.5, y - h]], roof);
    // window + door
    ctx.fillStyle = rand() > 0.4 ? 'rgba(255, 214, 140, 0.95)' : 'rgba(60, 50, 60, 0.6)';
    ctx.fillRect(x + w * 0.18, y - h * 0.72, w * 0.22, h * 0.28);
    ctx.fillStyle = shade(wall, -0.45);
    ctx.fillRect(x + w * 0.58, y - h * 0.5, w * 0.2, h * 0.5);
  }

  function waterTexture(ctx, rand, x0, y0, x1, y1, color, count) {
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.4;
    for (let i = 0; i < count; i++) {
      const x = x0 + rand() * (x1 - x0);
      const y = y0 + rand() * (y1 - y0);
      const w = 8 + rand() * 18;
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.quadraticCurveTo(x + w / 2, y - 3, x + w, y);
      ctx.stroke();
    }
  }

  function label(ctx, text, x, y, opts) {
    const o = opts || {};
    ctx.save();
    ctx.font = `${o.weight || 700} ${o.size || 13}px 'Bricolage Grotesque', 'Figtree', sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const w = ctx.measureText(text).width + 18;
    ctx.fillStyle = o.bg || 'rgba(30, 21, 48, 0.82)';
    ctx.beginPath();
    ctx.roundRect(x - w / 2, y - 11, w, 22, 11);
    ctx.fill();
    ctx.strokeStyle = o.border || 'rgba(232, 180, 74, 0.55)';
    ctx.lineWidth = 1;
    ctx.stroke();
    ctx.fillStyle = o.color || '#FFF4DE';
    ctx.fillText(text, x, y + 0.5);
    ctx.restore();
  }

  // ---------------------------------------------------------------------------
  // City definitions
  // ---------------------------------------------------------------------------
  const WORLDS = {};

  /* ============================== İSTANBUL ============================== */
  (function () {
    const europe = [[-PAD, -PAD], [520, -PAD], [518, 40], [505, 150], [480, 260], [455, 360], [470, 470], [520, 560], [505, 660], [430, H + PAD], [-PAD, H + PAD]];
    const asia = [[640, -PAD], [W + PAD, -PAD], [W + PAD, H + PAD], [770, H + PAD], [700, 650], [660, 545], [640, 440], [655, 300], [640, 160], [628, 40]];
    // Golden Horn reaches far enough west that the track crosses it on the Galata Bridge
    const goldenHorn = [[478, 392], [400, 404], [300, 418], [180, 432], [62, 446], [90, 464], [180, 460], [310, 450], [404, 442], [484, 440]];
    const isLand = (x, y) => (inPoly(x, y, europe) || inPoly(x, y, asia)) && !inPoly(x, y, goldenHorn);

    WORLDS.istanbul = {
      title: 'İstanbul',
      subtitle: 'İki kıta, bir Boğaz',
      outside: '#0D3F5D',
      road: { base: '#CDB79A', edge: '#7D6249', line: '#FFF4DE', dash: [3, 9], glow: null },
      control: [
        [455, 590], [330, 622], [200, 598], [118, 520], [112, 420], [140, 316], [210, 226], [318, 168], [440, 140],
        [560, 132], [690, 140], [810, 168], [918, 222], [986, 318], [1002, 430], [960, 540], [868, 616], [740, 646], [620, 628]
      ],
      landmark: { x: 590, y: 700, name: 'Kız Kulesi', icon: '🏰' },
      labels: [['Avrupa Yakası', 250, 300], ['Asya Yakası', 860, 400], ['Haliç', 300, 470], ['Boğaz', 575, 360]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1453);
        // Sea
        const sea = ctx.createLinearGradient(0, -PAD, 0, H + PAD);
        sea.addColorStop(0, '#0F4A6B');
        sea.addColorStop(1, '#0A3049');
        ctx.fillStyle = sea;
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);
        waterTexture(ctx, rand, -PAD, -PAD, W + PAD, H + PAD, 'rgba(160, 220, 255, 0.10)', 520);

        // Shallow water halo + land
        [europe, asia].forEach(shape => {
          ctx.save();
          ctx.beginPath();
          shape.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
          ctx.closePath();
          ctx.strokeStyle = 'rgba(120, 210, 220, 0.30)';
          ctx.lineWidth = 26;
          ctx.stroke();
          const land = ctx.createLinearGradient(0, 0, W, H);
          land.addColorStop(0, '#B9A07C');
          land.addColorStop(1, '#957A5B');
          ctx.fillStyle = land;
          ctx.fill();
          ctx.strokeStyle = '#E9D4AE';
          ctx.lineWidth = 3;
          ctx.stroke();
          ctx.restore();
        });

        // Golden Horn inlet
        ctx.save();
        ctx.beginPath();
        goldenHorn.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        ctx.fillStyle = '#0F4A6B';
        ctx.fill();
        ctx.strokeStyle = '#E9D4AE';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();

        // Meadows wherever no neighbourhood stands
        const districtField = (x, y) => Math.sin(x * 0.017) + Math.sin(y * 0.022 + 1.3) + Math.sin((x + y) * 0.009 + 0.4);
        ctx.fillStyle = 'rgba(126, 150, 92, 0.55)';
        for (let gy = -PAD; gy < H + PAD; gy += 22) {
          for (let gx = -PAD; gx < W + PAD; gx += 22) {
            if (districtField(gx, gy) <= -0.45 && isLand(gx, gy)) {
              ctx.beginPath(); ctx.arc(gx, gy, 17, 0, Math.PI * 2); ctx.fill();
            }
          }
        }

        // Parks (Gülhane, Yıldız, Validebağ)
        [[250, 560, 55, 32], [760, 250, 60, 38], [850, 470, 48, 30]].forEach(([x, y, rx, ry]) => {
          ctx.fillStyle = '#4E7A3E';
          ctx.beginPath();
          ctx.ellipse(x, y, rx, ry, 0.2, 0, Math.PI * 2);
          ctx.fill();
        });

        // Houses with terracotta roofs, in clustered neighbourhoods with green
        // gaps between them (low-frequency "district" field)
        const walls = ['#EADBC0', '#E7C9A3', '#D9B48F', '#C9D2B8', '#EFE3CF', '#E3B9A0'];
        const district = (x, y) => Math.sin(x * 0.017) + Math.sin(y * 0.022 + 1.3) + Math.sin((x + y) * 0.009 + 0.4);
        const houses = gridScatter(rand, {
          x0: -PAD + 30, x1: W + PAD - 30, y0: -PAD + 30, y1: H + PAD - 10,
          step: [22, 18], jitter: 3, streetEvery: 4, skip: 0.42,
          isLand: (x, y) => isLand(x, y) && district(x, y) > -0.35, track, clear: 48,
          keepOut: [[330, 500, 70], [300, 335, 40], [850, 380, 60], [590, 132, 80], [250, 560, 58], [760, 250, 62], [850, 470, 50]]
        });
        houses.forEach(h => {
          const w = 11 + h.r * 6;
          const hh = 8 + h.r * 5;
          house(ctx, h.x, h.y, w, hh, walls[Math.floor(h.r * walls.length)], h.r > 0.5 ? '#B5533C' : '#C96A4A', rand);
        });

        // Green between the neighbourhoods
        scatter(rand, 260, {
          x0: -PAD, x1: W + PAD, y0: -PAD, y1: H + PAD,
          isLand: (x, y) => isLand(x, y) && district(x, y) <= -0.35, track, clear: 44, spacing: 16
        }).forEach(t => tree(ctx, t.x, t.y, 5 + t.r * 3, '#5B7F45', '#77A05A'));

        // Ayasofya: dome, half-domes, four minarets
        drawMosque(ctx, 330, 512, 1.25, '#D99C8B');
        // Sultanahmet-style mosque in Asia (Çamlıca)
        drawMosque(ctx, 850, 392, 1.05, '#E9E1D2');
        // Galata Tower
        drawGalata(ctx, 300, 345);

        // Bosphorus Bridge towers + cables around the northern crossing
        drawSuspension(ctx, 520, 646, 132);
        // Maiden's Tower islet
        ctx.fillStyle = '#E9D4AE';
        ctx.beginPath();
        ctx.ellipse(590, 708, 26, 12, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#F4EBDD';
        ctx.fillRect(582, 672, 16, 32);
        polyFill(ctx, [[578, 672], [590, 652], [602, 672]], '#6C8C9C');
        ctx.fillStyle = '#B5533C';
        ctx.fillRect(588, 640, 4, 14);
      },
      drawDynamic(ctx, t) {
        // Ferries crossing the Bosphorus
        for (let i = 0; i < 3; i++) {
          const phase = ((t * 0.018) + i * 0.33) % 1;
          const y = -40 + phase * (H + 80);
          const x = 575 + Math.sin(phase * 6 + i) * 18;
          drawFerry(ctx, x, y, i % 2 === 0);
        }
        // Gulls
        ctx.strokeStyle = 'rgba(255,255,255,0.85)';
        ctx.lineWidth = 1.6;
        for (let i = 0; i < 6; i++) {
          const gx = ((t * (12 + i * 3)) + i * 190) % (W + 200) - 100;
          const gy = 90 + i * 95 + Math.sin(t * 0.8 + i) * 14;
          const flap = Math.sin(t * 6 + i) * 3;
          ctx.beginPath();
          ctx.moveTo(gx - 6, gy - flap);
          ctx.quadraticCurveTo(gx - 3, gy - 4, gx, gy);
          ctx.quadraticCurveTo(gx + 3, gy - 4, gx + 6, gy - flap);
          ctx.stroke();
        }
      }
    };
  })();

  function drawMosque(ctx, x, y, s, wall) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(s, s);
    ctx.fillStyle = 'rgba(0,0,0,0.25)';
    ctx.beginPath();
    ctx.ellipse(4, 4, 40, 10, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = wall;
    ctx.fillRect(-30, -22, 60, 22);
    ctx.fillStyle = '#7E9AA8';
    ctx.beginPath(); ctx.arc(0, -22, 18, Math.PI, 0); ctx.fill();
    ctx.beginPath(); ctx.arc(-20, -20, 9, Math.PI, 0); ctx.fill();
    ctx.beginPath(); ctx.arc(20, -20, 9, Math.PI, 0); ctx.fill();
    ctx.fillStyle = '#E8B44A';
    ctx.fillRect(-1, -46, 2, 6);
    [-38, 38].forEach(mx => {
      ctx.fillStyle = '#F4EBDD';
      ctx.fillRect(mx - 2.5, -54, 5, 54);
      polyFill(ctx, [[mx - 3.5, -54], [mx, -66], [mx + 3.5, -54]], '#7E9AA8');
      ctx.fillStyle = '#D8CBB4';
      ctx.fillRect(mx - 4, -36, 8, 2);
    });
    ctx.fillStyle = 'rgba(255, 220, 140, 0.9)';
    for (let i = -24; i <= 20; i += 8) ctx.fillRect(i, -14, 3, 5);
    ctx.restore();
  }

  function drawGalata(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.25)';
    ctx.beginPath(); ctx.ellipse(3, 3, 16, 6, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#D9C3A0';
    ctx.fillRect(-10, -52, 20, 52);
    ctx.fillStyle = '#C9AE86';
    ctx.fillRect(-10, -52, 6, 52);
    ctx.fillStyle = '#3E3A3A';
    for (let i = -6; i <= 4; i += 5) ctx.fillRect(i, -46, 2, 4);
    ctx.fillStyle = '#E9D4AE';
    ctx.fillRect(-13, -56, 26, 5);
    polyFill(ctx, [[-12, -56], [0, -80], [12, -56]], '#596C7A');
    ctx.restore();
  }

  function drawSuspension(ctx, x0, x1, y) {
    ctx.save();
    ctx.strokeStyle = 'rgba(240, 230, 210, 0.9)';
    ctx.lineWidth = 1.5;
    [-16, 16].forEach(off => {
      ctx.beginPath();
      ctx.moveTo(x0 - 60, y + off);
      ctx.quadraticCurveTo((x0 + x1) / 2, y + off + 2, x1 + 60, y + off);
      ctx.stroke();
    });
    [x0, x1].forEach(tx => {
      ctx.fillStyle = '#C04A3C';
      ctx.fillRect(tx - 4, y - 24, 8, 48);
      ctx.fillStyle = '#8E2F25';
      ctx.fillRect(tx - 4, y - 24, 3, 48);
    });
    ctx.restore();
  }

  function drawFerry(ctx, x, y, north) {
    ctx.save();
    ctx.translate(x, y);
    if (!north) ctx.rotate(Math.PI);
    ctx.fillStyle = 'rgba(255,255,255,0.35)';
    ctx.beginPath(); ctx.moveTo(-5, 16); ctx.lineTo(0, 30); ctx.lineTo(5, 16); ctx.fill();
    ctx.fillStyle = '#F4EBDD';
    ctx.beginPath(); ctx.roundRect(-8, -16, 16, 32, 6); ctx.fill();
    ctx.fillStyle = '#26394A';
    ctx.fillRect(-5, -8, 10, 14);
    ctx.fillStyle = '#C04A3C';
    ctx.fillRect(-2, -3, 4, 4);
    ctx.restore();
  }

  /* ============================== TOKYO ============================== */
  (function () {
    const bay = [[700, H - 30], [880, 640], [1010, 600], [W + PAD, 560], [W + PAD, H + PAD], [640, H + PAD]];
    const river = [[760, -PAD], [742, 60], [720, 150], [736, 260], [768, 380], [792, 480], [800, 560], [820, 640]];
    const riverW = 44;
    const onRiver = (x, y) => {
      for (let i = 0; i < river.length - 1; i++) {
        const a = river[i], b = river[i + 1];
        const dx = b[0] - a[0], dy = b[1] - a[1];
        let t = ((x - a[0]) * dx + (y - a[1]) * dy) / (dx * dx + dy * dy);
        t = Math.max(0, Math.min(1, t));
        if (Math.hypot(x - (a[0] + t * dx), y - (a[1] + t * dy)) < riverW / 2) return true;
      }
      return false;
    };
    const isLand = (x, y) => !inPoly(x, y, bay) && !onRiver(x, y);

    WORLDS.tokyo = {
      title: 'Tokyo',
      subtitle: 'Neon körfezi',
      outside: '#12152A',
      road: { base: '#262A44', edge: '#0B0D1C', line: '#5EEAD4', dash: [10, 8], glow: 'rgba(94, 234, 212, 0.55)' },
      control: [
        [140, 610], [128, 470], [130, 330], [140, 190], [260, 150], [380, 158], [440, 230], [470, 330], [550, 365],
        [630, 330], [660, 230], [720, 158], [850, 150], [968, 190], [980, 330], [970, 470], [930, 600], [790, 650],
        [640, 660], [480, 662], [300, 652]
      ],
      landmark: { x: 550, y: 250, name: 'Tokyo Kulesi', icon: '🗼' },
      labels: [['Shibuya', 150, 690], ['Ueno Parkı', 300, 420], ['Tokyo Körfezi', 960, 690], ['Sumida', 735, 95]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1964);
        // Night ground
        ctx.fillStyle = '#12152A';
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);
        // Street grid
        ctx.strokeStyle = 'rgba(120, 130, 200, 0.14)';
        ctx.lineWidth = 6;
        for (let gx = -PAD; gx < W + PAD; gx += 64) { ctx.beginPath(); ctx.moveTo(gx, -PAD); ctx.lineTo(gx, H + PAD); ctx.stroke(); }
        for (let gy = -PAD; gy < H + PAD; gy += 58) { ctx.beginPath(); ctx.moveTo(-PAD, gy); ctx.lineTo(W + PAD, gy); ctx.stroke(); }

        // Bay
        ctx.save();
        ctx.beginPath();
        bay.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        const bayG = ctx.createLinearGradient(700, 560, 1100, 900);
        bayG.addColorStop(0, '#0D2A4A');
        bayG.addColorStop(1, '#06162A');
        ctx.fillStyle = bayG;
        ctx.fill();
        ctx.strokeStyle = 'rgba(94, 234, 212, 0.45)';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();
        // River
        ctx.save();
        bandPath(ctx, river);
        ctx.strokeStyle = '#0D2A4A';
        ctx.lineWidth = riverW;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        ctx.strokeStyle = 'rgba(94, 234, 212, 0.25)';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();
        waterTexture(ctx, rand, 700, 600, W + PAD, H + PAD, 'rgba(140, 200, 255, 0.12)', 120);

        // Ueno park with sakura
        ctx.fillStyle = '#1C2E2A';
        ctx.beginPath(); ctx.roundRect(220, 360, 170, 120, 26); ctx.fill();
        scatter(rand, 26, { x0: 230, x1: 380, y0: 370, y1: 470, spacing: 18 })
          .forEach(t => tree(ctx, t.x, t.y, 7 + t.r * 3, '#F4A6C6', '#FFD1E3'));

        // Shibuya scramble crossing (under the track corner)
        ctx.save();
        ctx.translate(140, 640);
        ctx.fillStyle = 'rgba(255,255,255,0.75)';
        for (let i = -3; i <= 3; i++) {
          ctx.fillRect(-60, i * 9 - 2, 30, 5);
          ctx.fillRect(30, i * 9 - 2, 30, 5);
        }
        ctx.rotate(Math.PI / 4);
        for (let i = -4; i <= 4; i++) ctx.fillRect(i * 9 - 2, -50, 5, 100);
        ctx.restore();

        // Skyscrapers with lit windows and neon signs
        const neon = ['#FF4FA3', '#5EEAD4', '#FDE047', '#A78BFA', '#38BDF8'];
        const towers = scatter(rand, 150, {
          x0: -PAD + 20, x1: W + PAD - 20, y0: -PAD + 60, y1: H + PAD,
          isLand, track, clear: 56, spacing: 26,
          keepOut: [[550, 250, 70], [305, 420, 100], [880, 330, 50], [140, 640, 70]]
        });
        towers.forEach(b => {
          const w = 16 + b.r * 14;
          const h = 22 + Math.pow(b.r, 2) * 70;
          const shade = 18 + Math.floor(b.r * 16);
          block(ctx, b.x - w / 2, b.y, w, h, 12, {
            front: `rgb(${shade + 8}, ${shade + 10}, ${shade + 34})`,
            side: `rgb(${shade}, ${shade + 2}, ${shade + 22})`,
            top: `rgb(${shade + 22}, ${shade + 24}, ${shade + 52})`
          }, rand, { lit: 0.35, on: b.r > 0.6 ? '#FDE68A' : '#9BE7FF', off: 'rgba(255,255,255,0.05)' });
          if (b.r > 0.78) {
            const c = neon[Math.floor(b.r * 97) % neon.length];
            ctx.save();
            ctx.shadowColor = c;
            ctx.shadowBlur = 10;
            ctx.fillStyle = c;
            ctx.fillRect(b.x - w / 2 + 2, b.y - h * 0.55, 3, Math.min(26, h * 0.4));
            ctx.restore();
          }
        });

        // Tokyo Tower in the notch of the track, Skytree on the east side
        drawTokyoTower(ctx, 550, 290, 1.25);
        drawSkytree(ctx, 880, 330);

        // Shinkansen line
        ctx.strokeStyle = '#3A3F63';
        ctx.lineWidth = 8;
        ctx.beginPath(); ctx.moveTo(-PAD, 718); ctx.lineTo(W + PAD, 718); ctx.stroke();
        ctx.strokeStyle = '#7C82B8';
        ctx.lineWidth = 1.5;
        [-3, 3].forEach(o => { ctx.beginPath(); ctx.moveTo(-PAD, 718 + o); ctx.lineTo(W + PAD, 718 + o); ctx.stroke(); });

        // Torii gate (Meiji) near the western track
        drawTorii(ctx, 60, 360);
      },
      drawDynamic(ctx, t) {
        // Shinkansen
        const tx = ((t * 160) % (W + PAD * 2 + 400)) - PAD - 200;
        ctx.save();
        ctx.fillStyle = '#F4F6FB';
        ctx.beginPath(); ctx.roundRect(tx, 710, 180, 16, 8); ctx.fill();
        ctx.fillStyle = '#1D4ED8';
        ctx.fillRect(tx + 10, 722, 160, 2);
        ctx.fillStyle = '#93C5FD';
        for (let k = 0; k < 9; k++) ctx.fillRect(tx + 16 + k * 18, 714, 10, 4);
        ctx.restore();
        // Sakura petals drifting over Ueno
        ctx.fillStyle = 'rgba(255, 190, 215, 0.9)';
        for (let i = 0; i < 18; i++) {
          const px = 210 + ((i * 53 + t * 18) % 220);
          const py = 330 + ((i * 37 + t * 26) % 190);
          ctx.beginPath();
          ctx.ellipse(px + Math.sin(t * 2 + i) * 6, py, 2.4, 1.4, t + i, 0, Math.PI * 2);
          ctx.fill();
        }
        // Tower beacon
        const a = (Math.sin(t * 3) + 1) / 2;
        ctx.fillStyle = `rgba(255, 80, 80, ${0.4 + a * 0.6})`;
        ctx.beginPath(); ctx.arc(550, 290 - 150, 3.5, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(880, 330 - 205, 3, 0, Math.PI * 2); ctx.fill();
      }
    };
  })();

  function drawTokyoTower(ctx, x, y, s) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(s, s);
    ctx.fillStyle = 'rgba(0,0,0,0.35)';
    ctx.beginPath(); ctx.ellipse(0, 2, 34, 9, 0, 0, Math.PI * 2); ctx.fill();
    const bands = ['#E5484D', '#F4F6FB'];
    for (let i = 0; i < 8; i++) {
      const y0 = -i * 14;
      const y1 = y0 - 14;
      const w0 = 30 - i * 3.4;
      const w1 = 30 - (i + 1) * 3.4;
      polyFill(ctx, [[-w0, y0], [-w1, y1], [w1, y1], [w0, y0]], bands[i % 2]);
      ctx.strokeStyle = 'rgba(0,0,0,0.25)';
      ctx.lineWidth = 0.8;
      ctx.beginPath(); ctx.moveTo(-w0, y0); ctx.lineTo(w1, y1); ctx.moveTo(w0, y0); ctx.lineTo(-w1, y1); ctx.stroke();
    }
    ctx.fillStyle = '#F4F6FB';
    ctx.fillRect(-9, -58, 18, 6);
    ctx.fillRect(-6, -96, 12, 5);
    ctx.fillStyle = '#E5484D';
    ctx.fillRect(-1.5, -122, 3, 12);
    ctx.restore();
  }

  function drawSkytree(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.35)';
    ctx.beginPath(); ctx.ellipse(0, 2, 20, 6, 0, 0, Math.PI * 2); ctx.fill();
    polyFill(ctx, [[-14, 0], [-4, -200], [4, -200], [14, 0]], '#C9D4E8');
    polyFill(ctx, [[-14, 0], [-4, -200], [0, -200], [-2, 0]], '#9FB0CC');
    ctx.fillStyle = '#E8EEF8';
    ctx.fillRect(-10, -120, 20, 8);
    ctx.fillRect(-7, -160, 14, 6);
    ctx.fillStyle = '#5EEAD4';
    ctx.fillRect(-10, -114, 20, 2);
    ctx.restore();
  }

  function drawTorii(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = '#E5484D';
    ctx.fillRect(-20, -30, 5, 30);
    ctx.fillRect(15, -30, 5, 30);
    ctx.fillRect(-26, -34, 52, 5);
    ctx.fillRect(-22, -24, 44, 3);
    ctx.fillStyle = '#1F1A1A';
    ctx.fillRect(-28, -37, 56, 3);
    ctx.restore();
  }

  /* ============================== PARIS ============================== */
  (function () {
    const seine = [[-PAD, 300], [60, 330], [190, 410], [330, 450], [470, 418], [610, 380], [740, 420], [870, 470], [1000, 440], [W + PAD, 390]];
    const seineW = 50;
    const onSeine = (x, y) => {
      for (let i = 0; i < seine.length - 1; i++) {
        const a = seine[i], b = seine[i + 1];
        const dx = b[0] - a[0], dy = b[1] - a[1];
        let t = ((x - a[0]) * dx + (y - a[1]) * dy) / (dx * dx + dy * dy);
        t = Math.max(0, Math.min(1, t));
        if (Math.hypot(x - (a[0] + t * dx), y - (a[1] + t * dy)) < seineW / 2 + 6) return true;
      }
      return false;
    };
    const center = [550, 375];
    // Track: a rosette around the Étoile
    const control = [];
    for (let i = 0; i < 20; i++) {
      const a = Math.PI / 2 + (i / 20) * Math.PI * 2;
      const r = 1 + 0.11 * Math.cos(a * 5);
      control.push([Math.round(center[0] + Math.cos(a) * 440 * r), Math.round(center[1] + Math.sin(a) * 268 * r)]);
    }
    const isLand = (x, y) => !onSeine(x, y);

    WORLDS.paris = {
      title: 'Paris',
      subtitle: 'Işık bulvarları',
      outside: '#8C7357',
      veil: 'rgba(40, 26, 18, 0.24)',
      road: { base: '#E9DCC0', edge: '#9C8660', line: '#B8862B', dash: [2, 7], glow: null },
      control,
      landmark: { x: 360, y: 300, name: 'Eyfel Kulesi', icon: '🗼' },
      labels: [['Sen Nehri', 120, 345], ['Louvre', 770, 318], ['Zafer Takı', 550, 412]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1889);
        // Warm evening limestone city
        const g = ctx.createRadialGradient(center[0], center[1], 80, center[0], center[1], 900);
        g.addColorStop(0, '#CDB48C');
        g.addColorStop(1, '#8C7357');
        ctx.fillStyle = g;
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);

        // Radial avenues from the Étoile
        ctx.strokeStyle = 'rgba(250, 236, 205, 0.55)';
        ctx.lineWidth = 9;
        for (let i = 0; i < 12; i++) {
          const a = (i / 12) * Math.PI * 2;
          ctx.beginPath();
          ctx.moveTo(center[0] + Math.cos(a) * 46, center[1] + Math.sin(a) * 30);
          ctx.lineTo(center[0] + Math.cos(a) * 1200, center[1] + Math.sin(a) * 800);
          ctx.stroke();
        }
        // Ring boulevard
        ctx.lineWidth = 7;
        ctx.beginPath(); ctx.ellipse(center[0], center[1], 250, 150, 0, 0, Math.PI * 2); ctx.stroke();

        // Seine
        ctx.save();
        bandPath(ctx, seine);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#E3D2AE';
        ctx.lineWidth = seineW + 12;
        ctx.stroke();
        ctx.strokeStyle = '#3E6E8C';
        ctx.lineWidth = seineW;
        ctx.stroke();
        ctx.strokeStyle = 'rgba(255, 230, 170, 0.22)';
        ctx.lineWidth = 2;
        ctx.setLineDash([12, 18]);
        ctx.stroke();
        ctx.restore();

        // Champ de Mars + Tuileries gardens
        ctx.fillStyle = '#6C8A4A';
        ctx.beginPath(); ctx.roundRect(300, 300, 120, 44, 10); ctx.fill();
        ctx.beginPath(); ctx.roundRect(700, 250, 140, 46, 10); ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.35)';
        ctx.lineWidth = 1;
        ctx.strokeRect(312, 310, 96, 24);

        // Plane trees lining the Seine
        for (let i = 0; i < seine.length - 1; i++) {
          const a = seine[i], b = seine[i + 1];
          for (let k = 0; k < 6; k++) {
            const f = k / 6;
            const x = a[0] + (b[0] - a[0]) * f;
            const y = a[1] + (b[1] - a[1]) * f;
            [-1, 1].forEach(side => {
              const ty = y + side * (seineW / 2 + 14);
              if (distToPolyline(x, ty, track) > 42) tree(ctx, x, ty, 5.5, '#4F6E36', '#6B8C48');
            });
          }
        }

        // Haussmann blocks: cream stone, slate mansard roofs, ironwork balconies
        // Haussmann blocks follow the radial avenues: place on a grid, then skip
        // anything sitting on an avenue so the star pattern stays readable
        const onAvenue = (x, y) => {
          const a = Math.atan2((y - center[1]) / 0.66, x - center[0]);
          const step = (Math.PI * 2) / 12;
          const off = Math.abs(((a % step) + step) % step - step / 2);
          const d = Math.hypot(x - center[0], (y - center[1]) / 0.66);
          return (step / 2 - off) * d < 14;
        };
        const blocks = gridScatter(rand, {
          x0: -PAD + 20, x1: W + PAD - 20, y0: -PAD + 30, y1: H + PAD,
          step: [32, 26], jitter: 4, skip: 0.45,
          isLand: (x, y) => isLand(x, y) && !onAvenue(x, y), track, clear: 50,
          keepOut: [[360, 250, 90], [550, 360, 62], [770, 290, 82], [360, 320, 70]]
        });
        blocks.forEach(b => {
          const w = 22 + b.r * 6;
          const h = 15 + b.r * 9;
          block(ctx, b.x - w / 2, b.y, w, h, 15, { front: '#EFE3C8', side: '#D6C6A3', top: '#5E6B7D' }, rand,
            { lit: 0.5, on: '#FFD58A', off: '#8F9AA8' });
          // Wrought-iron balcony line
          ctx.fillStyle = '#3A4250';
          ctx.fillRect(b.x - w / 2, b.y - h + 7, w, 1.2);
        });

        drawArc(ctx, center[0], center[1] + 8);
        drawPyramid(ctx, 770, 300);
        drawEiffel(ctx, 360, 300);
      },
      drawDynamic(ctx, t) {
        // Eiffel sparkle on the hour (here: in waves)
        const wave = (Math.sin(t * 0.7) + 1) / 2;
        if (wave > 0.55) {
          ctx.fillStyle = 'rgba(255, 252, 230, 0.95)';
          for (let i = 0; i < 26; i++) {
            const k = (i * 7919 + Math.floor(t * 12)) % 100 / 100;
            const yy = 300 - k * 180;
            const half = 26 * (1 - k) + 3;
            const xx = 360 + (((i * 31 + Math.floor(t * 20)) % 100) / 50 - 1) * half;
            ctx.fillRect(xx, yy, 2, 2);
          }
        }
        // Bateaux-mouches on the Seine
        for (let i = 0; i < 2; i++) {
          const f = ((t * 0.02) + i * 0.5) % 1;
          const seg = Math.min(seineDense.length - 1, Math.floor(f * seineDense.length));
          const p = seineDense[seg];
          ctx.save();
          ctx.translate(p.x, p.y + (i ? -8 : 8));
          ctx.fillStyle = '#F4EBDD';
          ctx.beginPath(); ctx.roundRect(-16, -5, 32, 10, 5); ctx.fill();
          ctx.fillStyle = '#FFD58A';
          for (let k = -12; k <= 10; k += 5) ctx.fillRect(k, -2, 3, 3);
          ctx.restore();
        }
      }
    };
    // Dense Seine path for the boats
    const seineDense = [];
    for (let i = 0; i < seine.length - 1; i++) {
      for (let k = 0; k < 20; k++) {
        const f = k / 20;
        seineDense.push({ x: seine[i][0] + (seine[i + 1][0] - seine[i][0]) * f, y: seine[i][1] + (seine[i + 1][1] - seine[i][1]) * f });
      }
    }
  })();

  function drawEiffel(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.3)';
    ctx.beginPath(); ctx.ellipse(0, 4, 44, 10, 0, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#6B4F34';
    ctx.fillStyle = '#7A5B3C';
    ctx.lineWidth = 2.2;
    // Legs with arch
    ctx.beginPath();
    ctx.moveTo(-34, 0); ctx.quadraticCurveTo(-14, -70, -6, -180); ctx.lineTo(6, -180); ctx.quadraticCurveTo(14, -70, 34, 0);
    ctx.lineTo(22, 0); ctx.quadraticCurveTo(0, -36, -22, 0); ctx.closePath();
    ctx.fill();
    // Lattice
    ctx.strokeStyle = 'rgba(255, 220, 160, 0.35)';
    ctx.lineWidth = 0.8;
    for (let k = 0; k < 14; k++) {
      const yy = -k * 12;
      const half = 30 * (1 - k / 15) + 4;
      ctx.beginPath(); ctx.moveTo(-half, yy); ctx.lineTo(half, yy - 12); ctx.moveTo(half, yy); ctx.lineTo(-half, yy - 12); ctx.stroke();
    }
    ctx.fillStyle = '#5A412A';
    ctx.fillRect(-24, -44, 48, 5);
    ctx.fillRect(-13, -104, 26, 4);
    ctx.fillRect(-2, -196, 4, 18);
    ctx.fillStyle = '#FFD58A';
    ctx.beginPath(); ctx.arc(0, -198, 2.5, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function drawArc(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.3)';
    ctx.beginPath(); ctx.ellipse(4, 2, 34, 9, 0, 0, Math.PI * 2); ctx.fill();
    block(ctx, -26, 0, 52, 44, 14, { front: '#EFE3C8', side: '#D2BE97', top: '#E6D6B4' });
    ctx.fillStyle = '#5B4A3A';
    ctx.beginPath(); ctx.moveTo(-9, 0); ctx.lineTo(-9, -22); ctx.arc(0, -22, 9, Math.PI, 0); ctx.lineTo(9, 0); ctx.closePath(); ctx.fill();
    ctx.fillStyle = '#D2BE97';
    ctx.fillRect(-26, -38, 52, 3);
    ctx.restore();
  }

  function drawPyramid(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    polyFill(ctx, [[-30, 0], [0, -34], [30, 0]], 'rgba(170, 215, 235, 0.85)');
    polyFill(ctx, [[0, -34], [30, 0], [10, 6]], 'rgba(120, 170, 200, 0.85)');
    ctx.strokeStyle = 'rgba(255,255,255,0.6)';
    ctx.lineWidth = 0.7;
    for (let i = 1; i < 5; i++) {
      ctx.beginPath(); ctx.moveTo(-30 + i * 6, -i * 6.8); ctx.lineTo(30 - i * 6, -i * 6.8); ctx.stroke();
    }
    ctx.restore();
  }

  /* ============================== NEW YORK ============================== */
  (function () {
    const ANG = -0.22; // Manhattan's grid tilt
    const cx = 560, cy = 380;
    const rot = (x, y) => {
      const c = Math.cos(ANG), s = Math.sin(ANG);
      return [Math.round(cx + x * c - y * s), Math.round(cy + x * s + y * c)];
    };
    const manhattan = [rot(-600, -200), rot(520, -250), rot(620, -150), rot(600, 170), rot(480, 260), rot(-420, 280), rot(-520, 240), rot(-640, 60)];
    const brooklyn = [[860, 700], [1000, 610], [W + PAD, 560], [W + PAD, H + PAD], [760, H + PAD]];
    const liberty = [[110, 690], [150, 668], [190, 684], [168, 712], [124, 714]];
    const park = [rot(-170, -95), rot(250, -95), rot(250, 70), rot(-170, 70)];
    const isLand = (x, y) => inPoly(x, y, manhattan) || inPoly(x, y, brooklyn);

    // Track: avenues around Central Park, down to the financial district and back
    const control = [
      rot(-470, 190), rot(-500, 40), rot(-430, -120), rot(-250, -175), rot(-40, -185), rot(170, -185), rot(380, -178),
      rot(470, -90), rot(480, 60), rot(420, 175), rot(250, 205), rot(40, 210), rot(-150, 215), rot(-320, 225)
    ];

    WORLDS.newyork = {
      title: 'New York',
      subtitle: 'Manhattan ızgarası',
      outside: '#2F6FA0',
      road: { base: '#3A3F47', edge: '#1D2026', line: '#FBBF24', dash: [12, 10], glow: null },
      control,
      landmark: { x: 150, y: 680, name: 'Özgürlük Heykeli', icon: '🗽' },
      labels: [['Central Park', ...rot(40, -10)], ['Brooklyn', 960, 690], ['Hudson', 120, 120]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1776);
        // Harbour
        const sea = ctx.createLinearGradient(0, -PAD, W, H + PAD);
        sea.addColorStop(0, '#3C86B8');
        sea.addColorStop(1, '#245E8A');
        ctx.fillStyle = sea;
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);
        waterTexture(ctx, rand, -PAD, -PAD, W + PAD, H + PAD, 'rgba(255,255,255,0.16)', 420);

        [manhattan, brooklyn, liberty].forEach(shape => {
          ctx.beginPath();
          shape.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
          ctx.closePath();
          ctx.strokeStyle = 'rgba(255,255,255,0.55)';
          ctx.lineWidth = 3;
          ctx.stroke();
          ctx.fillStyle = '#B9B5AA';
          ctx.fill();
        });

        // Street grid, clipped to Manhattan
        ctx.save();
        ctx.beginPath();
        manhattan.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        ctx.clip();
        ctx.translate(cx, cy);
        ctx.rotate(ANG);
        ctx.strokeStyle = '#8F8B82';
        ctx.lineWidth = 7;
        for (let gx = -700; gx < 700; gx += 90) { ctx.beginPath(); ctx.moveTo(gx, -400); ctx.lineTo(gx, 400); ctx.stroke(); }
        ctx.lineWidth = 3;
        for (let gy = -400; gy < 400; gy += 26) { ctx.beginPath(); ctx.moveTo(-700, gy); ctx.lineTo(700, gy); ctx.stroke(); }
        ctx.restore();

        // Central Park with lake and paths
        polyFill(ctx, park, '#5F9447');
        const lake = rot(60, -20);
        ctx.fillStyle = '#3C86B8';
        ctx.beginPath(); ctx.ellipse(lake[0], lake[1], 46, 24, ANG, 0, Math.PI * 2); ctx.fill();
        scatter(rand, 70, {
          x0: 330, x1: 860, y0: 200, y1: 520, isLand: (x, y) => inPoly(x, y, park),
          spacing: 14, keepOut: [[lake[0], lake[1], 52]]
        }).forEach(t => tree(ctx, t.x, t.y, 5 + t.r * 3, '#3F7A34', '#5E9E48'));

        // Skyscrapers with sharp afternoon shadows
        const towers = scatter(rand, 150, {
          x0: -PAD, x1: W + PAD, y0: -PAD, y1: H + PAD,
          isLand: (x, y) => isLand(x, y) && !inPoly(x, y, park), track, clear: 56, spacing: 24,
          keepOut: [[...rot(-330, 120), 50]]
        });
        towers.forEach(b => {
          const w = 16 + b.r * 12;
          const h = 26 + Math.pow(b.r, 2.2) * 90;
          ctx.fillStyle = 'rgba(30, 40, 60, 0.28)';
          polyFill(ctx, [[b.x - w / 2, b.y], [b.x + w / 2, b.y], [b.x + w / 2 + h * 0.5, b.y + h * 0.18], [b.x - w / 2 + h * 0.5, b.y + h * 0.18]], 'rgba(30, 40, 60, 0.25)');
          const glass = b.r > 0.5;
          block(ctx, b.x - w / 2, b.y, w, h, 11, glass
            ? { front: '#7FA7C9', side: '#5D84A8', top: '#C7D9E8' }
            : { front: '#C9B9A0', side: '#A7967D', top: '#E1D5C1' }, rand,
          { lit: 0.25, on: glass ? '#E6F3FF' : '#FFF1C9', off: glass ? '#6A92B6' : '#9C8C74' });
        });

        // Landmarks
        const es = rot(-330, 120);
        drawEmpireState(ctx, es[0], es[1]);
        drawLiberty(ctx, 150, 690);
        drawBrooklynBridge(ctx);
      },
      drawDynamic(ctx, t) {
        // Yellow cabs on the avenues (only on the island)
        ctx.save();
        ctx.beginPath();
        manhattan.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        ctx.clip();
        ctx.translate(cx, cy);
        ctx.rotate(ANG);
        for (let i = 0; i < 12; i++) {
          const lane = -630 + (i % 6) * 90 * 2 + 45 * 0;
          const dir = i % 2 ? 1 : -1;
          const yy = ((t * (40 + i * 6) * dir + i * 137) % 760 + 760) % 760 - 380;
          const xx = lane + (i > 5 ? 90 : 0);
          ctx.fillStyle = '#FBBF24';
          ctx.fillRect(xx - 3, yy - 6, 6, 12);
          ctx.fillStyle = '#1F2937';
          ctx.fillRect(xx - 2, yy - 2, 4, 3);
        }
        ctx.restore();
        // Liberty torch glow
        const a = (Math.sin(t * 2.5) + 1) / 2;
        ctx.fillStyle = `rgba(255, 200, 80, ${0.35 + a * 0.5})`;
        ctx.beginPath(); ctx.arc(141, 618, 5 + a * 2, 0, Math.PI * 2); ctx.fill();
        // Ferry to Liberty Island
        const f = (t * 0.03) % 1;
        const fx = 220 + f * 300;
        const fy = 690 - f * 120;
        ctx.fillStyle = '#F4EBDD';
        ctx.beginPath(); ctx.roundRect(fx - 10, fy - 4, 20, 8, 4); ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,0.5)';
        ctx.fillRect(fx - 22, fy - 1, 10, 2);
      }
    };
  })();

  function drawEmpireState(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(30, 40, 60, 0.3)';
    polyFill(ctx, [[-14, 0], [14, 0], [110, 34], [80, 34]], 'rgba(30, 40, 60, 0.3)');
    block(ctx, -16, 0, 32, 70, 14, { front: '#D9CDB6', side: '#B4A68C', top: '#ECE3D2' });
    block(ctx, -11, -70, 22, 50, 10, { front: '#D9CDB6', side: '#B4A68C', top: '#ECE3D2' });
    block(ctx, -7, -120, 14, 30, 8, { front: '#E3D8C3', side: '#BBAE95', top: '#F1EADD' });
    ctx.fillStyle = '#C0C7D1';
    ctx.fillRect(-2, -176, 4, 30);
    ctx.fillStyle = '#8B95A3';
    for (let k = -12; k <= 10; k += 5) ctx.fillRect(k, -66, 2, 60);
    ctx.restore();
  }

  function drawLiberty(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    block(ctx, -9, 0, 18, 16, 8, { front: '#B9A98D', side: '#9C8C70', top: '#CFC2A8' });
    ctx.fillStyle = '#6FB5A4';
    polyFill(ctx, [[-8, -16], [-5, -58], [5, -58], [8, -16]], '#6FB5A4');
    ctx.beginPath(); ctx.arc(0, -62, 5, 0, Math.PI * 2); ctx.fill();
    ctx.fillRect(-10, -82, 3, 22);
    ctx.strokeStyle = '#6FB5A4';
    ctx.lineWidth = 1.2;
    for (let k = -3; k <= 3; k++) { ctx.beginPath(); ctx.moveTo(0, -66); ctx.lineTo(k * 3, -72); ctx.stroke(); }
    ctx.restore();
  }

  function drawBrooklynBridge(ctx) {
    const a = [790, 610], b = [930, 655];
    ctx.save();
    ctx.strokeStyle = '#6B5E50';
    ctx.lineWidth = 8;
    ctx.beginPath(); ctx.moveTo(a[0] - 30, a[1] - 10); ctx.lineTo(b[0] + 30, b[1] + 10); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,255,255,0.7)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(a[0] - 30, a[1] - 20); ctx.quadraticCurveTo((a[0] + b[0]) / 2, (a[1] + b[1]) / 2 + 4, b[0] + 30, b[1]); ctx.stroke();
    [a, b].forEach(p => {
      ctx.fillStyle = '#8C7B66';
      ctx.fillRect(p[0] - 5, p[1] - 30, 10, 32);
      ctx.fillStyle = '#5B4E40';
      ctx.fillRect(p[0] - 2, p[1] - 24, 4, 10);
    });
    ctx.restore();
  }

  // ---------------------------------------------------------------------------
  // Precompute tracks
  // ---------------------------------------------------------------------------
  Object.keys(WORLDS).forEach(id => {
    const world = WORLDS[id];
    const dense = spline(world.control, 24);
    const sampled = resample(dense, 32);
    world.dense = dense;
    world.tiles = sampled.points;
    world.trackLength = sampled.length;
  });
  WORLDS.tropics = WORLDS.istanbul;
  WORLDS.cyber = WORLDS.tokyo;
  WORLDS.candy = WORLDS.paris;

  // Keep MAP_THEMES landmark data in sync (used for clicks and toasts)
  Object.keys(WORLDS).forEach(id => {
    if (MAP_THEMES[id]) MAP_THEMES[id].landmark = WORLDS[id].landmark;
  });

  function worldFor(themeId) {
    return WORLDS[themeId] || WORLDS.istanbul;
  }

  // ---------------------------------------------------------------------------
  // Static scenery cache
  // ---------------------------------------------------------------------------
  // The cache holds everything that never moves: scenery, a dimming veil that
  // pushes the scenery back behind the track, the road body and the soft edge
  // fade. Once built it is converted to an ImageBitmap (GPU-resident), and each
  // frame only the visible part of it is drawn.
  const cache = {};
  const bitmaps = {};
  function worldKey(world) {
    return Object.keys(WORLDS).find(k => WORLDS[k] === world);
  }

  function traceDense(ctx, dense, dy) {
    ctx.beginPath();
    dense.forEach((p, i) => (i ? ctx.lineTo(p.x, p.y + (dy || 0)) : ctx.moveTo(p.x, p.y + (dy || 0))));
    ctx.closePath();
  }

  // Rounded outline of the board's top face (inset > 0 moves inward, dy shifts down)
  function boardPath(ctx, inset, dy) {
    const i = inset || 0;
    ctx.beginPath();
    ctx.roundRect(-BM + i, -BM + i + (dy || 0), W + 2 * BM - 2 * i, H + 2 * BM - 2 * i, Math.max(4, BOARD_RADIUS - i));
  }
  // The printed map area inside the frame
  const MAP_INSET = 10;
  function mapAreaPath(ctx) {
    ctx.beginPath();
    ctx.roundRect(-MAP_INSET, -MAP_INSET, W + MAP_INSET * 2, H + MAP_INSET * 2, 14);
  }

  function compassRose(ctx, x, y, r) {
    ctx.save();
    ctx.translate(x, y);
    ctx.strokeStyle = '#7A5A35';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.stroke();
    ctx.beginPath(); ctx.arc(0, 0, r * 0.72, 0, Math.PI * 2); ctx.stroke();
    for (let i = 0; i < 4; i++) {
      ctx.save();
      ctx.rotate((i * Math.PI) / 2);
      polyFill(ctx, [[0, -r * 1.05], [r * 0.16, 0], [0, r * 0.08], [-r * 0.16, 0]], i === 0 ? '#C0392B' : '#7A5A35');
      ctx.restore();
      ctx.save();
      ctx.rotate((i * Math.PI) / 2 + Math.PI / 4);
      polyFill(ctx, [[0, -r * 0.62], [r * 0.1, 0], [-r * 0.1, 0]], '#B8862B');
      ctx.restore();
    }
    ctx.fillStyle = '#EFE2C4';
    ctx.beginPath(); ctx.arc(0, 0, r * 0.12, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function sceneryFor(themeId) {
    const world = worldFor(themeId);
    const key = worldKey(world);
    if (cache[key]) return bitmaps[key] || cache[key];
    const canvas = document.createElement('canvas');
    canvas.width = Math.round((W + PAD * 2) * CACHE_SCALE);
    canvas.height = Math.round((H + PAD * 2) * CACHE_SCALE);
    const ctx = canvas.getContext('2d');
    ctx.scale(CACHE_SCALE, CACHE_SCALE);
    ctx.translate(PAD, PAD);

    // 1. Soft cast shadow of the board on the felt
    ctx.save();
    ctx.shadowColor = 'rgba(5, 2, 12, 0.6)';
    ctx.shadowBlur = 46;
    ctx.shadowOffsetX = 10;
    ctx.shadowOffsetY = 26;
    boardPath(ctx, 0, THICK);
    ctx.fillStyle = '#1A1210';
    ctx.fill();
    ctx.restore();

    // 2. Board thickness (laminated edge)
    boardPath(ctx, 0, THICK);
    const edge = ctx.createLinearGradient(0, H, 0, H + BM + THICK);
    edge.addColorStop(0, '#4A3424');
    edge.addColorStop(1, '#23170F');
    ctx.fillStyle = edge;
    ctx.fill();
    ctx.strokeStyle = 'rgba(255, 220, 170, 0.08)';
    ctx.lineWidth = 1;
    for (let k = 4; k < THICK; k += 4) {
      boardPath(ctx, 0, k);
      ctx.stroke();
    }

    // 3. Top face: paper base, then the city map inside the frame
    boardPath(ctx, 0, 0);
    ctx.fillStyle = '#EFE2C4';
    ctx.fill();

    ctx.save();
    mapAreaPath(ctx);
    ctx.clip();
    world.drawStatic(ctx, world.dense);

    // Veil: quieter scenery so the track, tiles and pawns read first
    ctx.fillStyle = world.veil || 'rgba(24, 16, 38, 0.18)';
    ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);

    // Bridge piers wherever the track runs over water
    ctx.fillStyle = 'rgba(40, 30, 25, 0.55)';
    world.dense.forEach((p, i) => {
      if (i % 3 === 0 && !world.isLand(p.x, p.y)) ctx.fillRect(p.x - 3, p.y + 10, 6, 14);
    });

    // Road body (the flowing centre line is drawn live)
    const road = world.road;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    traceDense(ctx, world.dense, 8);
    ctx.strokeStyle = 'rgba(10, 6, 14, 0.35)';
    ctx.lineWidth = 36;
    ctx.stroke();
    traceDense(ctx, world.dense, 3);
    ctx.strokeStyle = road.edge;
    ctx.lineWidth = 33;
    ctx.stroke();
    if (road.glow) {
      ctx.save();
      ctx.shadowColor = road.glow;
      ctx.shadowBlur = 16;
      traceDense(ctx, world.dense);
      ctx.strokeStyle = road.glow;
      ctx.lineWidth = 31;
      ctx.stroke();
      ctx.restore();
    }
    traceDense(ctx, world.dense);
    ctx.strokeStyle = road.base;
    ctx.lineWidth = 29;
    ctx.stroke();
    // kerb highlight on the lit side
    traceDense(ctx, world.dense, -1);
    ctx.strokeStyle = 'rgba(255, 250, 235, 0.18)';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Lighting: warm key light from the top-left, cooler shade bottom-right, soft vignette
    const light = ctx.createLinearGradient(0, 0, W, H);
    light.addColorStop(0, 'rgba(255, 236, 200, 0.12)');
    light.addColorStop(0.5, 'rgba(255, 236, 200, 0)');
    light.addColorStop(1, 'rgba(12, 6, 24, 0.18)');
    ctx.fillStyle = light;
    ctx.fillRect(-MAP_INSET, -MAP_INSET, W + MAP_INSET * 2, H + MAP_INSET * 2);
    const vig = ctx.createRadialGradient(W / 2, H / 2, H * 0.45, W / 2, H / 2, W * 0.72);
    vig.addColorStop(0, 'rgba(10, 5, 20, 0)');
    vig.addColorStop(1, 'rgba(10, 5, 20, 0.26)');
    ctx.fillStyle = vig;
    ctx.fillRect(-MAP_INSET, -MAP_INSET, W + MAP_INSET * 2, H + MAP_INSET * 2);
    ctx.restore();

    // 4. Printed frame: neat line around the map, a brass rule, corner ornaments
    mapAreaPath(ctx);
    ctx.strokeStyle = '#5E4529';
    ctx.lineWidth = 2.5;
    ctx.stroke();
    boardPath(ctx, 12, 0);
    ctx.strokeStyle = '#B8862B';
    ctx.lineWidth = 1.5;
    ctx.stroke();
    boardPath(ctx, 17, 0);
    ctx.strokeStyle = 'rgba(122, 90, 53, 0.45)';
    ctx.lineWidth = 1;
    ctx.setLineDash([2, 4]);
    ctx.stroke();
    ctx.setLineDash([]);
    [[-BM / 2 - 2, -BM / 2 - 2], [W + BM / 2 + 2, -BM / 2 - 2], [-BM / 2 - 2, H + BM / 2 + 2]].forEach(([cx, cy]) => {
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(Math.PI / 4);
      ctx.fillStyle = '#B8862B';
      ctx.fillRect(-4, -4, 8, 8);
      ctx.restore();
    });
    compassRose(ctx, W + BM / 2 + 2, H + BM / 2 + 2, 15);

    // 5. Bevel: a light catch on the top-left edge of the board
    boardPath(ctx, 0.75, 0);
    const bevel = ctx.createLinearGradient(-BM, -BM, W, H);
    bevel.addColorStop(0, 'rgba(255, 255, 255, 0.7)');
    bevel.addColorStop(0.5, 'rgba(255, 255, 255, 0.15)');
    bevel.addColorStop(1, 'rgba(0, 0, 0, 0.25)');
    ctx.strokeStyle = bevel;
    ctx.lineWidth = 1.5;
    ctx.stroke();

    cache[key] = canvas;
    if (typeof createImageBitmap === 'function') {
      createImageBitmap(canvas).then(bmp => { bitmaps[key] = bmp; }).catch(() => {});
    }
    return canvas;
  }

  // Felt table texture shared by every board
  let feltCanvas = null;
  function feltTile() {
    if (feltCanvas) return feltCanvas;
    const c = document.createElement('canvas');
    c.width = 128;
    c.height = 128;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#1E1530';
    ctx.fillRect(0, 0, 128, 128);
    const rand = seeded(7);
    for (let i = 0; i < 900; i++) {
      const v = rand();
      ctx.fillStyle = v > 0.5 ? 'rgba(255, 255, 255, 0.025)' : 'rgba(0, 0, 0, 0.05)';
      ctx.fillRect(Math.floor(rand() * 128), Math.floor(rand() * 128), 1, 1 + Math.floor(rand() * 2));
    }
    feltCanvas = c;
    return c;
  }

  // Raw (un-veiled) scenery for menu thumbnails
  function rawCanvasFor(themeId) {
    const world = worldFor(themeId);
    sceneryFor(themeId);
    return cache[worldKey(world)];
  }

  // ---------------------------------------------------------------------------
  // Tile icons (vector, so every platform renders them identically)
  // ---------------------------------------------------------------------------
  const TYPE_STYLE = {
    start: { color: '#2BB3A3' },
    coin: { color: '#3E7BD8' },
    mystery: { color: '#8A5CD1' },
    trap: { color: '#C2373C', hazard: true },
    shop: { color: '#E0962E' },
    star: { color: '#E8B44A' },
    warp: { color: '#1FA0C2' },
    duel: { color: '#D2457E' },
    tax: { color: '#B23A5A', hazard: true },
    bank: { color: '#2F9D6A' },
    jail: { color: '#7A3036', hazard: true },
    freeze: { color: '#4B8FD6', hazard: true },
    reverse: { color: '#7D4BC2', hazard: true },
    slip: { color: '#C99A1E', hazard: true }
  };

  function starPath(ctx, x, y, r, inner) {
    ctx.beginPath();
    for (let i = 0; i < 10; i++) {
      const a = -Math.PI / 2 + (i * Math.PI) / 5;
      const rr = i % 2 ? r * (inner || 0.45) : r;
      const px = x + Math.cos(a) * rr;
      const py = y + Math.sin(a) * rr;
      if (i) ctx.lineTo(px, py); else ctx.moveTo(px, py);
    }
    ctx.closePath();
  }

  function drawIcon(ctx, type, x, y, tile) {
    const ink = '#2B1D12';
    ctx.save();
    ctx.translate(x, y);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = ink;
    ctx.fillStyle = ink;
    ctx.lineWidth = 2;
    switch (type) {
      case 'coin':
      case 'bank': {
        // Embossed gold coin (no numbers on the board; the tile inspector shows amounts)
        ctx.fillStyle = '#A97A22';
        ctx.beginPath(); ctx.arc(0.8, 1, 9, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#E8B44A';
        ctx.beginPath(); ctx.arc(0, 0, 9, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = '#C9952F'; ctx.lineWidth = 1.2;
        ctx.beginPath(); ctx.arc(0, 0, 6, 0, Math.PI * 2); ctx.stroke();
        if (type === 'bank') {
          ctx.fillStyle = '#A97A22';
          ctx.fillRect(-4, -1, 8, 5);
          polyFill(ctx, [[-5, -1], [0, -5], [5, -1]], '#A97A22');
        }
        ctx.fillStyle = 'rgba(255, 250, 225, 0.8)';
        ctx.beginPath(); ctx.arc(-3, -3.5, 2, 0, Math.PI * 2); ctx.fill();
        break;
      }
      case 'star':
        starPath(ctx, 0, 0, 10);
        ctx.fillStyle = '#E8B44A'; ctx.fill();
        ctx.strokeStyle = '#A97A22'; ctx.lineWidth = 1.4; ctx.stroke();
        break;
      case 'start':
        ctx.strokeStyle = ink; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(-6, 9); ctx.lineTo(-6, -9); ctx.stroke();
        for (let r = 0; r < 2; r++) for (let c = 0; c < 3; c++) {
          ctx.fillStyle = (r + c) % 2 ? '#FFF4DE' : ink;
          ctx.fillRect(-5 + c * 4, -9 + r * 4, 4, 4);
        }
        break;
      case 'mystery':
        ctx.fillStyle = '#8A5CD1';
        ctx.fillRect(-8, -3, 16, 11);
        ctx.fillStyle = '#B88CF0';
        ctx.fillRect(-9, -7, 18, 5);
        ctx.fillStyle = '#E8B44A';
        ctx.fillRect(-1.5, -7, 3, 15);
        ctx.beginPath(); ctx.ellipse(-3, -9, 3, 2, -0.5, 0, Math.PI * 2); ctx.ellipse(3, -9, 3, 2, 0.5, 0, Math.PI * 2); ctx.fill();
        break;
      case 'shop':
        ctx.fillStyle = '#E0962E';
        ctx.beginPath(); ctx.roundRect(-8, -4, 16, 13, 2); ctx.fill();
        ctx.strokeStyle = ink; ctx.lineWidth = 1.6;
        ctx.beginPath(); ctx.arc(0, -4, 4.5, Math.PI, 0); ctx.stroke();
        break;
      case 'trap':
        ctx.fillStyle = '#C2373C';
        for (let i = -1; i <= 1; i++) {
          ctx.beginPath(); ctx.moveTo(i * 6 - 4, 7); ctx.lineTo(i * 6, -8); ctx.lineTo(i * 6 + 4, 7); ctx.closePath(); ctx.fill();
        }
        break;
      case 'warp':
        ctx.strokeStyle = '#1FA0C2'; ctx.lineWidth = 2.2;
        ctx.beginPath();
        for (let a = 0; a < Math.PI * 5; a += 0.2) {
          const r = 1 + a * 0.55;
          const px = Math.cos(a) * r, py = Math.sin(a) * r;
          if (a) ctx.lineTo(px, py); else ctx.moveTo(px, py);
        }
        ctx.stroke();
        break;
      case 'duel':
        ctx.strokeStyle = ink; ctx.lineWidth = 2.4;
        ctx.beginPath(); ctx.moveTo(-8, 8); ctx.lineTo(8, -8); ctx.moveTo(8, 8); ctx.lineTo(-8, -8); ctx.stroke();
        ctx.strokeStyle = '#D2457E'; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(-9, 3); ctx.lineTo(-3, 9); ctx.moveTo(9, 3); ctx.lineTo(3, 9); ctx.stroke();
        break;
      case 'jail':
        ctx.strokeStyle = ink; ctx.lineWidth = 2;
        ctx.strokeRect(-8, -8, 16, 16);
        for (let i = -4; i <= 4; i += 4) { ctx.beginPath(); ctx.moveTo(i, -8); ctx.lineTo(i, 8); ctx.stroke(); }
        break;
      case 'tax':
        ctx.fillStyle = '#E8B44A';
        ctx.beginPath(); ctx.arc(0, 0, 9, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = '#B23A5A'; ctx.lineWidth = 2.4;
        ctx.beginPath(); ctx.moveTo(-4, 0); ctx.lineTo(4, 0); ctx.stroke();
        break;
      case 'freeze':
        ctx.strokeStyle = '#2F6FB8'; ctx.lineWidth = 2;
        for (let i = 0; i < 3; i++) {
          ctx.save(); ctx.rotate((i * Math.PI) / 3);
          ctx.beginPath(); ctx.moveTo(0, -10); ctx.lineTo(0, 10); ctx.moveTo(-3, -7); ctx.lineTo(0, -4); ctx.lineTo(3, -7); ctx.stroke();
          ctx.restore();
        }
        break;
      case 'reverse':
      case 'slip':
        ctx.strokeStyle = '#7D4BC2'; ctx.lineWidth = 2.4;
        ctx.beginPath(); ctx.arc(0, 0, 7, -Math.PI * 0.2, Math.PI * 1.3); ctx.stroke();
        ctx.fillStyle = '#7D4BC2';
        ctx.beginPath(); ctx.moveTo(6, -9); ctx.lineTo(10, -1); ctx.lineTo(1, -3); ctx.closePath(); ctx.fill();
        break;
      default:
        ctx.beginPath(); ctx.arc(0, 0, 4, 0, Math.PI * 2); ctx.fill();
    }
    ctx.restore();
  }

  // ---------------------------------------------------------------------------
  // Patch BoardRenderer
  // ---------------------------------------------------------------------------
  const P = BoardRenderer.prototype;
  const origSetTheme = P.setTheme;

  P.calculateTilePositions = function () {
    return worldFor(this.activeTheme || 'istanbul').tiles.map(p => ({ x: p.x, y: p.y }));
  };

  P.setTheme = function (themeId) {
    const before = this.activeTheme;
    origSetTheme.call(this, themeId);
    if (this.activeTheme !== before || !this.__worldReady) {
      this.__worldReady = true;
      this.tileCoords = this.calculateTilePositions();
      sceneryFor(this.activeTheme); // warm the cache
    }
  };

  P.drawEnvironment = function (ctx) {
    const world = worldFor(this.activeTheme);
    if (!this.__worldReady) {
      this.__worldReady = true;
      this.tileCoords = this.calculateTilePositions();
    }
    const img = sceneryFor(this.activeTheme);
    ctx.save();
    // The felt table under the board
    if (!this.__feltPattern) this.__feltPattern = ctx.createPattern(feltTile(), 'repeat');
    ctx.fillStyle = this.__feltPattern || '#1E1530';
    ctx.fillRect(-3000, -3000, 7100, 6800);

    // Draw only the part of the cached scenery that is on screen
    const m = ctx.getTransform();
    const inv = m.inverse();
    const cw = ctx.canvas.width;
    const ch = ctx.canvas.height;
    const corners = [[0, 0], [cw, 0], [0, ch], [cw, ch]].map(([x, y]) => ({
      x: inv.a * x + inv.c * y + inv.e,
      y: inv.b * x + inv.d * y + inv.f
    }));
    let vx0 = Math.min(...corners.map(c => c.x)) - 4;
    let vy0 = Math.min(...corners.map(c => c.y)) - 4;
    let vx1 = Math.max(...corners.map(c => c.x)) + 4;
    let vy1 = Math.max(...corners.map(c => c.y)) + 4;
    vx0 = Math.max(vx0, -PAD); vy0 = Math.max(vy0, -PAD);
    vx1 = Math.min(vx1, W + PAD); vy1 = Math.min(vy1, H + PAD);
    if (vx1 > vx0 && vy1 > vy0) {
      ctx.drawImage(img,
        (vx0 + PAD) * CACHE_SCALE, (vy0 + PAD) * CACHE_SCALE, (vx1 - vx0) * CACHE_SCALE, (vy1 - vy0) * CACHE_SCALE,
        vx0, vy0, vx1 - vx0, vy1 - vy0);
    }

    // Moving details live on the printed map only (they slide under the frame)
    if (world.drawDynamic) {
      ctx.save();
      mapAreaPath(ctx);
      ctx.clip();
      world.drawDynamic(ctx, performance.now() / 1000);
      ctx.restore();
    }
    ctx.restore();
  };

  // Frame the whole board (with a little felt around it) instead of just the map
  const origResize = P.resizeCanvas;
  P.resizeCanvas = function () {
    this.virtualWidth = W + BM * 2 + 70;
    this.virtualHeight = H + BM * 2 + THICK + 60;
    origResize.call(this);
  };

  // Only the flowing centre line is live; the road body is in the cache
  P.drawPathways = function (ctx) {
    const world = worldFor(this.activeTheme);
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    traceDense(ctx, world.dense);
    ctx.setLineDash(world.road.dash);
    ctx.lineDashOffset = -(performance.now() / 60) % 100;
    ctx.strokeStyle = world.road.line;
    ctx.lineWidth = 2.5;
    ctx.stroke();
    ctx.restore();
  };

  // ---------------------------------------------------------------------------
  // Tile sprites: each kind of space is drawn once, then blitted every frame
  // ---------------------------------------------------------------------------
  const TILE_R = 25;
  const TILE_RY = TILE_R * 0.74;   // a rounder chip reads less "squashed"
  const TILE_DEPTH = 9;
  const SPRITE_W = 66;
  const SPRITE_H = 64;
  const spriteCache = {};

  function darken(hex, f) {
    const n = parseInt(hex.slice(1), 16);
    const r = Math.round(((n >> 16) & 255) * (1 - f));
    const g = Math.round(((n >> 8) & 255) * (1 - f));
    const b = Math.round((n & 255) * (1 - f));
    return `rgb(${r}, ${g}, ${b})`;
  }

  function tileSprite(tile) {
    const key = `${tile.type}|${tile.amount || ''}`;
    if (spriteCache[key]) return spriteCache[key];
    const style = TYPE_STYLE[tile.type] || { color: '#3E7BD8' };
    const c = document.createElement('canvas');
    c.width = SPRITE_W * SPRITE_SCALE;
    c.height = SPRITE_H * SPRITE_SCALE;
    const ctx = c.getContext('2d');
    ctx.scale(SPRITE_SCALE, SPRITE_SCALE);
    const x = SPRITE_W / 2;
    const y = SPRITE_H / 2 - 6;
    const r = TILE_R;
    const ry = TILE_RY;

    // Contact shadow toward the bottom-right
    ctx.fillStyle = 'rgba(10, 6, 14, 0.38)';
    ctx.beginPath(); ctx.ellipse(x + SHADOW_DX, y + TILE_DEPTH + SHADOW_DY, r + 1, ry, 0, 0, Math.PI * 2); ctx.fill();

    // Chip edge (thickness) with the classic striped inserts
    ctx.fillStyle = darken(style.color, 0.42);
    ctx.beginPath(); ctx.ellipse(x, y + TILE_DEPTH, r, ry, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillRect(x - r, y, r * 2, TILE_DEPTH);
    ctx.fillStyle = 'rgba(255, 244, 222, 0.85)';
    for (let k = 1; k <= 7; k += 2) {
      const a = (k / 8) * Math.PI;
      const ex = x + Math.cos(a) * r;
      const ey = y + Math.sin(a) * ry;
      ctx.fillRect(ex - 2, ey + 1.5, 4, TILE_DEPTH - 3);
    }

    // Top face
    const face = ctx.createLinearGradient(x - r, y - ry, x + r, y + ry);
    face.addColorStop(0, shade(style.color, 0.14));
    face.addColorStop(1, darken(style.color, 0.18));
    ctx.fillStyle = face;
    ctx.beginPath(); ctx.ellipse(x, y, r, ry, 0, 0, Math.PI * 2); ctx.fill();

    // Inlaid ring of edge spots (hazards get a warning-yellow ring)
    ctx.save();
    ctx.setLineDash([5.5, 4.5]);
    ctx.strokeStyle = style.hazard ? 'rgba(255, 214, 110, 0.95)' : 'rgba(255, 244, 222, 0.9)';
    ctx.lineWidth = 2.6;
    ctx.beginPath(); ctx.ellipse(x, y, r - 3.5, ry - 3, 0, 0, Math.PI * 2); ctx.stroke();
    ctx.restore();

    // Card-stock medallion with the vector icon
    ctx.fillStyle = '#FFF4DE';
    ctx.beginPath(); ctx.ellipse(x, y, 14, 11.5, 0, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = 'rgba(43, 29, 18, 0.18)';
    ctx.lineWidth = 1;
    ctx.stroke();
    drawIcon(ctx, tile.type, x, y, tile);

    // Gloss on the lit side
    ctx.save();
    ctx.beginPath(); ctx.ellipse(x, y, r, ry, 0, 0, Math.PI * 2); ctx.clip();
    const gloss = ctx.createLinearGradient(x - r, y - ry, x, y);
    gloss.addColorStop(0, 'rgba(255, 255, 255, 0.28)');
    gloss.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.fillStyle = gloss;
    ctx.fillRect(x - r, y - ry, r * 2, ry * 2);
    ctx.restore();
    ctx.strokeStyle = 'rgba(255, 250, 235, 0.55)';
    ctx.lineWidth = 1.2;
    ctx.beginPath(); ctx.ellipse(x, y, r, ry, 0, Math.PI * 0.95, Math.PI * 1.75); ctx.stroke();

    spriteCache[key] = c;
    return c;
  }

  // Pre-rendered halo for the active star pedestal
  let haloSprite = null;
  function starHalo() {
    if (haloSprite) return haloSprite;
    const size = 150;
    const c = document.createElement('canvas');
    c.width = size;
    c.height = size;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(size / 2, size / 2, 10, size / 2, size / 2, size / 2);
    g.addColorStop(0, 'rgba(255, 214, 120, 0.75)');
    g.addColorStop(1, 'rgba(255, 214, 120, 0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, size, size);
    haloSprite = c;
    return c;
  }

  P.drawPlatformTile = function (ctx, index, tile, pos, isStarTile, isSelected, hasTrap) {
    const pulse = this.starPulse || 0;
    if (isStarTile) {
      const s = 132 + Math.sin(pulse * 1.5) * 10;
      ctx.drawImage(starHalo(), pos.x - s / 2, pos.y - s / 2, s, s);
    }
    ctx.drawImage(tileSprite(tile), pos.x - SPRITE_W / 2, pos.y - SPRITE_H / 2 + 6, SPRITE_W, SPRITE_H);

    if (isStarTile) {
      ctx.save();
      ctx.strokeStyle = '#FFE08A';
      ctx.lineWidth = 3;
      ctx.beginPath(); ctx.ellipse(pos.x, pos.y, TILE_R, TILE_RY, 0, 0, Math.PI * 2); ctx.stroke();
      const bob = Math.sin(pulse * 1.5) * 5;
      starPath(ctx, pos.x, pos.y - 44 + bob, 15);
      ctx.fillStyle = '#E8B44A';
      ctx.fill();
      ctx.strokeStyle = '#FFF4DE';
      ctx.lineWidth = 2;
      ctx.stroke();
      ctx.restore();
    }

    if (isSelected) {
      ctx.save();
      ctx.strokeStyle = '#FFF4DE';
      ctx.lineWidth = 2.5;
      ctx.setLineDash([5, 4]);
      ctx.beginPath(); ctx.ellipse(pos.x, pos.y + 2, TILE_R + 8, TILE_RY + 7, 0, 0, Math.PI * 2); ctx.stroke();
      ctx.restore();
    }

    if (hasTrap) {
      ctx.save();
      ctx.fillStyle = '#F6D24A';
      ctx.strokeStyle = '#6B4A12';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(pos.x + 8, pos.y - 26);
      ctx.quadraticCurveTo(pos.x + 22, pos.y - 20, pos.x + 20, pos.y - 6);
      ctx.quadraticCurveTo(pos.x + 14, pos.y - 16, pos.x + 8, pos.y - 26);
      ctx.fill();
      ctx.stroke();
      ctx.restore();
    }
  };

  // Landmarks are part of the illustration; no floating name plates
  P.drawLandmark = function () {};
  // No spotlight beam: the active pawn's name tag already marks whose turn it is
  P.drawTurnSpotlight = function () {};

  // Possible landing spots for your own roll: quiet brass rings on the next 6 spaces
  P.drawStepTrajectory = function (ctx) {
    const state = window.CURRENT_ROOM_STATE;
    if (!state || state.state !== 'playing' || !state.players) return;
    const active = state.players[state.turnIndex];
    const me = window.GameClient && window.GameClient.player;
    if (!active || !me || active.sessionId !== me.sessionId) return;
    const n = this.tileCoords.length;
    const a = 0.35 + (Math.sin((this.starPulse || 0) * 2) + 1) * 0.15;
    ctx.save();
    ctx.strokeStyle = `rgba(255, 224, 138, ${a})`;
    ctx.lineWidth = 2;
    for (let s = 1; s <= 6; s++) {
      const p = this.tileCoords[(active.position + s) % n];
      ctx.beginPath(); ctx.ellipse(p.x, p.y, TILE_R + 5, TILE_RY + 4, 0, 0, Math.PI * 2); ctx.stroke();
    }
    ctx.restore();
  };

  // ---------------------------------------------------------------------------
  // Pawns: glossy board-game pieces in the player's colour. No labels — the
  // active pawn gets a brass ring on the ground and a small marker above it.
  // ---------------------------------------------------------------------------
  P.renderMinifigToken = function (ctx, player, px, py, floorY, isClustered, sx, sy, hopArcY) {
    const state = window.CURRENT_ROOM_STATE;
    const active = state && state.players && state.players[state.turnIndex];
    const isCurrent = !!active && active.id === player.id;
    const color = (typeof player.color === 'string' && /^#[0-9a-fA-F]{6}$/.test(player.color)) ? player.color : '#E5484D';
    const pulse = (Math.sin((this.starPulse || 0) * 3) + 1) / 2;
    const lift = Math.max(0, -(hopArcY || 0));
    const fy = floorY + 4;

    ctx.save();
    // Shadow on the board shrinks while the pawn is in the air
    const sh = Math.max(0.45, 1 - lift / 48);
    ctx.fillStyle = `rgba(10, 6, 14, ${0.42 * sh})`;
    ctx.beginPath(); ctx.ellipse(px + SHADOW_DX + 2, fy + SHADOW_DY * 0.6, 13 * sh, 5.5 * sh, 0, 0, Math.PI * 2); ctx.fill();

    if (isCurrent) {
      ctx.strokeStyle = `rgba(232, 180, 74, ${0.55 + pulse * 0.4})`;
      ctx.lineWidth = 2.5;
      ctx.beginPath(); ctx.ellipse(px, fy, 17 + pulse * 3, 7 + pulse * 1.2, 0, 0, Math.PI * 2); ctx.stroke();
    }

    ctx.translate(px, py + 4);
    ctx.scale((sx || 1) * (isCurrent ? 1.08 : 0.94), (sy || 1) * (isCurrent ? 1.08 : 0.94));

    const dark = shade(color, -0.38);
    const light = shade(color, 0.38);
    const body = ctx.createLinearGradient(-11, 0, 11, 0);
    body.addColorStop(0, light);
    body.addColorStop(0.45, color);
    body.addColorStop(1, dark);

    // Base disc
    ctx.fillStyle = dark;
    ctx.beginPath(); ctx.ellipse(0, 0, 12, 4.8, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillRect(-12, -4, 24, 4);
    ctx.fillStyle = body;
    ctx.beginPath(); ctx.ellipse(0, -4, 12, 4.8, 0, 0, Math.PI * 2); ctx.fill();

    // Bell-shaped body
    ctx.beginPath();
    ctx.moveTo(-9.5, -5);
    ctx.bezierCurveTo(-9, -14, -3.2, -18, -3.4, -27);
    ctx.lineTo(3.4, -27);
    ctx.bezierCurveTo(3.2, -18, 9, -14, 9.5, -5);
    ctx.closePath();
    ctx.fillStyle = body;
    ctx.fill();
    ctx.strokeStyle = 'rgba(20, 10, 14, 0.28)';
    ctx.lineWidth = 0.8;
    ctx.stroke();

    // Collar
    ctx.fillStyle = shade(color, -0.15);
    ctx.beginPath(); ctx.ellipse(0, -27.5, 6.5, 2.4, 0, 0, Math.PI * 2); ctx.fill();

    // Head with a specular highlight
    const head = ctx.createRadialGradient(-2.6, -37.5, 1, 0, -34.5, 8.5);
    head.addColorStop(0, shade(color, 0.6));
    head.addColorStop(0.5, color);
    head.addColorStop(1, dark);
    ctx.fillStyle = head;
    ctx.beginPath(); ctx.arc(0, -34.5, 7.6, 0, Math.PI * 2); ctx.fill();
    ctx.stroke();
    ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
    ctx.beginPath(); ctx.ellipse(-2.8, -37.4, 2, 1.4, -0.5, 0, Math.PI * 2); ctx.fill();
    // Body sheen
    ctx.fillStyle = 'rgba(255, 255, 255, 0.22)';
    ctx.beginPath(); ctx.ellipse(-4.5, -13, 1.6, 6, 0.25, 0, Math.PI * 2); ctx.fill();

    // Turn marker: a small brass chevron bobbing above the head
    if (isCurrent) {
      const bob = Math.sin((this.starPulse || 0) * 3) * 2.2;
      ctx.translate(0, -54 + bob);
      ctx.beginPath();
      ctx.moveTo(-6, -4); ctx.lineTo(6, -4); ctx.lineTo(0, 4); ctx.closePath();
      ctx.fillStyle = '#E8B44A';
      ctx.fill();
      ctx.strokeStyle = '#FFF4DE';
      ctx.lineWidth = 1.5;
      ctx.stroke();
    }
    ctx.restore();
  };

  // Phones in portrait fit the whole world to the narrow width, which makes the
  // spaces tiny; start closer there (the camera still follows the active pawn)
  const origResetCamera = P.resetCamera;
  P.resetCamera = function () {
    origResetCamera.call(this);
    const w = this.canvas ? this.canvas.width / (this.dpr || 1) : window.innerWidth;
    if (w < 700) this.camera.zoom = 1.75;
  };

  // Do no drawing work while the board is not on screen
  const origDraw = P.draw;
  P.draw = function () {
    if (document.hidden || !this.canvas || this.canvas.offsetParent === null) return;
    origDraw.call(this);
  };

  // Public: map metadata for the UI (title cards, previews)
  window.WorldMaps = {
    info(themeId) {
      const w = worldFor(themeId);
      return { title: w.title, subtitle: w.subtitle };
    },
    // Small preview image of a city for menus (track included in the cache)
    preview(themeId, width) {
      const src = rawCanvasFor(themeId);
      const cw = width || 240;
      const ch = Math.round(cw * (H / W));
      const c = document.createElement('canvas');
      c.width = cw * 2;
      c.height = ch * 2;
      const ctx = c.getContext('2d');
      ctx.drawImage(src, PAD * CACHE_SCALE, PAD * CACHE_SCALE, W * CACHE_SCALE, H * CACHE_SCALE, 0, 0, c.width, c.height);
      c.style.width = `${cw}px`;
      c.style.height = `${ch}px`;
      return c;
    },
    _worlds: WORLDS
  };
})();


/* =================== 6. MINI GAMES =================== */
/**
 * Interactive Party Mini-Games Engine
 * Real-time mini-games with high stakes, zero-zoom mobile touch controls,
 * and 5 distinct game modes!
 */
class MiniGameManager {
  constructor() {
    this.container = document.getElementById('minigame-overlay');
    this.activeGame = null;
    this.timerInterval = null;
    this.score = 0;
    this.gameFinished = false;
  }

  start(minigameData) {
    if (!this.container) return;
    this.score = 0;
    this.gameFinished = false;
    this.activeGame = minigameData;
    this.container.classList.remove('hidden');

    switch (minigameData.id) {
      case 'coin_catch':
        this.runCoinCatch(minigameData);
        break;
      case 'reflex_pop':
        this.runReflexPop(minigameData);
        break;
      case 'balloon_mash':
        this.runBalloonMash(minigameData);
        break;
      case 'lucky_chests':
        this.runLuckyChests(minigameData);
        break;
      case 'memory_matrix':
        this.runMemoryMatrix(minigameData);
        break;
      case 'timing_hammer':
        this.runTimingHammer(minigameData);
        break;
      default:
        this.runCoinCatch(minigameData);
        break;
    }
  }

  end() {
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    }
    this.gameFinished = true;
    if (window.GameClient) {
      window.GameClient.sendMiniGameScore(this.score);
    }
  }

  close() {
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    }
    this.gameFinished = true;
    if (this.container) {
      this.container.classList.add('hidden');
      this.container.innerHTML = '';
    }
    this.activeGame = null;
  }

  /* ----------------------------------------------------
     MINI-GAME 1: GÖKTEN ALTIN YAĞMURU (Coin Rain Blitz)
     - Touch-action: none prevents double-tap zoom
     - Smooth continuous hold & direct touch drag support
     ---------------------------------------------------- */
  runCoinCatch(data) {
    let timeLeft = data.duration || 15;

    this.container.innerHTML = `
      <div class="minigame-card">
        <div class="minigame-header">
          <div class="mg-stakes-badge">🏆 1.ye +15 🪙 & Eşya! • 💀 Sonuncuya -5 🪙 Ceza!</div>
          <h2>🪙 ${data.title}</h2>
          <p>${data.desc}</p>
          <div class="minigame-stats">
            <div class="badge-time">⏳ Süre: <span id="mg-timer">${timeLeft}</span>s</div>
            <div class="badge-score">⭐ Skor: <span id="mg-score">0</span></div>
          </div>
        </div>
        <div class="coin-catch-arena" id="catch-arena" style="touch-action: none; user-select: none;">
          <div class="catch-basket" id="basket">🧺</div>
        </div>
        <div class="touch-controls-row" style="touch-action: none;">
          <button class="touch-btn" id="btn-left" style="touch-action: none; user-select: none;">◀ SOLA</button>
          <button class="touch-btn" id="btn-right" style="touch-action: none; user-select: none;">SAĞA ▶</button>
        </div>
      </div>
    `;

    const arena = document.getElementById('catch-arena');
    const basket = document.getElementById('basket');
    const timerElem = document.getElementById('mg-timer');
    const scoreElem = document.getElementById('mg-score');

    let basketX = 160;
    basket.style.left = `${basketX}px`;

    const moveBasket = (delta) => {
      if (this.gameFinished) return;
      const arenaW = arena.clientWidth || 360;
      basketX = Math.max(10, Math.min(arenaW - 60, basketX + delta));
      basket.style.left = `${basketX}px`;
    };

    // Keyboard support
    const handleKey = (e) => {
      if (e.key === 'ArrowLeft' || e.key === 'a') moveBasket(-28);
      if (e.key === 'ArrowRight' || e.key === 'd') moveBasket(28);
    };
    window.addEventListener('keydown', handleKey);

    // Direct touch / mouse drag across arena (Smooth finger tracking)
    const handlePointerMove = (clientX) => {
      const rect = arena.getBoundingClientRect();
      const relativeX = clientX - rect.left - 25;
      const arenaW = rect.width || 360;
      basketX = Math.max(10, Math.min(arenaW - 60, relativeX));
      basket.style.left = `${basketX}px`;
    };

    arena.addEventListener('pointermove', (e) => {
      if (e.buttons > 0 || e.pointerType === 'touch') {
        handlePointerMove(e.clientX);
      }
    });

    arena.addEventListener('pointerdown', (e) => {
      handlePointerMove(e.clientX);
    });

    // Continuous button hold-to-move without browser double-tap zoom
    let holdInterval = null;
    const startHold = (delta) => (e) => {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      moveBasket(delta);
      if (holdInterval) clearInterval(holdInterval);
      holdInterval = setInterval(() => moveBasket(delta), 50);
    };
    const stopHold = (e) => {
      if (e) { e.preventDefault(); }
      if (holdInterval) {
        clearInterval(holdInterval);
        holdInterval = null;
      }
    };

    const btnL = document.getElementById('btn-left');
    const btnR = document.getElementById('btn-right');

    btnL.addEventListener('pointerdown', startHold(-18));
    btnL.addEventListener('pointerup', stopHold);
    btnL.addEventListener('pointercancel', stopHold);
    btnL.addEventListener('pointerleave', stopHold);

    btnR.addEventListener('pointerdown', startHold(18));
    btnR.addEventListener('pointerup', stopHold);
    btnR.addEventListener('pointercancel', stopHold);
    btnR.addEventListener('pointerleave', stopHold);

    // Spawn falling items
    const items = [];
    const spawnItem = () => {
      if (this.gameFinished) return;
      const isBomb = Math.random() < 0.26;
      const isGem = !isBomb && Math.random() < 0.28;

      const itemElem = document.createElement('div');
      itemElem.className = 'falling-item';
      itemElem.innerHTML = isBomb ? '💣' : (isGem ? '💎' : '🪙');
      const startX = Math.random() * (arena.clientWidth - 40);
      itemElem.style.left = `${startX}px`;
      itemElem.style.top = '0px';
      arena.appendChild(itemElem);

      items.push({
        elem: itemElem,
        x: startX,
        y: 0,
        speed: 3 + Math.random() * 4,
        isBomb,
        isGem
      });
    };

    const spawnInterval = setInterval(spawnItem, 420);

    // Game loop for falling physics and collision
    let animId;
    const updatePhysics = () => {
      if (this.gameFinished) {
        cancelAnimationFrame(animId);
        return;
      }

      const basketRect = basket.getBoundingClientRect();

      for (let i = items.length - 1; i >= 0; i--) {
        const item = items[i];
        item.y += item.speed;
        item.elem.style.top = `${item.y}px`;

        const itemRect = item.elem.getBoundingClientRect();

        // Check Collision with basket
        if (
          itemRect.bottom >= basketRect.top &&
          itemRect.top <= basketRect.bottom &&
          itemRect.right >= basketRect.left &&
          itemRect.left <= basketRect.right
        ) {
          if (item.isBomb) {
            this.score = Math.max(0, this.score - 5);
            AudioPlayer.playTrapSound();
            basket.style.transform = 'scale(0.8) rotate(-15deg)';
            setTimeout(() => basket.style.transform = '', 200);
          } else if (item.isGem) {
            this.score += 4;
            AudioPlayer.playStarFanfare();
          } else {
            this.score += 2;
            AudioPlayer.playCoinSound();
          }

          scoreElem.innerText = this.score;
          item.elem.remove();
          items.splice(i, 1);
          continue;
        }

        // Out of bounds
        if (item.y > arena.clientHeight) {
          item.elem.remove();
          items.splice(i, 1);
        }
      }

      animId = requestAnimationFrame(updatePhysics);
    };

    animId = requestAnimationFrame(updatePhysics);

    // Countdown Timer
    this.timerInterval = setInterval(() => {
      timeLeft--;
      timerElem.innerText = timeLeft;
      AudioPlayer.playCountdown(timeLeft <= 3);

      if (timeLeft <= 0) {
        clearInterval(spawnInterval);
        stopHold();
        window.removeEventListener('keydown', handleKey);
        this.end();
      }
    }, 1000);
  }

  /* ----------------------------------------------------
     MINI-GAME 2: HIZLI PARMAK (Reflex Duel)
     ---------------------------------------------------- */
  runReflexPop(data) {
    this.container.innerHTML = `
      <div class="minigame-card text-center">
        <div class="mg-stakes-badge">🏆 1.ye +15 🪙 & Eşya! • 💀 Sonuncuya -5 🪙 Ceza!</div>
        <h2>⚡ ${data.title}</h2>
        <p>${data.desc}</p>
        <div class="reflex-pad wait-state" id="reflex-pad" style="touch-action: none; user-select: none;">
          <div class="reflex-icon" id="reflex-icon">🛑</div>
          <div class="reflex-msg" id="reflex-msg">BEKLE...</div>
          <div class="reflex-sub">Yeşil olduğunda hemen dokun!</div>
        </div>
        <div class="badge-score mt-3">Skorun: <span id="reflex-score">0</span></div>
      </div>
    `;

    const pad = document.getElementById('reflex-pad');
    const icon = document.getElementById('reflex-icon');
    const msg = document.getElementById('reflex-msg');
    const scoreElem = document.getElementById('reflex-score');

    let canClick = false;
    let startTime = 0;
    let reacted = false;

    // Wait between 1.2 and 2.6 seconds
    const waitTime = Math.random() * 1400 + 1200;
    const triggerTimeout = setTimeout(() => {
      if (this.gameFinished) return;
      canClick = true;
      startTime = Date.now();
      pad.className = 'reflex-pad ready-state';
      icon.innerText = '⚡';
      msg.innerText = 'ŞİMDİ DOKUN!';
      AudioPlayer.playStarFanfare();
    }, waitTime);

    pad.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      if (reacted) return;
      reacted = true;

      if (!canClick) {
        // False start!
        clearTimeout(triggerTimeout);
        pad.className = 'reflex-pad error-state';
        icon.innerText = '❌';
        msg.innerText = 'ERKEN TIKLADIN!';
        this.score = 0;
        AudioPlayer.playTrapSound();
        scoreElem.innerText = this.score;
        setTimeout(() => this.end(), 600);
      } else {
        const diffMs = Date.now() - startTime;
        this.score = Math.max(5, Math.floor(35 - (diffMs / 32)));
        pad.className = 'reflex-pad success-state';
        icon.innerText = '🎉';
        msg.innerText = `${diffMs} ms! Harika!`;
        AudioPlayer.playCoinSound();
        scoreElem.innerText = this.score;
        setTimeout(() => this.end(), 700);
      }
    });
  }

  /* ----------------------------------------------------
     MINI-GAME 3: LEGO BALON ŞİŞİRME (Balloon Mash)
     ---------------------------------------------------- */
  runBalloonMash(data) {
    let timeLeft = data.duration || 12;
    let balloonSize = 60;

    this.container.innerHTML = `
      <div class="minigame-card text-center">
        <div class="mg-stakes-badge">🏆 1.ye +15 🪙 & Eşya! • 💀 Sonuncuya -5 🪙 Ceza!</div>
        <h2>🎈 ${data.title}</h2>
        <p>${data.desc}</p>
        <div class="minigame-stats">
          <div class="badge-time">⏳ <span id="mash-timer">${timeLeft}</span>s</div>
          <div class="badge-score">Puan: <span id="mash-score">0</span></div>
        </div>
        <div class="balloon-arena">
          <div class="lego-balloon" id="lego-balloon">🎈</div>
        </div>
        <button class="mash-btn" id="mash-btn" style="touch-action: none; user-select: none;">POMPALA! (TIKLA)</button>
      </div>
    `;

    const balloon = document.getElementById('lego-balloon');
    const btn = document.getElementById('mash-btn');
    const timerElem = document.getElementById('mash-timer');
    const scoreElem = document.getElementById('mash-score');

    const handlePump = (e) => {
      if (e) e.preventDefault();
      if (this.gameFinished) return;
      this.score += 2;
      balloonSize += 5;
      scoreElem.innerText = this.score;

      balloon.style.transform = `scale(${balloonSize / 60})`;
      AudioPlayer.playStep();

      if (balloonSize >= 180) {
        // Popped!
        AudioPlayer.playStarFanfare();
        balloon.innerHTML = '💥';
        balloon.style.transform = 'scale(2.2)';
        this.score += 15;
        scoreElem.innerText = this.score;
        btn.disabled = true;
        setTimeout(() => this.end(), 600);
      }
    };

    btn.addEventListener('pointerdown', handlePump);
    window.addEventListener('keydown', (e) => {
      if (e.code === 'Space') handlePump();
    });

    this.timerInterval = setInterval(() => {
      timeLeft--;
      timerElem.innerText = timeLeft;
      AudioPlayer.playCountdown(timeLeft <= 3);

      if (timeLeft <= 0) {
        this.end();
      }
    }, 1000);
  }

  /* ----------------------------------------------------
     MINI-GAME 4: HAZİNE SANDIĞI KUMARI (Lucky Treasure Chests)
     ---------------------------------------------------- */
  runLuckyChests(data) {
    let timeLeft = data.duration || 10;
    let chosen = false;

    this.container.innerHTML = `
      <div class="minigame-card text-center">
        <div class="mg-stakes-badge">🏆 1.ye +15 🪙 & Eşya! • 💀 Sonuncuya -5 🪙 Ceza!</div>
        <h2>📦 ${data.title}</h2>
        <p>${data.desc}</p>
        <div class="minigame-stats">
          <div class="badge-time">⏳ <span id="chest-timer">${timeLeft}</span>s</div>
          <div class="badge-score">Sonuç: <span id="chest-result-badge">Bir Sandık Seç!</span></div>
        </div>
        <div class="lucky-chests-row" id="chests-row">
          <div class="lucky-chest-box" data-idx="0">
            <div class="chest-icon">📦</div>
            <div class="chest-label">Altın Sandık</div>
          </div>
          <div class="lucky-chest-box" data-idx="1">
            <div class="chest-icon">💎</div>
            <div class="chest-label">Elmas Kasa</div>
          </div>
          <div class="lucky-chest-box" data-idx="2">
            <div class="chest-icon">🐉</div>
            <div class="chest-label">Ejder Sandığı</div>
          </div>
        </div>
      </div>
    `;

    const timerElem = document.getElementById('chest-timer');
    const resultElem = document.getElementById('chest-result-badge');
    const chestBoxes = document.querySelectorAll('.lucky-chest-box');

    chestBoxes.forEach(box => {
      box.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        if (chosen || this.gameFinished) return;
        chosen = true;

        box.classList.add('selected');
        AudioPlayer.playChestOpen();

        // Roll outcome
        const rand = Math.random();
        setTimeout(() => {
          if (rand < 0.5) {
            // Jackpot!
            box.querySelector('.chest-icon').innerText = '👑';
            resultElem.innerText = '🎉 BÜYÜK HAZİNE! +25 Puan!';
            this.score = 25;
            AudioPlayer.playStarFanfare();
          } else if (rand < 0.8) {
            // Nice reward
            box.querySelector('.chest-icon').innerText = '🪙';
            resultElem.innerText = '💰 Altın Kaptın! +15 Puan!';
            this.score = 15;
            AudioPlayer.playCoinSound();
          } else {
            // Mimic monster trap!
            box.querySelector('.chest-icon').innerText = '👾';
            resultElem.innerText = '💀 CANAVAR TUZAĞI! 0 Puan!';
            this.score = 0;
            AudioPlayer.playTrapSound();
          }
          const speed = window.GAME_SPEED || 1;
          setTimeout(() => this.end(), Math.max(250, Math.round(500 / speed)));
        }, Math.max(150, Math.round(300 / speed)));
      });
    });

    this.timerInterval = setInterval(() => {
      timeLeft--;
      timerElem.innerText = timeLeft;
      AudioPlayer.playCountdown(timeLeft <= 3);

      if (timeLeft <= 0) {
        if (!chosen) {
          this.score = 0;
          this.end();
        }
      }
    }, 1000);
  }

  /* ----------------------------------------------------
     MINI-GAME 5: PARTİ HAFIZASI & HIZLI RENKLER (Memory Matrix)
     ---------------------------------------------------- */
  runMemoryMatrix(data) {
    let timeLeft = data.duration || 14;

    this.container.innerHTML = `
      <div class="minigame-card text-center">
        <div class="mg-stakes-badge">🏆 1.ye +15 🪙 & Eşya! • 💀 Sonuncuya -5 🪙 Ceza!</div>
        <h2>🧩 ${data.title}</h2>
        <p>${data.desc}</p>
        <div class="minigame-stats">
          <div class="badge-time">⏳ <span id="mem-timer">${timeLeft}</span>s</div>
          <div class="badge-score">⭐ Skor: <span id="mem-score">0</span></div>
        </div>
        <div class="memory-grid" id="memory-grid">
          <div class="mem-pad pad-red" data-color="red" style="touch-action: none;">🔴</div>
          <div class="mem-pad pad-blue" data-color="blue" style="touch-action: none;">🔵</div>
          <div class="mem-pad pad-green" data-color="green" style="touch-action: none;">🟢</div>
          <div class="mem-pad pad-yellow" data-color="yellow" style="touch-action: none;">🟡</div>
        </div>
        <div class="mem-status" id="mem-status">Sırayı İzle...</div>
      </div>
    `;

    const timerElem = document.getElementById('mem-timer');
    const scoreElem = document.getElementById('mem-score');
    const statusElem = document.getElementById('mem-status');
    const pads = document.querySelectorAll('.mem-pad');

    const colors = ['red', 'blue', 'green', 'yellow'];
    let sequence = [colors[Math.floor(Math.random() * 4)], colors[Math.floor(Math.random() * 4)], colors[Math.floor(Math.random() * 4)]];
    let userStep = 0;
    let acceptingInput = false;

    const playSequence = () => {
      acceptingInput = false;
      userStep = 0;
      statusElem.innerText = '👀 Dikkatle İzle...';

      sequence.forEach((col, idx) => {
        setTimeout(() => {
          if (this.gameFinished) return;
          const targetPad = document.querySelector(`.pad-${col}`);
          if (targetPad) {
            targetPad.classList.add('flash');
            AudioPlayer.playTone(col === 'red' ? 330 : (col === 'blue' ? 440 : (col === 'green' ? 550 : 660)), 'triangle', 0.15, 0.25);
            setTimeout(() => targetPad.classList.remove('flash'), 250);
          }
          if (idx === sequence.length - 1) {
            setTimeout(() => {
              if (this.gameFinished) return;
              acceptingInput = true;
              statusElem.innerText = '👉 Sırayı Şimdi Dokunarak Tekrarla!';
            }, 300);
          }
        }, idx * 450 + 200);
      });
    };

    setTimeout(playSequence, 600);

    pads.forEach(p => {
      p.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        if (!acceptingInput || this.gameFinished) return;
        const col = p.getAttribute('data-color');

        p.classList.add('flash');
        setTimeout(() => p.classList.remove('flash'), 180);
        AudioPlayer.playTone(col === 'red' ? 330 : (col === 'blue' ? 440 : (col === 'green' ? 550 : 660)), 'sine', 0.1, 0.2);

        if (col === sequence[userStep]) {
          userStep++;
          if (userStep >= sequence.length) {
            // Completed wave!
            this.score += 8;
            scoreElem.innerText = this.score;
            AudioPlayer.playCoinSound();
            statusElem.innerText = '✨ Harika! Yeni Renk Ekleniyor...';
            // Add next color
            sequence.push(colors[Math.floor(Math.random() * 4)]);
            setTimeout(playSequence, 800);
          }
        } else {
          // Mistake!
          AudioPlayer.playTrapSound();
          statusElem.innerText = '❌ Hatalı Sıra! Baştan Deneniyor...';
          acceptingInput = false;
          setTimeout(playSequence, 900);
        }
      });
    });

    this.timerInterval = setInterval(() => {
      timeLeft--;
      timerElem.innerText = timeLeft;
      AudioPlayer.playCountdown(timeLeft <= 3);

      if (timeLeft <= 0) {
        this.end();
      }
    }, 1000);
  }

  runTimingHammer(data) {
    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const titles = {
      tr: 'Güç Çekici & Hassas Zamanlama',
      en: 'Timing Hammer & Power Striker',
      de: 'Krafthammer & Präzision',
      es: 'Martillo de Fuerza y Precisión',
      fr: 'Marteau de Force & Précision'
    };
    const descs = {
      tr: 'Gösterge tam tepedeki %100 altın zili gösterdiğinde VUR! (3 Vuruş Hakkın Var)',
      en: 'Hit when the power meter reaches the 100% Golden Bell! (3 Hits)',
      de: 'Triff, wenn die Anzeige die 100% Goldene Glocke erreicht! (3 Schläge)',
      es: '¡Golpea cuando el medidor llegue al 100% de la Campana Dorada! (3 Golpes)',
      fr: 'Frappez quand la jauge atteint la Cloche Dorée 100% ! (3 Coups)'
    };
    const hitBtnLabels = {
      tr: '🔨 VUR! (TIKLA)',
      en: '🔨 HIT! (TAP)',
      de: '🔨 SCHLAGEN! (TIPPEN)',
      es: '🔨 ¡GOLPEAR! (TOCAR)',
      fr: '🔨 FRAPPER ! (CLIQUER)'
    };
    const attemptsLabels = {
      tr: 'Kalan Vuruş',
      en: 'Hits Left',
      de: 'Schläge übrig',
      es: 'Intentos restantes',
      fr: 'Coups restants'
    };

    let timeLeft = 12;
    let attemptsLeft = 3;
    let animId = null;
    let currentPower = 0;
    let direction = 1;
    let speed = 2.4;
    let canHit = true;

    this.container.innerHTML = `
      <div class="minigame-card text-center" style="max-width: 440px;">
        <div class="mg-stakes-badge">🏆 1.ye +15 🪙 & Eşya!   💀 Sonuncuya -5 🪙 Ceza!</div>
        <h2>🔨 ${titles[lang] || titles.tr}</h2>
        <p>${descs[lang] || descs.tr}</p>
        <div class="minigame-stats">
          <div class="badge-time">⏱️ <span id="th-timer">${timeLeft}</span>s</div>
          <div class="badge-score">🎯 <span id="th-attempts">${attemptsLeft}</span> ${attemptsLabels[lang] || attemptsLabels.tr}</div>
          <div class="badge-score">🪙 Skor: <span id="th-score">0</span></div>
        </div>

        <div class="hammer-striker-wrapper" style="margin: 16px auto; width: 100%; max-width: 340px; background: #0F172A; border-radius: 16px; padding: 14px; box-sizing: border-box; border: 3px solid #334155; box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);">
          <!-- Top Bell -->
          <div class="striker-bell" id="striker-bell" style="font-size: 32px; filter: drop-shadow(0 2px 6px rgba(234,179,8,0.5)); transition: transform 0.15s ease;">🔔 100</div>
          
          <!-- Track & Bar -->
          <div class="striker-track" style="position: relative; height: 32px; background: #1E293B; border-radius: 12px; margin: 12px 0; overflow: hidden; border: 2px solid #475569;">
            <div id="striker-fill" style="height: 100%; width: 0%; background: linear-gradient(90deg, #3B82F6 0%, #10B981 60%, #F59E0B 85%, #EF4444 95%, #FACC15 100%); transition: none; border-radius: 10px;"></div>
            <div style="position: absolute; right: 0; top: 0; width: 8%; height: 100%; background: rgba(254, 240, 138, 0.4); border-left: 2px dashed #FEF08A;"></div>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 800; color: #94A3B8; padding: 0 4px;">
            <span>0%</span>
            <span>50%</span>
            <span>85%</span>
            <span style="color: #FACC15;">🎯 100% ZİL</span>
          </div>

          <!-- Impact Message -->
          <div id="th-feedback" style="min-height: 28px; margin-top: 10px; font-weight: 900; font-size: 16px; color: #F8FAFC;">
            Zamanı yakala ve vur!
          </div>
        </div>

        <button class="mash-btn" id="btn-hammer-hit" style="width: 100%; max-width: 320px; font-size: 20px; padding: 14px; background: #EF4444; color: white; border: none; border-radius: 16px; box-shadow: 0 6px 0 #B91C1C; cursor: pointer; touch-action: manipulation; user-select: none;">
          ${hitBtnLabels[lang] || hitBtnLabels.tr}
        </button>
      </div>
    `;

    const fillElem = document.getElementById('striker-fill');
    const bellElem = document.getElementById('striker-bell');
    const scoreElem = document.getElementById('th-score');
    const attemptsElem = document.getElementById('th-attempts');
    const feedbackElem = document.getElementById('th-feedback');
    const timerElem = document.getElementById('th-timer');
    const hitBtn = document.getElementById('btn-hammer-hit');

    // Oscillation loop
    const loop = () => {
      if (this.gameFinished) return;
      currentPower += direction * speed;
      if (currentPower >= 100) {
        currentPower = 100;
        direction = -1;
      } else if (currentPower <= 0) {
        currentPower = 0;
        direction = 1;
      }
      if (fillElem) fillElem.style.width = `${currentPower}%`;
      animId = requestAnimationFrame(loop);
    };
    animId = requestAnimationFrame(loop);

    const onHit = (e) => {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      if (!canHit || this.gameFinished || attemptsLeft <= 0) return;
      canHit = false;

      let hitScore = 0;
      let text = '';
      if (currentPower >= 96) {
        hitScore = 50;
        text = '🔔 DİNG DİNG! 100 PUANLIK MÜKEMMEL VURUŞ! (+50)';
        AudioPlayer.playStarFanfare();
        if (bellElem) {
          bellElem.style.transform = 'scale(1.4) rotate(15deg)';
          setTimeout(() => { if (bellElem) bellElem.style.transform = 'scale(1)'; }, 300);
        }
      } else if (currentPower >= 85) {
        hitScore = 35;
        text = '💥 GÜÇLÜ VURUŞ! Harika zamanlama! (+35)';
        AudioPlayer.playCoinSound();
      } else if (currentPower >= 60) {
        hitScore = 20;
        text = '⚡ Fena Değil! (+20)';
        AudioPlayer.playClick();
      } else {
        hitScore = 10;
        text = '💨 Zayıf Vuruş! (+10)';
        AudioPlayer.playClick();
      }

      this.score += hitScore;
      scoreElem.innerText = this.score;
      feedbackElem.innerText = text;
      attemptsLeft--;
      attemptsElem.innerText = attemptsLeft;

      if (attemptsLeft <= 0) {
        setTimeout(() => {
          if (animId) cancelAnimationFrame(animId);
          this.end();
        }, 1100);
      } else {
        speed += 0.8;
        setTimeout(() => {
          canHit = true;
          if (feedbackElem) feedbackElem.innerText = 'Sıradaki vuruşa hazırlan!';
        }, 700);
      }
    };

    hitBtn.addEventListener('pointerdown', onHit);

    const keyHandler = (e) => {
      if (e.code === 'Space') {
        onHit(e);
      }
    };
    window.addEventListener('keydown', keyHandler);

    this.timerInterval = setInterval(() => {
      timeLeft--;
      if (timerElem) timerElem.innerText = timeLeft;
      AudioPlayer.playCountdown(timeLeft <= 3);

      if (timeLeft <= 0) {
        window.removeEventListener('keydown', keyHandler);
        if (animId) cancelAnimationFrame(animId);
        this.end();
      }
    }, 1000);
  }
}

const MiniGames = new MiniGameManager();


/* =================== 7. LOCAL GAME SERVER (STANDALONE ENGINE) =================== */

// ============================================================================
// STANDALONE IN-BROWSER GAME ENGINE (Local / Bot / Pass-and-Play Mode)
// Enables the game to run 100% offline from a single HTML file without Node.js!
// ============================================================================

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

// BOARD_TILES already declared in board-map.js

const BOT_NAMES = [
  { name: 'Lego Usta', avatar: 'builder', title: 'Mimar', color: '#3B82F6', personality: 'balanced' },
  { name: 'Yeşil Dino', avatar: 'dino', title: 'Parti Canavarı', color: '#10B981', personality: 'chaotic' },
  { name: 'Siber Kedi', avatar: 'cat', title: 'Hızlı Patiler', color: '#EC4899', personality: 'greedy' },
  { name: 'Gandi Bot', avatar: 'wizard', title: 'Büyücü', color: '#8B5CF6', personality: 'strategic' },
  { name: 'Robo-X', avatar: 'robot', title: 'Hesap Uzmanı', color: '#06B6D4', personality: 'balanced' },
  { name: 'Dedektif', avatar: 'detective', title: 'Gizem Avcısı', color: '#F59E0B', personality: 'strategic' }
];

class LocalRoom {
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

// Route all LocalRoom broadcasts directly to GameClient
LocalRoom.prototype.broadcast = function(type, payload = {}) {
  const msg = { type, ...payload };
  setTimeout(() => {
    if (window.GameClient) {
      window.GameClient.handleServerMessage(msg);
    }
  }, 10);
};

class LocalGameServer {
  constructor() {
    this.currentRoom = null;
  }

  createRoom(hostPlayer, settings = {}) {
    const roomId = 'SOLO';
    if (this.currentRoom) this.currentRoom.destroy();
    this.currentRoom = new LocalRoom(roomId, hostPlayer.sessionId, hostPlayer.name, settings);
    
    // Register the host player into the room
    const player = this.currentRoom.addPlayer(
      hostPlayer.sessionId,
      hostPlayer.name,
      hostPlayer.avatar,
      hostPlayer.diceSkin,
      hostPlayer.level,
      hostPlayer.title,
      null, // ws
      hostPlayer.hat,
      hostPlayer.color
    );

    setTimeout(() => {
      if (window.GameClient) {
        window.GameClient.handleServerMessage({
          type: 'room_created',
          roomId: roomId,
          sessionId: hostPlayer.sessionId,
          player: player
        });
      }
      this.currentRoom.broadcastState();
    }, 30);

    return this.currentRoom;
  }

  reportError(code) {
    if (!code || !window.GameClient) return;
    const messages = {
      not_your_turn: 'Eşyaları yalnızca kendi sıranda, zar atmadan önce kullanabilirsin!',
      turn_in_progress: 'Hamlen devam ediyor, şu an eşya kullanamazsın!',
      frozen: 'Donmuş durumdasın, bu tur eşya kullanamazsın!',
      no_target: 'Takas edilecek rakip bulunamadı!',
      shop_closed: 'Dükkân şu anda senin için açık değil!',
      no_coins: 'Yeterli altının yok!'
    };
    if (!messages[code]) return;
    window.GameClient.handleServerMessage({ type: 'error', message: messages[code] });
  }

  handleMessage(sessionId, type, data = {}) {
    if (!this.currentRoom) return;
    const room = this.currentRoom;

    switch (type) {
      case 'add_bot':
        room.addBot();
        room.broadcastState();
        break;

      case 'switch_map':
        if (room.state !== 'lobby') break;
        room.mapTheme = LocalRoom.normalizeTheme(data.mapTheme);
        room.board = generateBoardForTheme(room.mapTheme);
        room.addLog('🗺️ Harita seçildi: ' + room.mapTheme.toUpperCase());
        room.broadcastState();
        break;

      case 'start_game':
        if (room.state === 'lobby') room.startGame();
        break;

      case 'roll_dice':
        room.rollDice(sessionId);
        break;

      case 'set_game_speed':
        room.gameSpeed = [1, 2, 4].includes(Number(data.speed)) ? Number(data.speed) : 1;
        break;

      case 'buy_item':
        this.reportError(room.buyItem(sessionId, data.itemId));
        break;

      case 'use_item':
        this.reportError(room.useItem(sessionId, data.itemId, data.targetPlayerId));
        break;

      case 'close_shop':
        room.closeShop(sessionId);
        break;

      case 'send_trade_offer':
        room.handleTradeOffer(sessionId, data.offer || data);
        break;

      case 'respond_trade_offer':
        room.resolveTrade(data.tradeId, data.accepted === true, sessionId);
        break;

      case 'send_emote':
        room.sendEmote(sessionId, data.emoteId);
        break;

      case 'leave_room': {
        // Stop the abandoned room's bot and turn timers
        room.destroy();
        this.currentRoom = null;
        break;
      }

      case 'kick_player': {
        const pIdx = room.players.findIndex(p => p.id === data.playerId || p.sessionId === data.playerId);
        if (pIdx > 0) {
          const kicked = room.players.splice(pIdx, 1)[0];
          room.addLog('👋 ' + kicked.name + ' odadan ayrıldı.');
          room.broadcastState();
        }
        break;
      }

      case 'send_quick_chat':
        room.sendQuickChat(sessionId, data.text);
        break;

      case 'minigame_score':
        room.handleMiniGameScore(sessionId, data.score);
        break;
    }
  }
}

window.LocalServer = new LocalGameServer();


/* =================== 8. GAME CLIENT =================== */
/**
 * Multi-Backend Game Client & Networking Layer
 * Supports:
 *  1. PHP Serverless Backend ('api.php' with atomic file locking and zero DB)
 *  2. Pure Offline / Standalone Mode (100% In-Browser LocalServer Engine)
 *  3. WebSocket Server (Node.js fallback for LAN multiplayer)
 */
class ClientEngine {
  constructor() {
    this.ws = null;
    this.connected = false;
    this.backendType = 'offline'; // 'php', 'ws', 'offline'
    this.apiEndpoint = 'api.php';
    this.isStandalone = true;
    this.roomId = null;
    this.player = null; // My player object
    this.state = null; // Current room state
    this.reconnectAttempts = 0;
    this.pollInterval = null;
    this.lastStateHash = '';
  }

  async init() {
    await this.detectBackend();
    this.checkUrlParams();
  }

  async detectBackend() {
    const pill = document.getElementById('server-status-pill');
    const pillText = document.getElementById('server-status-text');

    // 1. If running on file:// protocol, force offline standalone
    if (window.location.protocol === 'file:') {
      this.backendType = 'offline';
      this.isStandalone = true;
      if (pill) pill.className = 'server-status-pill offline';
      if (pillText) pillText.innerText = '⚡ Yerel Dosya Modu (Bağımsız Oyun Motoru Aktif)';
      return;
    }

    // 2. Try checking PHP Backend (probing api.php, index.php, or current endpoint)
    const endpointsToProbe = ['api.php', 'index.php'];
    if (window.location.pathname.endsWith('.php')) {
      endpointsToProbe.unshift(window.location.pathname);
    } else {
      endpointsToProbe.push('?action=health');
    }

    for (const ep of endpointsToProbe) {
      try {
        const url = ep.includes('?') ? ep : `${ep}?action=health`;
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 1600);
        const res = await fetch(url, {
          signal: controller.signal,
          cache: 'no-store'
        });
        clearTimeout(timeoutId);

        if (res.ok) {
          const data = await res.json();
          if (data && data.status === 'ok') {
            console.log('✅ PHP Çok Oyunculu Arka Ucu aktif! Uç nokta:', ep);
            this.apiEndpoint = ep.split('?')[0] || 'index.php';
            this.backendType = 'php';
            this.isStandalone = false;
            this.connected = true;
            if (pill) pill.className = 'server-status-pill online';
            if (pillText) pillText.innerText = '🟢 Çevrimiçi Çok Oyunculu Mod Aktif';
            return;
          }
        }
      } catch (e) {
        // Continue to next probe
      }
    }

    // 3. Fallback to offline standalone LocalServer
    console.log('🌐 Statik / Çevrimdışı ortam tespit edildi. Bağımsız Yerel Motor (LocalServer) devrede.');
    this.backendType = 'offline';
    this.isStandalone = true;
    this.connected = true;
    if (pill) pill.className = 'server-status-pill offline';
    if (pillText) pillText.innerText = '⚡ Statik Mod (Bağımsız Çevrimdışı & Botlu Oyun)';
  }

  checkUrlParams() {
    try {
      const params = new URLSearchParams(window.location.search);
      const roomParam = params.get('room');
      if (roomParam) {
        const cleanRoom = roomParam.trim().toUpperCase();
        console.log('🔗 Davet bağlantısı algılandı. Oda:', cleanRoom);
        setTimeout(() => {
          if (this.backendType === 'php') {
            UI.showToast(`🔗 "${cleanRoom}" odasına bağlanılıyor...`, 'info');
            this.joinRoom(cleanRoom);
          } else {
            UI.showToast(`ℹ️ Bu web sitesi statik modda çalışıyor. Bot partisine başlanıyor!`, 'info');
            this.createRoom(10, 'istanbul');
          }
        }, 400);
        return true;
      }
    } catch (e) {}
    return false;
  }

  // ---------------------------------------------------------------------------
  // API & Communication Layer
  // ---------------------------------------------------------------------------

  getApiUrl(actionParams) {
    const ep = this.apiEndpoint || 'api.php';
    const glue = ep.includes('?') ? '&' : '?';
    return `${ep}${glue}action=${actionParams}`;
  }

  async apiPost(action, payload = {}) {
    try {
      const sessId = this.player ? this.player.sessionId : (PlayerProgression.profile ? PlayerProgression.profile.sessionId : 's_guest');
      const targetUrl = this.getApiUrl(action);
      const res = await fetch(targetUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action,
          roomId: this.roomId,
          sessionId: sessId,
          ...payload
        })
      });
      const data = await res.json();
      if (data && data.type === 'error') {
        UI.showToast(data.message, 'error');
        return null;
      }
      return data;
    } catch (e) {
      console.warn('API isteği başarısız:', e);
      return null;
    }
  }

  startPolling() {
    this.stopPolling();
    this.pollInterval = setInterval(async () => {
      if (!this.roomId || this.backendType !== 'php') return;
      try {
        const sessId = this.player ? this.player.sessionId : PlayerProgression.profile.sessionId;
        const targetUrl = this.getApiUrl(`get_state&roomId=${encodeURIComponent(this.roomId)}&sessionId=${encodeURIComponent(sessId)}`);
        const res = await fetch(targetUrl, {
          cache: 'no-store'
        });
        if (res.ok) {
          const msg = await res.json();
          if (msg && msg.type === 'state_update') {
            const hash = JSON.stringify({ r: msg.currentRound, t: msg.turnIndex, s: msg.state, p: msg.players.map(x=>x.position) });
            if (hash !== this.lastStateHash) {
              this.lastStateHash = hash;
              this.handleServerMessage(msg);
            }
          }
        }
      } catch (err) {}
    }, 1200);
  }

  stopPolling() {
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
  }

  async leaveRoom() {
    this.stopPolling();
    const rId = this.roomId;
    const pId = this.player ? (this.player.id || this.player.sessionId) : null;

    if (this.backendType === 'php' && rId) {
      try {
        await this.apiPost('leave_room', { playerId: pId });
      } catch (e) {}
    } else if (this.backendType === 'ws' && this.ws && this.connected) {
      this.send('leave_room', { roomId: rId });
    } else if (window.LocalServer) {
      window.LocalServer.handleMessage(pId || 's_host', 'leave_room', {});
    }

    this.roomId = null;
    this.player = null;
    this.state = null;
    window.CURRENT_ROOM_STATE = null;
    try {
      localStorage.removeItem('partycraft_active_room');
    } catch (e) {}

    // Clean URL query parameter (?room=...)
    if (window.history && window.history.replaceState) {
      const cleanUrl = window.location.origin + window.location.pathname;
      window.history.replaceState({}, document.title, cleanUrl);
    }

    if (window.UI) {
      window.UI.returnToHomeScreen();
    }
  }

  connect() {
    return Promise.resolve();
  }

  // ---------------------------------------------------------------------------
  // Actions (Create, Join, Start, Roll, Items)
  // ---------------------------------------------------------------------------

  async createRoom(rounds = 10, mapTheme = 'istanbul', password = '') {
    const prof = PlayerProgression.profile;

    // 1. PHP Backend Mode
    if (this.backendType === 'php') {
      const res = await this.apiPost('create_room', {
        playerName: prof.name,
        avatar: prof.avatar,
        hat: prof.hat || 'none',
        color: prof.color || '#3B82F6',
        diceSkin: prof.diceSkin,
        level: prof.level,
        title: prof.title,
        rounds: rounds,
        mapTheme: mapTheme,
        password: password
      });

      if (res && res.type === 'room_created') {
        this.roomId = res.roomId;
        this.player = res.player;
        localStorage.setItem('partycraft_active_room', res.roomId);
        this.handleServerMessage(res);
        this.startPolling();
      }
      return;
    }

    // 2. Standalone / Offline Mode (LocalServer)
    const hostPlayer = {
      sessionId: prof.sessionId,
      name: prof.name,
      avatar: prof.avatar,
      hat: prof.hat || 'none',
      color: prof.color || '#3B82F6',
      diceSkin: prof.diceSkin,
      level: prof.level,
      title: prof.title
    };
    this.player = hostPlayer;
    if (window.LocalServer) {
      window.LocalServer.createRoom(hostPlayer, {
        totalRounds: parseInt(rounds) || 10,
        mapTheme: mapTheme
      });
    }
  }

  async joinRoom(roomId, password = '') {
    const prof = PlayerProgression.profile;
    const cleanId = (roomId || '').trim().toUpperCase();

    // 1. PHP Backend Mode
    if (this.backendType === 'php') {
      const res = await this.apiPost('join_room', {
        roomId: cleanId,
        playerName: prof.name,
        avatar: prof.avatar,
        hat: prof.hat || 'none',
        color: prof.color || '#3B82F6',
        diceSkin: prof.diceSkin,
        level: prof.level,
        title: prof.title,
        password: password
      });

      if (res && res.type === 'room_joined') {
        this.roomId = res.roomId;
        this.player = res.player;
        localStorage.setItem('partycraft_active_room', res.roomId);
        this.handleServerMessage(res);
        this.startPolling();
      }
      return;
    }

    // 2. Offline Mode
    this.createRoom(10, 'istanbul');
  }

  async startGame() {
    if (this.backendType === 'php') {
      const res = await this.apiPost('start_game');
      if (res && res.type === 'state_update') {
        this.handleServerMessage(res);
      }
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'start_game', {});
    }
  }

  async addBot() {
    if (this.backendType === 'php') {
      const res = await this.apiPost('add_bot');
      if (res) this.handleServerMessage(res);
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'add_bot', {});
    }
  }

  async kickPlayer(targetPlayerId) {
    if (this.backendType === 'php') {
      const res = await this.apiPost('kick_player', { targetPlayerId });
      if (res) this.handleServerMessage(res);
      return;
    }

    if (window.LocalServer && window.LocalServer.currentRoom) {
      const room = window.LocalServer.currentRoom;
      const idx = room.players.findIndex(p => p.id === targetPlayerId);
      if (idx !== -1) {
        const removed = room.players.splice(idx, 1)[0];
        room.addLog(`🚪 ${removed.name} odadan çıkarıldı.`);
        room.broadcastState();
      }
    }
  }

  async switchMap(mapTheme) {
    if (this.backendType === 'php') {
      const res = await this.apiPost('switch_map', { mapTheme });
      if (res) this.handleServerMessage(res);
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'switch_map', { mapTheme });
    }
  }

  async rollDice() {
    if (this.backendType === 'php') {
      const res = await this.apiPost('roll_dice');
      if (res && res.type === 'dice_rolled') {
        UI.showDiceRollAnimation(res);
        setTimeout(() => {
          if (res.state) this.handleServerMessage(res.state);
          // The server opens the shop for this player after landing on a shop tile
          if (res.shopItems) UI.openShopModal(res.shopItems, true);
        }, 1600);
      }
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'roll_dice', {});
    }
  }

  async buyItem(itemId) {
    if (this.backendType === 'php') {
      const res = await this.apiPost('buy_item', { itemId });
      if (res && res.room) this.handleServerMessage(res.room);
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'buy_item', { itemId });
    }
  }

  async useItem(itemId, targetPlayerId = null) {
    if (this.backendType === 'php') {
      const res = await this.apiPost('use_item', { itemId, targetPlayerId });
      if (res && res.room) this.handleServerMessage(res.room);
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'use_item', { itemId, targetPlayerId });
    }
  }

  async closeShop() {
    if (this.backendType === 'php') {
      await this.apiPost('close_shop');
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'close_shop', {});
    }
  }

  async sendEmote(emoteId) {
    if (this.backendType === 'php') {
      await this.apiPost('send_emote', { emoteId });
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'send_emote', { emoteId });
    }
  }

  async sendQuickChat(text) {
    if (this.backendType === 'php') {
      await this.apiPost('send_quick_chat', { text });
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'send_quick_chat', { text });
    }
  }

  async sendMiniGameScore(score) {
    if (this.backendType === 'php') {
      await this.apiPost('minigame_score', { score });
      return;
    }

    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'minigame_score', { score });
    }
  }

  setGameSpeed(speed) {
    window.GAME_SPEED = speed;
    if (window.BoardMap) {
      window.BoardMap.gameSpeed = speed;
    }
    if (window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'set_game_speed', { speed });
    }
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.send('set_game_speed', { speed });
    }
  }

  // ---------------------------------------------------------------------------
  // Message Handling Dispatcher
  // ---------------------------------------------------------------------------

  handleServerMessage(msg) {
    if (!msg) return;

    switch (msg.type) {
      case 'room_created':
      case 'room_joined':
        this.roomId = msg.roomId;
        this.player = msg.player;
        this.playerId = msg.player ? msg.player.id : null;
        UI.showLobbyScreen(msg.roomId, this.player);
        if (msg.state) this.handleServerMessage(msg.state.type ? msg.state : { type: 'state_update', ...msg.state });
        break;

      case 'state_update':
        if (typeof msg.isHost === 'boolean') this.isHost = msg.isHost;
        this.state = msg;
        window.CURRENT_ROOM_STATE = msg;
        UI.updateGameState(msg);
        break;

      case 'dice_rolled':
        UI.showDiceRollAnimation(msg);
        break;

      case 'mystery_triggered':
        UI.showMysteryRoulette(msg);
        break;

      case 'tile_event':
        UI.showTileEventCard(msg);
        break;

      case 'open_shop':
        UI.openShopModal(msg.items, msg.playerId === (this.player ? this.player.id : null));
        break;

      case 'minigame_start':
        MiniGames.start(msg.minigame);
        break;

      case 'minigame_end':
        MiniGames.close();
        UI.showMiniGameResults(msg);
        break;

      case 'player_emote':
        UI.showPlayerEmote(msg.playerId, msg.emoteId);
        break;

      case 'player_chat':
        UI.showPlayerChatBubble(msg.playerId, msg.playerName, msg.text);
        break;

      case 'incoming_trade_offer':
        UI.showIncomingTradeModal(msg);
        break;

      case 'trade_completed':
        AudioPlayer.playStarFanfare();
        UI.showToast(`🤝 ${msg.senderName} ve ${msg.receiverName} başarıyla takas yaptı!`, 'success');
        break;

      case 'trade_rejected':
        UI.showToast(`❌ Takas teklifi reddedildi.`, 'info');
        break;

      case 'game_over':
        this.stopPolling();
        localStorage.removeItem('partycraft_active_room');
        UI.showVictoryPodium(msg);
        break;

      case 'error':
        UI.showToast(msg.message, 'error');
        break;
    }
  }
}

const GameClient = new ClientEngine();
window.GameClient = GameClient;


/* =================== 9. UI MANAGER =================== */
/**
 * UI Controller, Screen Transitions, Modals, Micro-interactions & Social Trade Engine
 */
// Escape player-controlled text before it is placed into innerHTML templates
function escapeHtml(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

class UIEngine {
  constructor() {
    this.homeScreen = document.getElementById('screen-home');
    this.lobbyScreen = document.getElementById('screen-lobby');
    this.gameScreen = document.getElementById('screen-game');
    this.boardRenderer = null;
    this.toastContainer = document.getElementById('toast-container');
    this.activeTradeOffer = null;
  }

  init() {
    if (window.i18n) {
      window.i18n.applyTranslations();
    }
    const savedSpeed = parseInt(localStorage.getItem('partycraft_game_speed') || '1', 10);
    window.GAME_SPEED = [1, 2, 4].includes(savedSpeed) ? savedSpeed : 1;
    this.updateSpeedUI();

    GameClient.init();
    this.initSettings();
    this.renderProfileCard();
    this.bindHomeEvents();
    this.bindGameEvents();
  }

  updateSpeedUI() {
    const lbl = document.getElementById('speed-label');
    const btn = document.getElementById('btn-toggle-speed');
    const spd = window.GAME_SPEED || 1;
    if (lbl) {
      lbl.innerText = spd === 4 ? '4x 🚀' : (spd === 2 ? '2x ⚡' : '1x');
    }
    if (btn) {
      btn.classList.toggle('active-speed', spd > 1);
    }
  }

  onLanguageChanged(lang) {
    this.renderCharacterStudio();
    this.renderProfileCard();
    if (GameClient.state) {
      this.renderGameHeader(GameClient.state);
      this.renderScoreboard(GameClient.state);
    }
    this.initSettings();
  }

  initSettings() {
    let settings = { sfx: true, music: false, dice3d: true, lowPower: false };
    try {
      const saved = localStorage.getItem('partycraft_settings');
      if (saved) settings = Object.assign(settings, JSON.parse(saved));
    } catch (e) {}

    window.PARTY_SETTINGS = settings;
    AudioPlayer.muted = !settings.sfx;

    const sfxInput = document.getElementById('setting-sfx');
    const musicInput = document.getElementById('setting-music');
    const diceInput = document.getElementById('setting-dice3d');
    const powerInput = document.getElementById('setting-lowpower');

    if (sfxInput) sfxInput.checked = !!settings.sfx;
    if (musicInput) musicInput.checked = !!settings.music;
    if (diceInput) diceInput.checked = !!settings.dice3d;
    if (powerInput) powerInput.checked = !!settings.lowPower;

    if (window.i18n) {
      const curLang = window.i18n.getLang();
      document.querySelectorAll('.lang-btn, .lang-pill-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-lang') === curLang);
      });
    }
  }

  checkUrlForRoom() {
    // Handled in GameClient.init()
  }

  renderProfileCard() {
    this.renderCharacterStudio();
  }

  renderCharacterStudio() {
    const prof = PlayerProgression.profile;
    const avatarDef = AVATAR_DEFS[prof.avatar] || AVATAR_DEFS.builder;
    const hatDef = HAT_DEFS[prof.hat] || HAT_DEFS.none;

    // 1. Live Avatar Preview & Glow
    const avatarBox = document.getElementById('profile-avatar-box');
    if (avatarBox) {
      avatarBox.innerHTML = avatarDef.svg;
    }

    const glowBox = document.getElementById('studio-avatar-glow');
    if (glowBox) {
      glowBox.style.borderColor = prof.color || '#3B82F6';
      glowBox.style.boxShadow = `0 0 24px ${prof.color || '#3B82F6'}55, inset 0 2px 6px rgba(0,0,0,0.1)`;
    }

    const hatBadge = document.getElementById('studio-hat-badge');
    if (hatBadge) {
      hatBadge.innerText = hatDef.icon || '🙂';
    }

    const nameInput = document.getElementById('home-name-input');
    if (nameInput && document.activeElement !== nameInput) {
      nameInput.value = prof.name;
    }

    const titleElem = document.getElementById('profile-title');
    if (titleElem) titleElem.innerText = PlayerProgression.getDisplayTitle(prof.title);

    const levelElem = document.getElementById('profile-level');
    if (levelElem) levelElem.innerText = `Lv. ${prof.level}`;

    const xpText = document.getElementById('profile-xp-text');
    const needed = PlayerProgression.getXpRequired(prof.level);
    if (xpText) {
      xpText.innerText = `${prof.xp} / ${needed} XP`;
    }

    // 2. Avatar Model Chips
    const avatarContainer = document.getElementById('home-avatar-chips');
    if (avatarContainer && avatarContainer.children.length === 0) {
      Object.values(AVATAR_DEFS).forEach(av => {
        const isUnlocked = prof.level >= av.requiredLevel;
        const chip = document.createElement('button');
        chip.className = `studio-chip-btn ${prof.avatar === av.id ? 'active' : ''}`;
        chip.id = `chip-av-${av.id}`;
        chip.innerHTML = `${av.name} ${!isUnlocked ? '🔒' : ''}`;
        chip.onclick = () => {
          if (!isUnlocked) {
            this.showToast(`Bu karakter Lv. ${av.requiredLevel}'de açılır!`, 'info');
            return;
          }
          AudioPlayer.playClick();
          PlayerProgression.setAvatar(av.id);
          this.refreshStudioChips();
          this.renderCharacterStudio();
        };
        avatarContainer.appendChild(chip);
      });
    }

    // 3. Hat / Accessory Chips
    const hatContainer = document.getElementById('home-hat-chips');
    if (hatContainer && hatContainer.children.length === 0) {
      Object.values(HAT_DEFS).forEach(h => {
        const isUnlocked = prof.level >= h.requiredLevel;
        const chip = document.createElement('button');
        chip.className = `studio-chip-btn ${prof.hat === h.id ? 'active' : ''}`;
        chip.id = `chip-hat-${h.id}`;
        chip.innerHTML = `${h.icon} ${h.name} ${!isUnlocked ? '🔒' : ''}`;
        chip.onclick = () => {
          if (!isUnlocked) {
            this.showToast(`Bu şapka Lv. ${h.requiredLevel}'de açılır!`, 'info');
            return;
          }
          AudioPlayer.playClick();
          PlayerProgression.setHat(h.id);
          this.refreshStudioChips();
          this.renderCharacterStudio();
        };
        hatContainer.appendChild(chip);
      });
    }

    // 4. Color Palette Chips
    const colorContainer = document.getElementById('home-color-chips');
    if (colorContainer && colorContainer.children.length === 0) {
      COLOR_PRESETS.forEach(c => {
        const circle = document.createElement('div');
        circle.className = `studio-color-circle ${prof.color === c.hex ? 'active' : ''}`;
        circle.id = `chip-color-${c.id}`;
        circle.style.backgroundColor = c.hex;
        circle.title = c.name;
        circle.onclick = () => {
          AudioPlayer.playClick();
          PlayerProgression.setColor(c.hex);
          this.refreshStudioChips();
          this.renderCharacterStudio();
        };
        colorContainer.appendChild(circle);
      });
    }
  }

  refreshStudioChips() {
    const prof = PlayerProgression.profile;
    document.querySelectorAll('#home-avatar-chips .studio-chip-btn').forEach(btn => {
      btn.classList.toggle('active', btn.id === `chip-av-${prof.avatar}`);
    });
    document.querySelectorAll('#home-hat-chips .studio-chip-btn').forEach(btn => {
      btn.classList.toggle('active', btn.id === `chip-hat-${prof.hat}`);
    });
    document.querySelectorAll('#home-color-chips .studio-color-circle').forEach(btn => {
      btn.classList.toggle('active', btn.style.backgroundColor === prof.color || btn.id === `chip-color-${prof.color}`);
    });
  }

  bindHomeEvents() {
    // Name input live update
    const nameInput = document.getElementById('home-name-input');
    if (nameInput) {
      nameInput.addEventListener('input', (e) => {
        PlayerProgression.setName(e.target.value);
      });
    }

    // 1. Quick Play (Instant Solo against 3 smart bots in a random city)
    const btnQuick = document.getElementById('btn-quick-play');
    if (btnQuick) {
      btnQuick.addEventListener('click', async () => {
        AudioPlayer.playClick();
        await GameClient.connect();
        const mapKeys = ['istanbul', 'tokyo', 'paris', 'newyork'];
        const chosenMap = mapKeys[Math.floor(Math.random() * mapKeys.length)];
        GameClient.createRoom(10, chosenMap);
        setTimeout(() => {
          GameClient.addBot();
          GameClient.addBot();
          GameClient.addBot();
          setTimeout(() => GameClient.startGame(), 400);
        }, 500);
      });
    }

    // 2. Play with Friends (1-click room creation with shareable link & QR code)
    const btnFriends = document.getElementById('btn-play-friends');
    if (btnFriends) {
      btnFriends.addEventListener('click', async () => {
        AudioPlayer.playClick();
        await GameClient.connect();
        GameClient.createRoom(10, 'istanbul');
      });
    }

    // 3. Play with Bots (Opens room configuration modal to pick city & round count)
    const btnBots = document.getElementById('btn-play-bots');
    if (btnBots) {
      btnBots.addEventListener('click', () => {
        AudioPlayer.playClick();
        const modal = document.getElementById('modal-create-room');
        if (modal) modal.classList.remove('hidden');
      });
    }

    // Backward-compatibility for any legacy create room button
    const btnLegacyCreate = document.getElementById('btn-create-room');
    if (btnLegacyCreate) {
      btnLegacyCreate.addEventListener('click', () => {
        AudioPlayer.playClick();
        const modal = document.getElementById('modal-create-room');
        if (modal) modal.classList.remove('hidden');
      });
    }

    // Confirm Create Room in modal
    const btnConfirmCreate = document.getElementById('btn-confirm-create');
    if (btnConfirmCreate) {
      btnConfirmCreate.addEventListener('click', async () => {
        AudioPlayer.playClick();
        const roundElem = document.querySelector('input[name="round-select"]:checked');
        const rounds = roundElem ? roundElem.value : 10;
        const mapThemeElem = document.querySelector('input[name="map-theme-select"]:checked');
        const mapTheme = mapThemeElem ? mapThemeElem.value : 'istanbul';
        await GameClient.connect();
        GameClient.createRoom(rounds, mapTheme);
        const modal = document.getElementById('modal-create-room');
        if (modal) modal.classList.add('hidden');
      });
    }

    // 4. How to Play Rules Modal
    const btnHowToPlay = document.getElementById('btn-how-to-play');
    const modalHowToPlay = document.getElementById('modal-how-to-play');
    const btnCloseHowToPlay = document.getElementById('btn-close-how-to-play');
    const btnOkHowToPlay = document.getElementById('btn-ok-how-to-play');
    if (btnHowToPlay && modalHowToPlay) {
      btnHowToPlay.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalHowToPlay.classList.remove('hidden');
      });
    }
    if (btnCloseHowToPlay && modalHowToPlay) {
      btnCloseHowToPlay.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalHowToPlay.classList.add('hidden');
      });
    }
    if (btnOkHowToPlay && modalHowToPlay) {
      btnOkHowToPlay.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalHowToPlay.classList.add('hidden');
      });
    }

    // 5. Settings Modal
    const btnSettings = document.getElementById('btn-settings');
    const modalSettings = document.getElementById('modal-settings');
    const btnCloseSettings = document.getElementById('btn-close-settings');
    const btnSaveSettings = document.getElementById('btn-save-settings');
    const btnResetProfile = document.getElementById('btn-reset-profile');

    if (btnSettings && modalSettings) {
      btnSettings.addEventListener('click', () => {
        AudioPlayer.playClick();
        this.initSettings();
        modalSettings.classList.remove('hidden');
      });
    }
    if (btnCloseSettings && modalSettings) {
      btnCloseSettings.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalSettings.classList.add('hidden');
      });
    }
    if (btnSaveSettings && modalSettings) {
      btnSaveSettings.addEventListener('click', () => {
        AudioPlayer.playClick();
        const sfx = document.getElementById('setting-sfx')?.checked ?? true;
        const music = document.getElementById('setting-music')?.checked ?? false;
        const dice3d = document.getElementById('setting-dice3d')?.checked ?? true;
        const lowPower = document.getElementById('setting-lowpower')?.checked ?? false;
        const s = { sfx, music, dice3d, lowPower };
        window.PARTY_SETTINGS = s;
        try { localStorage.setItem('partycraft_settings', JSON.stringify(s)); } catch (e) {}
        AudioPlayer.muted = !sfx;
        if (music && !AudioPlayer.musicPlaying) {
          AudioPlayer.toggleMusic();
        } else if (!music && AudioPlayer.musicPlaying) {
          AudioPlayer.toggleMusic();
        }
        modalSettings.classList.add('hidden');
        this.showToast('✅ Ayarlar başarıyla kaydedildi!', 'success');
      });
    }
    if (btnResetProfile) {
      btnResetProfile.addEventListener('click', () => {
        if (confirm('Tüm oyuncu seviyen ve biriken XP puanların sıfırlansın mı?')) {
          PlayerProgression.reset();
          this.renderCharacterStudio();
          this.showToast('🔄 Profil verileri sıfırlandı.', 'info');
        }
      });
    }

    // Language selector buttons
    document.querySelectorAll('.lang-btn, .lang-pill-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        AudioPlayer.playClick();
        const lang = btn.getAttribute('data-lang');
        if (lang && window.setLanguage) {
          window.setLanguage(lang);
        }
      });
    });

    // Join Room Button
    const btnJoin = document.getElementById('btn-join-room');
    if (btnJoin) {
      btnJoin.addEventListener('click', async () => {
        AudioPlayer.playClick();
        const roomCode = document.getElementById('input-room-code').value.trim().toUpperCase();
        if (!roomCode) {
          this.showToast('Lütfen oda kodunu gir!', 'error');
          return;
        }
        await GameClient.connect();
        GameClient.joinRoom(roomCode);
      });
    }

    // QR Modal Controls
    const btnCloseQr = document.getElementById('btn-close-qr');
    const modalQr = document.getElementById('modal-qr');
    if (btnCloseQr && modalQr) {
      btnCloseQr.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalQr.classList.add('hidden');
      });
    }
    const btnCopyLan = document.getElementById('btn-copy-lan-link');
    if (btnCopyLan) {
      btnCopyLan.addEventListener('click', () => {
        const link = document.getElementById('lan-qr-link-text')?.innerText || '';
        if (link) {
          AudioPlayer.playClick();
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(() => {
              this.showToast('📋 Bağlantı kopyalandı!', 'success');
            }).catch(() => prompt('Bağlantıyı kopyala:', link));
          } else {
            prompt('Bağlantıyı kopyala:', link);
          }
        }
      });
    }

    // HUD QR Trigger button
    const btnHudQr = document.getElementById('btn-hud-qr');
    if (btnHudQr) {
      btnHudQr.addEventListener('click', () => {
        AudioPlayer.playClick();
        if (modalQr) modalQr.classList.remove('hidden');
      });
    }

    // Wardrobe / Customization Modal
    const btnWardrobe = document.getElementById('btn-open-wardrobe');
    if (btnWardrobe) {
      btnWardrobe.addEventListener('click', () => {
        AudioPlayer.playClick();
        this.openWardrobeModal();
      });
    }

    // Sound / Music Toggles on Home
    const btnHomeMusic = document.getElementById('btn-home-music');
    if (btnHomeMusic) {
      btnHomeMusic.addEventListener('click', (e) => {
        const playing = AudioPlayer.toggleMusic();
        e.currentTarget.classList.toggle('active', playing);
        e.currentTarget.innerHTML = playing ? getIcon('volume2') : getIcon('volumeX');
      });
    }
  }

  openWardrobeModal() {
    const modal = document.getElementById('modal-wardrobe');
    modal.classList.remove('hidden');

    const prof = PlayerProgression.profile;
    const nameInput = document.getElementById('wardrobe-name-input');
    nameInput.value = prof.name;

    // Render Avatars Grid
    const avatarGrid = document.getElementById('wardrobe-avatars');
    avatarGrid.innerHTML = '';
    Object.values(AVATAR_DEFS).forEach(av => {
      const isUnlocked = prof.level >= av.requiredLevel;
      const isSelected = prof.avatar === av.id;

      const item = document.createElement('div');
      item.className = `wardrobe-item ${isSelected ? 'selected' : ''} ${!isUnlocked ? 'locked' : ''}`;
      item.innerHTML = `
        <div class="wardrobe-avatar-preview">${av.svg}</div>
        <div class="wardrobe-item-name">${av.name}</div>
        <div class="wardrobe-item-tag">${isUnlocked ? (isSelected ? 'Kuşanıldı' : 'Seç') : `Lv. ${av.requiredLevel}`}</div>
      `;

      if (isUnlocked) {
        item.addEventListener('click', () => {
          PlayerProgression.setAvatar(av.id);
          AudioPlayer.playClick();
          this.renderProfileCard();
          this.openWardrobeModal();
        });
      }
      avatarGrid.appendChild(item);
    });

    // Render Dice Skins Grid
    const diceGrid = document.getElementById('wardrobe-dice');
    diceGrid.innerHTML = '';
    Object.values(DICE_SKINS).forEach(ds => {
      const isUnlocked = prof.level >= ds.requiredLevel;
      const isSelected = prof.diceSkin === ds.id;

      const item = document.createElement('div');
      item.className = `wardrobe-item ${isSelected ? 'selected' : ''} ${!isUnlocked ? 'locked' : ''}`;
      item.innerHTML = `
        <div class="wardrobe-dice-preview" style="background:${ds.color}; border: 2px solid ${ds.border};">🎲</div>
        <div class="wardrobe-item-name">${ds.name}</div>
        <div class="wardrobe-item-tag">${isUnlocked ? (isSelected ? 'Kuşanıldı' : 'Seç') : `Lv. ${ds.requiredLevel}`}</div>
      `;

      if (isUnlocked) {
        item.addEventListener('click', () => {
          PlayerProgression.setDiceSkin(ds.id);
          AudioPlayer.playClick();
          this.renderProfileCard();
          this.openWardrobeModal();
        });
      }
      diceGrid.appendChild(item);
    });

    // Render Titles Select
    const titleSelect = document.getElementById('wardrobe-titles');
    titleSelect.innerHTML = '';
    PlayerProgression.getUnlockedTitles().forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.title;
      opt.innerText = t.title;
      if (prof.title === t.title) opt.selected = true;
      titleSelect.appendChild(opt);
    });

    // Save Name & Title
    document.getElementById('btn-save-wardrobe').onclick = () => {
      const newName = nameInput.value.trim();
      if (newName) PlayerProgression.setName(newName);
      PlayerProgression.setTitle(titleSelect.value);
      AudioPlayer.playClick();
      this.renderProfileCard();
      modal.classList.add('hidden');
    };
  }

  showLobbyScreen(roomId, player) {
    this.homeScreen.classList.add('hidden');
    this.gameScreen.classList.add('hidden');
    this.lobbyScreen.classList.remove('hidden');

    const codeElem = document.getElementById('lobby-room-code');
    if (codeElem) codeElem.innerText = roomId;

    const joinUrl = `${window.location.origin}${window.location.pathname}?room=${roomId}`;
    const shareUrlInput = document.getElementById('lobby-share-url');
    if (shareUrlInput) {
      shareUrlInput.value = joinUrl;
    }

    // Generate Crisp Inline SVG QR Code (Zero External Dependencies)
    const qrSvgBox = document.getElementById('lobby-qr-svg');
    if (qrSvgBox && typeof window.generateQRCodeSVG === 'function') {
      qrSvgBox.innerHTML = window.generateQRCodeSVG(joinUrl, 140);
    }

    // Update LAN / Mobile Modal QR
    const lanQrImg = document.getElementById('lan-qr-image');
    if (lanQrImg && typeof window.generateQRCodeSVG === 'function') {
      lanQrImg.src = 'data:image/svg+xml;utf8,' + encodeURIComponent(window.generateQRCodeSVG(joinUrl, 200));
    }
    const lanLinkText = document.getElementById('lan-qr-link-text');
    if (lanLinkText) lanLinkText.innerText = joinUrl;

    // Show QR Modal Button
    const btnShowQr = document.getElementById('btn-show-qr');
    if (btnShowQr) {
      btnShowQr.onclick = () => {
        AudioPlayer.playClick();
        const modalQr = document.getElementById('modal-qr');
        if (modalQr) modalQr.classList.remove('hidden');
      };
    }

    // Copy link button
    const btnCopy = document.getElementById('btn-copy-link');
    if (btnCopy) {
      btnCopy.onclick = () => {
        AudioPlayer.playClick();
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(joinUrl).then(() => {
            this.showToast('📋 Davet bağlantısı panoya kopyalandı!', 'success');
          }).catch(() => prompt('Davet linkini kopyala:', joinUrl));
        } else {
          prompt('Davet linkini kopyala:', joinUrl);
        }
      };
    }

    // Bot Add Button
    const btnAddBot = document.getElementById('btn-lobby-add-bot');
    if (btnAddBot) {
      btnAddBot.onclick = () => {
        AudioPlayer.playClick();
        GameClient.addBot();
      };
    }

    // Switch Map buttons in lobby
    const mapSelects = document.querySelectorAll('.lobby-map-btn');
    mapSelects.forEach(btn => {
      btn.onclick = () => {
        const theme = btn.dataset.theme;
        GameClient.switchMap(theme);
        AudioPlayer.playClick();
      };
    });

    // Leave Lobby Button
    const btnLobbyLeave = document.getElementById('btn-lobby-leave');
    if (btnLobbyLeave) {
      btnLobbyLeave.onclick = () => {
        AudioPlayer.playClick();
        GameClient.leaveRoom();
      };
    }

    // Start Game Button
    const btnStart = document.getElementById('btn-lobby-start');
    if (btnStart) {
      btnStart.onclick = () => {
        AudioPlayer.playClick();
        GameClient.startGame();
      };
    }
  }

  updateGameState(state) {
    if (state.state === 'lobby') {
      this.renderLobbyPlayers(state.players);
      this.updateLobbyMapTheme(state.mapTheme);
      return;
    }

    if (state.state === 'playing' || state.state === 'minigame') {
      if (this.gameScreen.classList.contains('hidden')) {
        this.homeScreen.classList.add('hidden');
        this.lobbyScreen.classList.add('hidden');
        this.gameScreen.classList.remove('hidden');

        if (!this.boardRenderer) {
          this.boardRenderer = new BoardRenderer('board-canvas');
        }

        // Guarantee crisp canvas resize & character visibility immediately upon entering
        requestAnimationFrame(() => {
          if (this.boardRenderer) {
            this.boardRenderer.resizeCanvas();
            this.boardRenderer.resetCamera();
          }
        });
      }

      if (this.boardRenderer && state.mapTheme) {
        this.boardRenderer.setTheme(state.mapTheme);
      }

      this.renderGameHeader(state);
      this.renderScoreboard(state);
      this.updateTurnControls(state);
      this.renderEventLog(state.log);
    }
  }

  updateLobbyMapTheme(themeId) {
    const themeDef = MAP_THEMES[themeId] || MAP_THEMES.istanbul;
    const badge = document.getElementById('lobby-map-badge');
    if (badge) {
      badge.innerText = `🗺️ Harita: ${themeDef.name}`;
    }
    const mapBtns = document.querySelectorAll('.lobby-map-btn');
    mapBtns.forEach(b => {
      b.classList.toggle('active', b.dataset.theme === themeId);
    });
  }

  renderLobbyPlayers(players) {
    const list = document.getElementById('lobby-players-list');
    if (!list) return;
    list.innerHTML = '';

    const countElem = document.getElementById('lobby-player-count');
    if (countElem) countElem.innerText = players.length;

    const isHost = (GameClient.isHost || (players[0] && players[0].id === GameClient.playerId));

    players.forEach((p, idx) => {
      const avatarDef = AVATAR_DEFS[p.avatar] || AVATAR_DEFS.builder;
      const hatDef = HAT_DEFS[p.hat] || HAT_DEFS.none;
      const card = document.createElement('div');
      card.className = 'lobby-player-card';
      card.style.borderLeft = `5px solid ${p.color}`;
      card.innerHTML = `
        <div class="lobby-player-avatar">${avatarDef.svg}</div>
        <div class="lobby-player-info">
          <div class="lobby-player-name">${escapeHtml(p.name)} ${hatDef.id !== 'none' ? hatDef.icon : ''} ${p.isBot ? '🤖' : ''}</div>
          <div class="lobby-player-title">${escapeHtml(p.title)} • Lv.${escapeHtml(p.level)}</div>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
          <div class="lobby-player-badge">${idx === 0 ? '👑 Oda Sahibi' : 'Hazır'}</div>
          ${(isHost && idx !== 0) ? `<button class="btn-kick-player" data-player-id="${p.id}" title="Oyuncuyu Odadan Çıkar">✕</button>` : ''}
        </div>
      `;
      list.appendChild(card);
    });

    list.querySelectorAll('.btn-kick-player').forEach(btn => {
      btn.onclick = (e) => {
        e.stopPropagation();
        const pid = btn.dataset.playerId;
        if (pid) {
          AudioPlayer.playClick();
          GameClient.kickPlayer(pid);
        }
      };
    });

    const startBtn = document.getElementById('btn-lobby-start');
    if (startBtn) {
      startBtn.disabled = (players.length < 1);
    }
  }

  renderGameHeader(state) {
    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const roundLabels = { tr: 'Tur', en: 'Round', de: 'Runde', es: 'Ronda', fr: 'Manche' };
    const finalLabels = { tr: 'FİNAL TURU!', en: 'FINAL ROUND!', de: 'FINALE RUNDE!', es: '¡RONDA FINAL!', fr: 'MANCHE FINALE !' };
    const leftLabels = { tr: 'Tur Kaldı', en: 'Rounds Left', de: 'Runden übrig', es: 'Rondas restantes', fr: 'Tours restants' };
    const yourTurnLabels = { tr: '✨ Sıra Sende!', en: '✨ Your Turn!', de: '✨ Du bist dran!', es: '✨ ¡Tu Turno!', fr: '✨ À votre tour !' };
    const turnPrefixLabels = { tr: 'Sıra: ', en: 'Turn: ', de: 'Zug: ', es: 'Turno: ', fr: 'Tour : ' };

    const current = state.currentRound || 1;
    const total = state.totalRounds || 10;
    const remaining = Math.max(0, total - current);

    const roundElem = document.getElementById('hud-round');
    if (roundElem) roundElem.innerText = `${roundLabels[lang] || 'Tur'} ${current} / ${total}`;

    const remElem = document.getElementById('hud-round-remaining');
    if (remElem) {
      remElem.innerText = (remaining === 0 ? (finalLabels[lang] || 'FİNAL TURU!') : `${remaining} ${leftLabels[lang] || 'Tur Kaldı'}`);
      remElem.classList.toggle('final-pulse', remaining <= 1);
    }

    const fillElem = document.getElementById('hud-round-fill');
    if (fillElem) {
      const pct = Math.min(100, Math.round((current / total) * 100));
      fillElem.style.width = `${pct}%`;
    }

    // Trigger Golden Final Round Alert Banner when entering final round!
    const finalBanner = document.getElementById('final-round-banner');
    if (finalBanner) {
      if (current === total) {
        finalBanner.classList.remove('hidden');
        if (!this.finalBannerSoundPlayed) {
          this.finalBannerSoundPlayed = true;
          AudioPlayer.playStarFanfare();
        }
      } else {
        finalBanner.classList.add('hidden');
      }
    }

    const theme = MAP_THEMES[state.mapTheme] || MAP_THEMES.istanbul;
    const mapLabel = document.getElementById('hud-map-name');
    if (mapLabel) mapLabel.innerText = theme.name.split(' - ')[0];

    const activePlayer = state.players[state.turnIndex];
    if (activePlayer) {
      const isMe = (GameClient.player && GameClient.player.sessionId === activePlayer.sessionId);
      const turnLabel = isMe 
        ? (yourTurnLabels[lang] || '✨ Sıra Sende!') 
        : `${turnPrefixLabels[lang] || 'Sıra: '}${activePlayer.name}`;
      document.getElementById('hud-turn-name').innerText = turnLabel;
      document.getElementById('hud-turn-avatar').style.borderColor = activePlayer.color;
    }
  }

  renderScoreboard(state) {
    const boardElem = document.getElementById('players-carousel');
    if (!boardElem) return;
    boardElem.innerHTML = '';

    state.players.forEach((p, idx) => {
      const isCurrent = (idx === state.turnIndex);
      const isMe = (GameClient.player && GameClient.player.sessionId === p.sessionId);
      const avatarDef = AVATAR_DEFS[p.avatar] || AVATAR_DEFS.builder;
      const hatDef = HAT_DEFS[p.hat] || HAT_DEFS.none;

      const card = document.createElement('div');
      card.className = `player-hud-card ${isCurrent ? 'active-turn' : ''} ${isMe ? 'is-me' : ''}`;
      card.id = `player-card-${p.id}`;
      card.style.borderColor = p.color;

      // Status badges
      let statusIcons = '';
      if (hatDef.id !== 'none') statusIcons += `<span class="status-icon" title="Şapka">${hatDef.icon}</span>`;
      if (p.jailedTurns > 0) statusIcons += '<span class="status-icon" title="Kodes Cezası">🔒</span>';
      if (p.frozenTurns > 0) statusIcons += '<span class="status-icon" title="Dondu">🧊</span>';
      if (p.reverseNext) statusIcons += '<span class="status-icon" title="Ters Yön">🔄</span>';
      if (p.shieldActive > 0) statusIcons += '<span class="status-icon" title="Kalkan">🛡️</span>';
      if (p.isBot) statusIcons += '<span class="status-icon">🤖</span>';

      card.innerHTML = `
        <div class="hud-avatar-box">
          ${avatarDef.svg}
          <div class="hud-badges-row">${statusIcons}</div>
        </div>
        <div class="hud-player-stats">
          <div class="hud-pname">${escapeHtml(p.name)}</div>
          <div class="hud-scores">
            <span class="hud-star">⭐ ${p.stars}</span>
            <span class="hud-coin">🪙 ${p.coins}</span>
          </div>
        </div>
      `;

      boardElem.appendChild(card);
    });
  }

  updateTurnControls(state) {
    const activePlayer = state.players[state.turnIndex];
    const isMyTurn = (GameClient.player && activePlayer && GameClient.player.sessionId === activePlayer.sessionId && !activePlayer.isBot);

    const rollBtn = document.getElementById('btn-roll-dice');
    const dockStatus = document.getElementById('dock-status-text');

    if (rollBtn) {
      rollBtn.disabled = !isMyTurn;
      rollBtn.classList.toggle('pulse-turn', isMyTurn);
      // Short labels; the Space shortcut is shown as a small key cap, the variant as a class
      const kbd = '<kbd class="roll-kbd">Space</kbd>';
      rollBtn.style.background = '';
      rollBtn.style.boxShadow = '';
      rollBtn.classList.remove('is-double', 'is-fixed');
      if (isMyTurn && activePlayer && activePlayer.doubleDiceActive) {
        rollBtn.innerHTML = `<span>🎲🎲</span> Çift zar ${kbd}`;
        rollBtn.classList.add('is-double');
      } else if (isMyTurn && activePlayer && activePlayer.fixedRollNext) {
        rollBtn.innerHTML = `<span>🎯</span> Zar: ${Number(activePlayer.fixedRollNext)} ${kbd}`;
        rollBtn.classList.add('is-fixed');
      } else {
        rollBtn.innerHTML = `<span>🎲</span> Zar at ${kbd}`;
      }
    }

    // Update Top Screen Unmissable Turn Alert Banner
    const turnBanner = document.getElementById('hud-turn-alert-banner');
    const bannerIcon = document.getElementById('turn-banner-icon');
    const bannerTitle = document.getElementById('turn-banner-title');
    const bannerSub = document.getElementById('turn-banner-sub');

    if (turnBanner && activePlayer) {
      if (isMyTurn) {
        turnBanner.classList.add('my-turn');
        if (bannerIcon) bannerIcon.innerText = '🎲';
        if (bannerTitle) bannerTitle.innerText = '🎯 SIRA SENDE!';
        if (bannerSub) {
          if (activePlayer.jailedTurns > 0) {
            bannerSub.innerText = '🔒 Kodes Cezası: 5 altın kefalet öde veya pas geç!';
          } else if (activePlayer.doubleDiceActive) {
            bannerSub.innerText = '🎲🎲 2X Çift Zar Etkin! Bu tur iki zar birden atacaksın!';
          } else if (activePlayer.fixedRollNext) {
            bannerSub.innerText = `🎯 Şanslı Zar Etkin! Bu tur tam ${activePlayer.fixedRollNext} adım atacaksın!`;
          } else if (activePlayer.ghostCloakActive > 0) {
            bannerSub.innerText = `👻 Hayalet Pelerini Aktif (${activePlayer.ghostCloakActive} tur)! Tüm tuzak ve cezalardan muafsın!`;
          } else if (activePlayer.reverseNext) {
            bannerSub.innerText = '🔄 Boğaz Ters Akıntısı: Bir sonraki zarda geriye doğru yürüyeceksin!';
          } else {
            bannerSub.innerText = 'Zarı at (SPACE) veya çantandan taktik eşyası kullan!';
          }
        }
      } else {
        turnBanner.classList.remove('my-turn');
        if (bannerIcon) bannerIcon.innerText = '⏳';
        if (bannerTitle) bannerTitle.innerText = `⏳ Sıra: ${activePlayer.name}`;
        if (bannerSub) bannerSub.innerText = `${activePlayer.title} hamlesini yapıyor, hamle bekleniyor...`;
      }
    }

    if (dockStatus) {
      if (isMyTurn) {
        if (activePlayer.jailedTurns > 0) {
          dockStatus.innerHTML = '🔒 <strong>Kodes\'tesin!</strong> 5 altın kefalet ödeyeceksin ya da pas geçeceksin!';
        } else {
          dockStatus.innerHTML = '👉 <strong>Sıra Sende!</strong> Zarı at ya da takas teklif et!';
        }
      } else if (activePlayer) {
        dockStatus.innerHTML = `⏳ <strong>${escapeHtml(activePlayer.name)}</strong> hamlesini yapıyor...`;
      }
    }

    // Update inventory badge
    const myPlayer = state.players.find(p => GameClient.player && p.sessionId === GameClient.player.sessionId);
    const invBadge = document.getElementById('inventory-badge');
    if (invBadge && myPlayer) {
      invBadge.innerText = myPlayer.inventory.length;
    }
  }

  bindGameEvents() {
    // Roll Dice Button
    document.getElementById('btn-roll-dice').addEventListener('click', () => {
      AudioPlayer.playClick();
      GameClient.rollDice();
    });

    // Speed Toggle Button (1x -> 2x -> 4x -> 1x)
    const btnToggleSpeed = document.getElementById('btn-toggle-speed');
    if (btnToggleSpeed) {
      btnToggleSpeed.onclick = () => {
        let current = window.GAME_SPEED || 1;
        let next = 1;
        if (current === 1) next = 2;
        else if (current === 2) next = 4;
        else next = 1;

        window.GAME_SPEED = next;
        localStorage.setItem('partycraft_game_speed', next.toString());
        this.updateSpeedUI();
        if (window.GameClient) {
          window.GameClient.setGameSpeed(next);
        }
        AudioPlayer.playClick();
        const toastMsg = next === 4 ? '🚀 Oyun Hızı: 4x Turbo!' : (next === 2 ? '⚡ Oyun Hızı: 2x Hızlı!' : '⚡ Oyun Hızı: 1x Normal');
        this.showToast(toastMsg, 'info');
      };
    }

    // Inventory Button
    document.getElementById('btn-open-inventory').addEventListener('click', () => {
      AudioPlayer.playClick();
      this.openInventoryModal();
    });

    // Trade Button
    document.getElementById('btn-open-trade').addEventListener('click', () => {
      AudioPlayer.playClick();
      this.openTradeModal();
    });

    // Emotes Button
    document.getElementById('btn-open-emotes').addEventListener('click', () => {
      AudioPlayer.playClick();
      this.openEmoteWheel();
    });

    // Quick Chat Button
    document.getElementById('btn-open-chat').addEventListener('click', () => {
      AudioPlayer.playClick();
      this.openQuickChatModal();
    });

    // Camera Controls
    document.getElementById('btn-cam-zoom-in').onclick = () => {
      if (this.boardRenderer) this.boardRenderer.zoomIn();
    };
    document.getElementById('btn-cam-zoom-out').onclick = () => {
      if (this.boardRenderer) this.boardRenderer.zoomOut();
    };
    document.getElementById('btn-reset-cam').onclick = () => {
      AudioPlayer.playClick();
      if (this.boardRenderer) this.boardRenderer.resetCamera();
    };

    // Show LAN QR Code Button
    document.getElementById('btn-hud-qr').addEventListener('click', () => {
      AudioPlayer.playClick();
      this.openQrModal();
    });

    // Music Toggle
    document.getElementById('btn-hud-music').addEventListener('click', (e) => {
      const playing = AudioPlayer.toggleMusic();
      e.currentTarget.classList.toggle('active', playing);
      e.currentTarget.innerHTML = playing ? getIcon('volume2') : getIcon('volumeX');
    });

    // Leave Game Modal Controls (In-Game HUD Leave Button)
    const btnHudLeave = document.getElementById('btn-hud-leave');
    const modalLeave = document.getElementById('modal-confirm-leave');
    const btnCancelLeave = document.getElementById('btn-cancel-leave');
    const btnConfirmLeave = document.getElementById('btn-confirm-leave');

    if (btnHudLeave && modalLeave) {
      btnHudLeave.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalLeave.classList.remove('hidden');
      });
    }
    if (btnCancelLeave && modalLeave) {
      btnCancelLeave.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalLeave.classList.add('hidden');
      });
    }
    if (btnConfirmLeave && modalLeave) {
      btnConfirmLeave.addEventListener('click', () => {
        AudioPlayer.playClick();
        modalLeave.classList.add('hidden');
        GameClient.leaveRoom();
      });
    }

    // Keyboard Shortcuts
    window.addEventListener('keydown', (e) => {
      if (e.code === 'Space') {
        const rollBtn = document.getElementById('btn-roll-dice');
        if (rollBtn && !rollBtn.disabled) {
          rollBtn.click();
        }
      } else if (e.key === 't' || e.key === 'T') {
        document.getElementById('btn-open-trade').click();
      } else if (e.key === 'e' || e.key === 'E') {
        document.getElementById('btn-open-emotes').click();
      } else if (e.key === 'i' || e.key === 'I') {
        document.getElementById('btn-open-inventory').click();
      } else if (e.key === 'Escape') {
        // ESC key can close modals or open leave confirm
        const openModal = document.querySelector('.modal-overlay:not(.hidden)');
        if (openModal) {
          openModal.classList.add('hidden');
        } else if (!this.gameScreen.classList.contains('hidden')) {
          const m = document.getElementById('modal-confirm-leave');
          if (m) m.classList.remove('hidden');
        }
      }
    });
  }

  returnToHomeScreen() {
    this.gameScreen.classList.add('hidden');
    this.lobbyScreen.classList.add('hidden');
    this.homeScreen.classList.remove('hidden');

    const modalLeave = document.getElementById('modal-confirm-leave');
    if (modalLeave) modalLeave.classList.add('hidden');

    // Close any other open modals
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.add('hidden'));

    this.renderCharacterStudio();
    this.showToast('🚪 Oyundan ayrıldın. Ana menüye dönüldü.', 'info');
  }

  showTileInspector(tile, index, playersOnTile, stepsAway) {
    const modal = document.getElementById('modal-tile-inspector');
    modal.classList.remove('hidden');

    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const stepsDict = {
      tr: `${stepsAway} adım mesafede`,
      en: `${stepsAway} steps away`,
      de: `${stepsAway} Schritte entfernt`,
      es: `a ${stepsAway} casillas de distancia`,
      fr: `à ${stepsAway} cases de distance`
    };
    const emptyDict = {
      tr: 'Şu an bu karoda kimse yok.',
      en: 'No players on this tile.',
      de: 'Derzeit keine Spieler auf diesem Feld.',
      es: 'No hay jugadores en esta casilla.',
      fr: 'Aucun joueur sur cette case.'
    };
    document.getElementById('ti-tile-title').innerText = `${index}. ${tile.title}`;
    document.getElementById('ti-tile-desc').innerText = tile.desc;
    document.getElementById('ti-tile-steps').innerText = stepsDict[lang] || stepsDict.tr;

    const playersList = document.getElementById('ti-players-here');
    playersList.innerHTML = '';
    if (playersOnTile.length === 0) {
      playersList.innerHTML = `<span style="color:#94A3B8; font-size:12px;">${emptyDict[lang] || emptyDict.tr}</span>`;
    } else {
      playersOnTile.forEach(p => {
        const pBadge = document.createElement('span');
        pBadge.className = 'ti-player-pill';
        pBadge.style.borderColor = p.color;
        pBadge.innerText = `${p.name} (⭐${p.stars})`;
        playersList.appendChild(pBadge);
      });
    }

    document.getElementById('btn-close-tile-inspector').onclick = () => {
      modal.classList.add('hidden');
    };
  }

  /* ----------------------------------------------------
     TRADE SYSTEM UI (Oyuncular Arası Takas)
     ---------------------------------------------------- */
  openTradeModal() {
    const modal = document.getElementById('modal-trade');
    modal.classList.remove('hidden');

    const state = GameClient.state;
    if (!state || !state.players) return;

    const myPlayer = state.players.find(p => GameClient.player && p.sessionId === GameClient.player.sessionId);
    if (!myPlayer) return;

    const targetSelect = document.getElementById('trade-target-player');
    targetSelect.innerHTML = '';
    const otherPlayers = state.players.filter(p => p.id !== myPlayer.id);

    if (otherPlayers.length === 0) {
      targetSelect.innerHTML = '<option value="">Odada başka oyuncu yok!</option>';
      document.getElementById('btn-send-trade-offer').disabled = true;
      return;
    }

    document.getElementById('btn-send-trade-offer').disabled = false;
    otherPlayers.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.innerText = `${p.name} (🪙 ${p.coins} Altın, 🎒 ${p.inventory.length} Eşya)`;
      targetSelect.appendChild(opt);
    });

    // Populate My Offer Item
    const myItemSelect = document.getElementById('trade-offer-item');
    myItemSelect.innerHTML = '<option value="">-- Eşya Teklif Etme --</option>';
    myPlayer.inventory.forEach(itemId => {
      const itemDef = SHOP_ITEMS.find(i => i.id === itemId) || { name: itemId };
      const opt = document.createElement('option');
      opt.value = itemId;
      opt.innerText = itemDef.name;
      myItemSelect.appendChild(opt);
    });

    // Populate Requested Item based on selected target
    const reqItemSelect = document.getElementById('trade-request-item');
    const updateTargetInventory = () => {
      const targetId = targetSelect.value;
      const targetP = state.players.find(p => p.id === targetId);
      reqItemSelect.innerHTML = '<option value="">-- Eşya İsteme --</option>';
      if (targetP && targetP.inventory.length > 0) {
        targetP.inventory.forEach(itemId => {
          const itemDef = SHOP_ITEMS.find(i => i.id === itemId) || { name: itemId };
          const opt = document.createElement('option');
          opt.value = itemId;
          opt.innerText = itemDef.name;
          reqItemSelect.appendChild(opt);
        });
      }
    };

    targetSelect.onchange = updateTargetInventory;
    updateTargetInventory();

    // Set Max Coin limits
    const offerCoinsInput = document.getElementById('trade-offer-coins');
    offerCoinsInput.max = myPlayer.coins;
    offerCoinsInput.value = 0;

    const reqCoinsInput = document.getElementById('trade-request-coins');
    reqCoinsInput.value = 0;

    // Send Button
    document.getElementById('btn-send-trade-offer').onclick = () => {
      const targetId = targetSelect.value;
      const offerCoins = parseInt(offerCoinsInput.value) || 0;
      const offerItem = myItemSelect.value || null;
      const requestCoins = parseInt(reqCoinsInput.value) || 0;
      const requestItem = reqItemSelect.value || null;

      if (!targetId) return;

      GameClient.sendTradeOffer({
        toPlayerId: targetId,
        offerCoins,
        offerItem,
        requestCoins,
        requestItem
      });

      AudioPlayer.playClick();
      modal.classList.add('hidden');
      this.showToast('🤝 Takas teklifin gönderildi!', 'info');
    };

    document.getElementById('btn-close-trade').onclick = () => {
      modal.classList.add('hidden');
    };
  }

  showIncomingTradeModal(data) {
    AudioPlayer.playStarFanfare();
    const modal = document.getElementById('modal-incoming-trade');
    modal.classList.remove('hidden');

    document.getElementById('inc-sender-name').innerText = data.senderName;
    
    // What sender offers
    let offerText = `${data.offerCoins} Altın`;
    if (data.offerItem) {
      const itemDef = SHOP_ITEMS.find(i => i.id === data.offerItem) || { name: data.offerItem };
      offerText += ` + [${itemDef.name}]`;
    }
    document.getElementById('inc-offer-details').innerText = offerText;

    // What sender asks from you
    let reqText = `${data.requestCoins} Altın`;
    if (data.requestItem) {
      const itemDef = SHOP_ITEMS.find(i => i.id === data.requestItem) || { name: data.requestItem };
      reqText += ` + [${itemDef.name}]`;
    }
    document.getElementById('inc-request-details').innerText = reqText;

    // Accept / Decline handlers
    document.getElementById('btn-accept-trade').onclick = () => {
      AudioPlayer.playCoinSound();
      GameClient.respondTradeOffer(data.tradeId, true);
      modal.classList.add('hidden');
    };

    document.getElementById('btn-decline-trade').onclick = () => {
      AudioPlayer.playTrapSound();
      GameClient.respondTradeOffer(data.tradeId, false);
      modal.classList.add('hidden');
    };
  }

  /* ----------------------------------------------------
     QUICK TACTICAL CHAT UI
     ---------------------------------------------------- */
  openQuickChatModal() {
    const modal = document.getElementById('modal-quick-chat');
    modal.classList.remove('hidden');

    const list = document.getElementById('quick-chat-options');
    list.innerHTML = '';

    const quickMessages = [
      '🤝 Kim takas yapmak ister?',
      '👑 Yıldızıma kimse dokunmasın!',
      '😱 İntikamım çok fena olacak!',
      '👏 Harika hamle, tebrikler!',
      '🍀 Biraz zar şansı verin!',
      '🔥 Haydi hızlanın, sıra kimde?'
    ];

    quickMessages.forEach(msg => {
      const btn = document.createElement('button');
      btn.className = 'chat-preset-btn';
      btn.innerText = msg;
      btn.onclick = () => {
        AudioPlayer.playClick();
        GameClient.sendQuickChat(msg);
        modal.classList.add('hidden');
      };
      list.appendChild(btn);
    });

    document.getElementById('btn-close-quick-chat').onclick = () => {
      modal.classList.add('hidden');
    };
  }

  showPlayerChatBubble(playerId, playerName, text) {
    AudioPlayer.playEmoteReaction();

    // Spawn floating speech bubble over player HUD card
    const targetCard = document.getElementById(`player-card-${playerId}`);
    const bubble = document.createElement('div');
    bubble.className = 'speech-bubble-pop';
    bubble.innerHTML = `<strong>${escapeHtml(playerName)}:</strong> ${escapeHtml(text)}`;

    if (targetCard) {
      targetCard.appendChild(bubble);
    } else {
      document.body.appendChild(bubble);
    }

    setTimeout(() => {
      bubble.remove();
    }, 3800);
  }

  renderDicePips(val) {
    const pipsCount = Math.max(1, Math.min(6, val));
    if (pipsCount === 1) {
      return '<span class="pip pip-center"></span>';
    } else if (pipsCount === 2) {
      return '<span class="pip pip-top-left"></span><span class="pip pip-bottom-right"></span>';
    } else if (pipsCount === 3) {
      return '<span class="pip pip-top-left"></span><span class="pip pip-center"></span><span class="pip pip-bottom-right"></span>';
    } else if (pipsCount === 4) {
      return '<span class="pip pip-top-left"></span><span class="pip pip-top-right"></span><span class="pip pip-bottom-left"></span><span class="pip pip-bottom-right"></span>';
    } else if (pipsCount === 5) {
      return '<span class="pip pip-top-left"></span><span class="pip pip-top-right"></span><span class="pip pip-center"></span><span class="pip pip-bottom-left"></span><span class="pip pip-bottom-right"></span>';
    } else {
      return '<span class="pip pip-top-left"></span><span class="pip pip-top-right"></span><span class="pip pip-mid-left"></span><span class="pip pip-mid-right"></span><span class="pip pip-bottom-left"></span><span class="pip pip-bottom-right"></span>';
    }
  }

  showDiceRollAnimation(data) {
    AudioPlayer.playDiceRoll();
    const overlay = document.getElementById('dice-roll-overlay');
    if (!overlay) return;
    overlay.classList.remove('hidden');

    const titleBox = document.getElementById('dice-roller-name');
    const resultBox = document.getElementById('dice-result-value');
    const dieBox = document.getElementById('dice-cube-1');
    const pipFace = document.getElementById('dice-pip-face');

    if (titleBox) titleBox.innerText = `🎲 ${data.playerName} ZAR ATIYOR...`;
    if (resultBox) resultBox.innerText = 'Zar dönüyor...';

    if (dieBox) dieBox.className = 'single-dice-box rolling';

    const speed = window.GAME_SPEED || 1;
    const tumbleTime = Math.max(160, Math.round(550 / speed));
    const postTime = Math.max(200, Math.round(650 / speed));

    // Cycle through values rapidly while tumbling
    let cycleCount = 0;
    const interval = setInterval(() => {
      cycleCount++;
      const randVal = Math.floor(Math.random() * 6) + 1;
      if (pipFace) pipFace.innerHTML = this.renderDicePips(randVal);
      if (cycleCount > 8) clearInterval(interval);
    }, Math.max(25, Math.round(55 / speed)));

    let timer2 = null;
    const finishRoll = () => {
      clearInterval(interval);
      if (dieBox) dieBox.className = 'single-dice-box landed';
      if (pipFace) pipFace.innerHTML = this.renderDicePips(data.totalRoll);

      if (resultBox) {
        resultBox.innerText = `🎉 ${data.playerName}: ${data.totalRoll} ADIM!`;
      }

      AudioPlayer.playStep();

      timer2 = setTimeout(() => {
        overlay.classList.add('hidden');
        overlay.onclick = null;
      }, postTime);
    };

    const timer1 = setTimeout(finishRoll, tumbleTime);

    // Instant close on click / tap anywhere on overlay (No waiting!)
    overlay.onclick = () => {
      clearTimeout(timer1);
      if (timer2) clearTimeout(timer2);
      clearInterval(interval);
      overlay.classList.add('hidden');
      overlay.onclick = null;
    };
  }

  showTileEventCard(data) {
    const overlay = document.getElementById('tile-event-overlay');
    if (!overlay) return;
    overlay.classList.remove('hidden');

    const card = document.getElementById('tile-event-card');
    const icon = document.getElementById('tile-event-icon');
    const title = document.getElementById('tile-event-title');
    const desc = document.getElementById('tile-event-desc');
    const avatarBox = document.getElementById('tile-event-avatar');

    if (data.avatar && AVATAR_DEFS[data.avatar]) {
      avatarBox.innerHTML = AVATAR_DEFS[data.avatar].svg;
      avatarBox.classList.remove('hidden');
    } else {
      avatarBox.classList.add('hidden');
    }

    if (data.category === 'penalty') {
      card.className = 'tile-event-card penalty-mode screen-shake-anim';
      document.body.classList.add('screen-shake-anim');
      setTimeout(() => document.body.classList.remove('screen-shake-anim'), 450);

      if (data.sound === 'slip') AudioPlayer.playSlipSound();
      else if (data.sound === 'jail') AudioPlayer.playJailSound();
      else if (data.sound === 'trap') AudioPlayer.playTrapSound();
      else AudioPlayer.playTrapSound();
    } else if (data.category === 'reward') {
      card.className = 'tile-event-card reward-mode';
      if (data.sound === 'fanfare') AudioPlayer.playStarFanfare();
      else AudioPlayer.playCoinSound();
    } else {
      card.className = 'tile-event-card neutral-mode';
      if (data.sound === 'warp') AudioPlayer.playWarpSound();
      else AudioPlayer.playClick();
    }

    icon.innerText = data.category === 'penalty' ? (data.sound === 'slip' ? '🍌' : (data.sound === 'jail' ? '⛓️' : '💥')) : (data.amount ? '🪙' : '🎁');
    title.innerText = data.title;
    desc.innerText = data.message;

    const speed = window.GAME_SPEED || 1;
    const hideTimeoutMs = Math.max(450, Math.round(1400 / speed));

    const hideTimer = setTimeout(() => {
      overlay.classList.add('hidden');
      overlay.onclick = null;
    }, hideTimeoutMs);

    // Instant close on click / tap anywhere on overlay (No waiting!)
    overlay.onclick = () => {
      clearTimeout(hideTimer);
      overlay.classList.add('hidden');
      overlay.onclick = null;
    };
  }

  showMysteryRoulette(data) {
    const modal = document.getElementById('modal-mystery');
    if (!modal) return;
    modal.classList.remove('hidden');

    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const atTextDict = {
      tr: 'Şans Karosunda!',
      en: 'is on Fortune Tile!',
      de: 'ist auf dem Glücksfeld!',
      es: '¡está en la casilla de la suerte!',
      fr: 'est sur la case chance !'
    };
    const spinTextDict = {
      tr: 'Çark dönüyor...',
      en: 'Spinning the wheel...',
      de: 'Das Rad dreht sich...',
      es: 'La ruleta está girando...',
      fr: 'La roue tourne...'
    };
    const diceLabels = { tr: '🎲 Çift Zar', en: '🎲 2x Dice', de: '🎲 2x Würfel', es: '🎲 2x Dado', fr: '🎲 2x Dés' };
    const shieldLabels = { tr: '🛡️ Kalkan', en: '🛡️ Shield', de: '🛡️ Schild', es: '🛡️ Escudo', fr: '🛡️ Bouclier' };
    const rocketLabels = { tr: '🚀 Roket', en: '🚀 Rocket', de: '🚀 Rakete', es: '🚀 Cohete', fr: '🚀 Fusée' };
    const starLabels = { tr: '⭐ Yıldız', en: '⭐ Star', de: '⭐ Stern', es: '⭐ Estrella', fr: '⭐ Étoile' };
    const chestLabels = { tr: '🎁 Sandık', en: '🎁 Chest', de: '🎁 Kiste', es: '🎁 Cofre', fr: '🎁 Coffre' };
    const crownLabels = { tr: '👑 Taç', en: '👑 Crown', de: '👑 Krone', es: '👑 Corona', fr: '👑 Couronne' };

    document.getElementById('mystery-player-text').innerText = `${data.playerName} ${atTextDict[lang] || atTextDict.tr}`;
    const resultBox = document.getElementById('mystery-event-result');
    resultBox.innerText = spinTextDict[lang] || spinTextDict.tr;

    const canvas = document.getElementById('roulette-canvas');
    const pointer = document.getElementById('roulette-pointer');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width;
    const H = canvas.height;
    const cx = W / 2;
    const cy = H / 2;
    const R = W / 2 - 8;

    const segments = [
      { text: '+15 🪙', color: '#F59E0B', textColor: '#FFFFFF' },
      { text: diceLabels[lang] || diceLabels.tr, color: '#3B82F6', textColor: '#FFFFFF' },
      { text: shieldLabels[lang] || shieldLabels.tr, color: '#10B981', textColor: '#FFFFFF' },
      { text: rocketLabels[lang] || rocketLabels.tr, color: '#EC4899', textColor: '#FFFFFF' },
      { text: '+10 🪙', color: '#F97316', textColor: '#FFFFFF' },
      { text: starLabels[lang] || starLabels.tr, color: '#EAB308', textColor: '#FFFFFF' },
      { text: chestLabels[lang] || chestLabels.tr, color: '#8B5CF6', textColor: '#FFFFFF' },
      { text: crownLabels[lang] || crownLabels.tr, color: '#06B6D4', textColor: '#FFFFFF' }
    ];

    const numSegs = segments.length;
    const arc = (Math.PI * 2) / numSegs;

    const drawWheel = (rotationAngle) => {
      ctx.clearRect(0, 0, W, H);

      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(rotationAngle);

      // Outer rim
      ctx.beginPath();
      ctx.arc(0, 0, R, 0, Math.PI * 2);
      ctx.fillStyle = '#CBD5E1';
      ctx.fill();

      // Wedges
      for (let i = 0; i < numSegs; i++) {
        const seg = segments[i];
        const angle = i * arc;

        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, R - 6, angle, angle + arc);
        ctx.closePath();
        ctx.fillStyle = seg.color;
        ctx.fill();
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Wedge Text
        ctx.save();
        ctx.rotate(angle + arc / 2);
        ctx.textAlign = 'right';
        ctx.fillStyle = seg.textColor;
        ctx.font = 'bold 12px sans-serif';
        ctx.fillText(seg.text, R - 16, 4);
        ctx.restore();
      }

      // Center stud
      ctx.beginPath();
      ctx.arc(0, 0, 24, 0, Math.PI * 2);
      ctx.fillStyle = '#FFFFFF';
      ctx.fill();
      ctx.strokeStyle = '#F59E0B';
      ctx.lineWidth = 3;
      ctx.stroke();

      ctx.restore();
    };

    // Spin animation with physics (scaled by game speed)
    let currentAngle = 0;
    const targetSpin = Math.PI * 2 * (4 + Math.random() * 2);
    const speed = window.GAME_SPEED || 1;
    const duration = Math.max(700, Math.round(2400 / speed));
    const postWait = Math.max(350, Math.round(1800 / speed));
    const startT = performance.now();
    let lastTickAngle = 0;
    let modalTimer = null;
    let isFinished = false;

    // Instant close on click / tap anywhere on modal
    modal.onclick = () => {
      if (modalTimer) clearTimeout(modalTimer);
      modal.classList.add('hidden');
      modal.onclick = null;
    };

    const animateSpin = (now) => {
      if (isFinished || modal.classList.contains('hidden')) return;
      const elapsed = now - startT;
      const progress = Math.min(1, elapsed / duration);
      // Cubic ease out
      const ease = 1 - Math.pow(1 - progress, 3);
      currentAngle = targetSpin * ease;
      drawWheel(currentAngle);

      // Flapper pointer tick sound
      if (Math.abs(currentAngle - lastTickAngle) >= arc) {
        lastTickAngle = currentAngle;
        if (pointer) {
          pointer.style.transform = 'translateX(-50%) rotate(-18deg)';
          setTimeout(() => { if (pointer) pointer.style.transform = 'translateX(-50%) rotate(0deg)'; }, 40);
        }
        AudioPlayer.playWheelTick();
      }

      if (progress < 1) {
        requestAnimationFrame(animateSpin);
      } else {
        // Spin finished
        isFinished = true;
        AudioPlayer.playStarFanfare();
        const wonDict = {
          tr: 'Büyük Ödül Kazandın!',
          en: 'Big Prize Won!',
          de: 'Großer Preis gewonnen!',
          es: '¡Ganaste el Gran Premio!',
          fr: 'Grand Prix remporté !'
        };
        const wonText = wonDict[lang] || wonDict.tr;
        resultBox.innerText = `✨ ${data.eventName || wonText}!`;
        this.triggerConfetti();
        modalTimer = setTimeout(() => {
          modal.classList.add('hidden');
          modal.onclick = null;
        }, postWait);
      }
    };

    requestAnimationFrame(animateSpin);
  }

  getItemInfo(itemId, lang) {
    const itemTranslations = {
      double_dice: {
        tr: { name: 'Çift Zar', desc: 'Bir sonraki turda 2 zar birden atarsın (2-12 adım).' },
        en: { name: 'Double Dice', desc: 'Roll 2 dice at once on your next turn (2-12 steps).' },
        de: { name: 'Doppelwürfel', desc: 'Würfle im nächsten Zug mit 2 Würfeln (2-12 Felder).' },
        es: { name: 'Dado Doble', desc: 'Tira 2 dados a la vez en tu próximo turno (2-12 casillas).' },
        fr: { name: 'Double Dé', desc: 'Lancez 2 dés à la fois lors de votre prochain tour (2-12 cases).' }
      },
      rocket: {
        tr: { name: 'Süper Roket', desc: 'Hedefine doğru direkt 7 adım uçar!' },
        en: { name: 'Super Rocket', desc: 'Blast forward 7 steps towards your goal!' },
        de: { name: 'Super-Rakete', desc: 'Fliege 7 Felder direkt nach vorne!' },
        es: { name: 'Súper Cohete', desc: '¡Vuela 7 casillas hacia adelante!' },
        fr: { name: 'Super Fusée', desc: 'Foncez de 7 cases directement vers l\'avant !' }
      },
      shield: {
        tr: { name: 'Manyetik Kalkan', desc: 'Tuzaklara ve altın çalma hamlelerine karşı 2 tur korur.' },
        en: { name: 'Magnetic Shield', desc: 'Protects against traps and coin steals for 2 turns.' },
        de: { name: 'Magnetschild', desc: 'Schützt 2 Runden vor Fallen und Münzdiebstahl.' },
        es: { name: 'Escudo Magnético', desc: 'Protege contra trampas y robos durante 2 turnos.' },
        fr: { name: 'Bouclier Magnétique', desc: 'Protège contre les pièges et les vols pendant 2 tours.' }
      },
      magnet: {
        tr: { name: 'Altın Mıknatısı', desc: 'Bütün rakiplerden 4\'er altın çeker!' },
        en: { name: 'Gold Magnet', desc: 'Draws 4 coins from every opponent!' },
        de: { name: 'Goldmagnet', desc: 'Zieht 4 Münzen von jedem Gegner ab!' },
        es: { name: 'Imán de Oro', desc: '¡Roba 4 monedas a cada oponente!' },
        fr: { name: 'Aimant à Pièces', desc: 'Aspire 4 pièces de chaque adversaire !' }
      },
      banana: {
        tr: { name: 'Muz Kabuğu', desc: 'Bulunduğun karoya tuzak kur; üstüne basan 3 adım geri kayar!' },
        en: { name: 'Banana Peel', desc: 'Lay a slippery trap; rivals slip back 3 steps!' },
        de: { name: 'Bananenschale', desc: 'Platziere eine Falle; wer drauftritt, rutscht 3 Felder zurück!' },
        es: { name: 'Piel de Plátano', desc: 'Pon una trampa; ¡los rivales resbalan 3 casillas atrás!' },
        fr: { name: 'Peau de Banane', desc: 'Posez un piège ; les rivaux glissent de 3 cases en arrière !' }
      },
      swap_wand: {
        tr: { name: 'Takas Asası', desc: 'İstediğin bir rakiple haritadaki yerlerini takas et!' },
        en: { name: 'Warp Wand', desc: 'Swap board positions with an opponent of your choice!' },
        de: { name: 'Tauschstab', desc: 'Tausche deine Position mit einem beliebigen Gegner!' },
        es: { name: 'Varita de Canje', desc: '¡Intercambia casillas con el oponente que elijas!' },
        fr: { name: 'Baguette d\'Échange', desc: 'Échangez votre place sur le plateau avec un rival !' }
      },
      ghost_cloak: {
        tr: { name: 'Hayalet Pelerin', desc: '2 tur boyunca hiçbir tuzaktan veya cezadan etkilenmezsin!' },
        en: { name: 'Ghost Cloak', desc: 'Bypass all traps, police and penalties for 2 turns!' },
        de: { name: 'Geisterumhang', desc: 'Ignoriere 2 Runden lang alle Fallen und Strafen!' },
        es: { name: 'Capa Fantasma', desc: '¡Inmune a trampas, multas y cárcel por 2 turnos!' },
        fr: { name: 'Cape Fantôme', desc: 'Traversez pièges et pénalités indemne pendant 2 tours !' }
      },
      lucky_die: {
        tr: { name: 'Şanslı Altın Zar', desc: 'Sıradaki zarda 1-6 arasında istediğin sayıyı sen seçersin!' },
        en: { name: 'Lucky Gold Die', desc: 'Pick your exact roll from 1 to 6 on your next turn!' },
        de: { name: 'Glückswürfel', desc: 'Wähle deine exakte Augenzahl (1-6) beim nächsten Wurf!' },
        es: { name: 'Dado Dorado de la Suerte', desc: '¡Elige tu número exacto del 1 al 6 en el próximo tiro!' },
        fr: { name: 'Dé d\'Or Porte-Bonheur', desc: 'Choisissez votre score exact de 1 à 6 au prochain lancer !' }
      }
    };
    const curLang = lang || (window.i18n ? window.i18n.getLang() : 'tr');
    if (itemTranslations[itemId] && itemTranslations[itemId][curLang]) {
      return itemTranslations[itemId][curLang];
    }
    return { name: itemId, desc: '' };
  }

  openShopModal(items, isCurrentPlayer) {
    const modal = document.getElementById('modal-shop');
    modal.classList.remove('hidden');

    const list = document.getElementById('shop-items-grid');
    list.innerHTML = '';

    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const goldSuffix = { tr: 'Altın', en: 'Coins', de: 'Münzen', es: 'Monedas', fr: 'Pièces' };

    const myPlayer = GameClient.state.players.find(p => GameClient.player && p.sessionId === GameClient.player.sessionId);
    const myCoins = myPlayer ? myPlayer.coins : 0;

    items.forEach(item => {
      const canAfford = myCoins >= item.cost;
      const trans = this.getItemInfo(item.id, lang);
      const card = document.createElement('div');
      card.className = `shop-item-card ${!canAfford ? 'disabled' : ''}`;
      card.innerHTML = `
        <div class="shop-item-icon">${getIcon(item.icon, '🎁')}</div>
        <div class="shop-item-name">${trans.name || item.name}</div>
        <div class="shop-item-desc">${trans.desc || item.desc}</div>
        <button class="shop-buy-btn" ${(!canAfford || !isCurrentPlayer) ? 'disabled' : ''}>
          🪙 ${item.cost} ${goldSuffix[lang] || 'Altın'}
        </button>
      `;

      card.querySelector('.shop-buy-btn').onclick = () => {
        AudioPlayer.playCoinSound();
        GameClient.buyItem(item.id);
        modal.classList.add('hidden');
      };

      list.appendChild(card);
    });

    document.getElementById('btn-close-shop').onclick = () => {
      AudioPlayer.playClick();
      modal.classList.add('hidden');
      if (isCurrentPlayer) GameClient.closeShop();
    };
  }

  openInventoryModal() {
    const modal = document.getElementById('modal-inventory');
    modal.classList.remove('hidden');

    const list = document.getElementById('inventory-items-grid');
    list.innerHTML = '';

    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const emptyMsgDict = {
      tr: 'Çantan şu an boş! Dükkandan ya da şans kutularından eşya topla.',
      en: 'Your bag is empty! Collect items from the shop or fortune tiles.',
      de: 'Dein Inventar ist leer! Sammle Gegenstände im Laden oder auf Glücksfeldern.',
      es: '¡Tu mochila está vacía! Consigue objetos en la tienda o casillas de suerte.',
      fr: 'Votre sac est vide ! Obtenez des objets à la boutique ou sur les cases chance.'
    };
    const useBtnDict = { tr: 'Kullan', en: 'Use', de: 'Nutzen', es: 'Usar', fr: 'Utiliser' };

    const state = GameClient.state;
    const myPlayer = state ? state.players.find(p => GameClient.player && p.sessionId === GameClient.player.sessionId) : null;
    const activePlayer = state ? state.players[state.turnIndex] : null;
    const isMyTurn = (myPlayer && activePlayer && myPlayer.id === activePlayer.id);

    if (!myPlayer || myPlayer.inventory.length === 0) {
      list.innerHTML = `<div class="empty-inv-msg">${emptyMsgDict[lang] || emptyMsgDict.tr}</div>`;
    } else {
      myPlayer.inventory.forEach(itemId => {
        const itemDef = SHOP_ITEMS.find(i => i.id === itemId) || { name: itemId, icon: 'dices', desc: 'Özel eşya' };
        const trans = this.getItemInfo(itemId, lang);
        const card = document.createElement('div');
        card.className = 'inventory-item-card';
        card.innerHTML = `
          <div class="inv-icon">${getIcon(itemDef.icon, '🎁')}</div>
          <div class="inv-info">
            <div class="inv-name">${trans.name || itemDef.name}</div>
            <div class="inv-desc">${trans.desc || itemDef.desc}</div>
          </div>
          <button class="inv-use-btn" ${!isMyTurn ? 'disabled' : ''}>${useBtnDict[lang] || 'Kullan'}</button>
        `;

        card.querySelector('.inv-use-btn').onclick = () => {
          modal.classList.add('hidden');
          if (itemId === 'lucky_die') {
            this.showLuckyDiePicker();
          } else if (itemId === 'swap_wand') {
            this.showSwapWandTargetPicker();
          } else {
            AudioPlayer.playStarFanfare();
            GameClient.useItem(itemId);
            const feedbackDict = {
              double_dice: {
                tr: '🎲🎲 2X Çift Zar hazır! Sıradaki turunda 2 zar birden atacaksın.',
                en: '🎲🎲 Double Dice ready! You will roll 2 dice next turn.',
                de: '🎲🎲 Doppelwürfel bereit! Du würfelst 2 Würfel.',
                es: '🎲🎲 ¡Dado Doble listo! Tirarás 2 dados.',
                fr: '🎲🎲 Double Dé prêt ! Vous lancerez 2 dés.'
              },
              rocket: {
                tr: '🚀 Roket ateşlendi! 5 kare ileri fırladın!',
                en: '🚀 Rocket fired! Blasted 5 steps forward!',
                de: '🚀 Rakete gezündet! 5 Felder nach vorne geflogen!',
                es: '🚀 ¡Cohete disparado! ¡5 casillas hacia adelante!',
                fr: '🚀 Fusée lancée ! 5 cases en avant !'
              },
              magnet: {
                tr: '🧲 Mıknatıs çekildi! Rakiplerden altın topladın.',
                en: '🧲 Magnet pulled! Collected coins from rivals.',
                de: '🧲 Magnet aktiviert! Münzen von Gegnern geholt.',
                es: '🧲 ¡Imán activado! Has atraído monedas de los rivales.',
                fr: '🧲 Aimant activé ! Pièces attirées des rivaux.'
              },
              shield: {
                tr: '🛡️ Koruma Kalkanı aktif! 2 tur tuzaklara bağışıklısın.',
                en: '🛡️ Shield active! Immune to traps for 2 turns.',
                de: '🛡️ Schild aktiv! 2 Runden vor Fallen geschützt.',
                es: '🛡️ ¡Escudo activo! Inmune a trampas por 2 turnos.',
                fr: '🛡️ Bouclier actif ! Immunisé aux pièges 2 tours.'
              },
              banana: {
                tr: '🍌 Muz Kabuğu yere bırakıldı! Arkandan gelen kayacak.',
                en: '🍌 Banana peel placed! Rivals behind will slip.',
                de: '🍌 Banane platziert! Nachfolgende rutschen aus.',
                es: '🍌 ¡Piel de plátano colocada! Quien la pise resbalará.',
                fr: '🍌 Peau de banane posée ! Les suivants glisseront.'
              },
              ghost_cloak: {
                tr: '👻 Hayalet Pelerini aktif! 2 tur tüm cezalardan ve tuzaklardan muafsın.',
                en: '👻 Ghost Cloak active! Immune to all penalties and traps for 2 turns.',
                de: '👻 Geisterumhang aktiv! 2 Runden immun gegen alle Strafen.',
                es: '👻 ¡Capa Fantasma activa! Inmune a multas y trampas por 2 turnos.',
                fr: '👻 Cape Fantôme active ! Immunisé contre tous les pièges 2 tours.'
              }
            };
            const fMsg = (feedbackDict[itemId] && feedbackDict[itemId][lang]) || (feedbackDict[itemId] && feedbackDict[itemId].tr) || '✨ Eşya kullanıldı!';
            this.showToast(fMsg, 'success');
          }
        };

        list.appendChild(card);
      });
    }

    document.getElementById('btn-close-inventory').onclick = () => {
      AudioPlayer.playClick();
      modal.classList.add('hidden');
    };
  }

  showLuckyDiePicker() {
    const modal = document.getElementById('modal-lucky-die');
    if (!modal) return;
    modal.classList.remove('hidden');

    const grid = document.getElementById('lucky-die-grid');
    grid.innerHTML = '';

    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const stepWord = { tr: 'Adım', en: 'Steps', de: 'Schritte', es: 'Pasos', fr: 'Pas' }[lang] || 'Adım';

    const diceIcons = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];
    for (let i = 1; i <= 6; i++) {
      const btn = document.createElement('button');
      btn.className = 'lucky-die-btn';
      btn.innerHTML = `<span>${diceIcons[i - 1]}</span><span class="die-dot">${i} ${stepWord}</span>`;
      btn.onclick = () => {
        AudioPlayer.playStarFanfare();
        GameClient.useItem('lucky_die', i);
        modal.classList.add('hidden');
        const lockedMsg = {
          tr: `🎯 Sıradaki zarın ${i} olarak kilitlendi! Zarı atabilirsin.`,
          en: `🎯 Next die roll locked to ${i}! Roll when ready.`,
          de: `🎯 Nächster Wurf auf ${i} fixiert! Würfle jetzt.`,
          es: `🎯 ¡Próximo dado fijado en ${i}! Tira cuando quieras.`,
          fr: `🎯 Prochain dé verrouillé à ${i} ! Lancez quand vous voulez.`
        }[lang] || `🎯 Sıradaki zarın ${i} olarak kilitlendi!`;
        this.showToast(lockedMsg, 'success');
      };
      grid.appendChild(btn);
    }

    const closeBtn = document.getElementById('btn-close-lucky-die');
    if (closeBtn) {
      closeBtn.onclick = () => {
        AudioPlayer.playClick();
        modal.classList.add('hidden');
      };
    }
  }

  showSwapWandTargetPicker() {
    const modal = document.getElementById('modal-swap-wand');
    if (!modal) return;
    modal.classList.remove('hidden');

    const list = document.getElementById('swap-wand-list');
    list.innerHTML = '';

    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const state = GameClient.state;
    const myPlayer = state ? state.players.find(p => GameClient.player && p.sessionId === GameClient.player.sessionId) : null;
    const rivals = state ? state.players.filter(p => !myPlayer || p.id !== myPlayer.id) : [];

    const noRivalsMsg = {
      tr: 'Takas yapılabilecek başka oyuncu yok!',
      en: 'No other players available to swap with!',
      de: 'Keine anderen Spieler zum Tauschen verfügbar!',
      es: '¡No hay otros jugadores para intercambiar!',
      fr: 'Aucun autre joueur disponible pour l\'échange !'
    }[lang] || 'Takas yapılabilecek başka oyuncu yok!';

    const swapActionLabel = {
      tr: 'Konum Değiş',
      en: 'Swap Place',
      de: 'Position tauschen',
      es: 'Cambiar lugar',
      fr: 'Changer de place'
    }[lang] || 'Konum Değiş';

    if (rivals.length === 0) {
      list.innerHTML = `<div style="color:#64748B; font-size:13px; text-align:center; padding:16px;">${noRivalsMsg}</div>`;
    } else {
      rivals.forEach(r => {
        const card = document.createElement('div');
        card.className = 'swap-wand-card';
        card.innerHTML = `
          <div style="display:flex; align-items:center; min-width:0;">
            <div class="swap-wand-avatar">${r.avatar === 'cat' ? '🐱' : (r.avatar === 'dino' ? '🦖' : (r.avatar === 'ninja' ? '🥷' : '🤠'))}</div>
            <div class="swap-wand-info">
              <div class="swap-wand-name">${escapeHtml(r.name)}</div>
              <div class="swap-wand-sub">#${r.position} • ⭐${r.stars} • 🪙${r.coins}</div>
            </div>
          </div>
          <button class="swap-wand-action-btn">${swapActionLabel}</button>
        `;
        card.onclick = () => {
          AudioPlayer.playStarFanfare();
          GameClient.useItem('swap_wand', r.id);
          modal.classList.add('hidden');
          const swappedMsg = {
            tr: `🪄 ${r.name} ile konumlar takas edildi!`,
            en: `🪄 Swapped positions with ${r.name}!`,
            de: `🪄 Position mit ${r.name} getauscht!`,
            es: `🪄 ¡Has intercambiado lugares con ${r.name}!`,
            fr: `🪄 Positions échangées avec ${r.name} !`
          }[lang] || `🪄 ${r.name} ile konumlar takas edildi!`;
          this.showToast(swappedMsg, 'success');
        };
        list.appendChild(card);
      });
    }

    const closeBtn = document.getElementById('btn-close-swap-wand');
    if (closeBtn) {
      closeBtn.onclick = () => {
        AudioPlayer.playClick();
        modal.classList.add('hidden');
      };
    }
  }

  openEmoteWheel() {
    const modal = document.getElementById('modal-emotes');
    modal.classList.remove('hidden');

    const grid = document.getElementById('emotes-grid');
    grid.innerHTML = '';

    PlayerProgression.getUnlockedEmotes().forEach(em => {
      const btn = document.createElement('button');
      btn.className = 'emote-bubble-btn';
      btn.innerHTML = `<span class="em-char">${em.char}</span><span class="em-label">${em.name}</span>`;

      btn.onclick = () => {
        AudioPlayer.playEmoteReaction();
        GameClient.sendEmote(em.id);
        modal.classList.add('hidden');
      };

      grid.appendChild(btn);
    });

    document.getElementById('btn-close-emotes').onclick = () => {
      modal.classList.add('hidden');
    };
  }

  showPlayerEmote(playerId, emoteId) {
    const emDef = EMOTE_DEFS[emoteId] || { char: '👋' };
    AudioPlayer.playEmoteReaction();

    const bubble = document.createElement('div');
    bubble.className = 'floating-emote-bubble';
    bubble.innerText = emDef.char;
    document.body.appendChild(bubble);

    setTimeout(() => {
      bubble.remove();
    }, 2200);
  }

  openQrModal() {
    const modal = document.getElementById('modal-qr');
    modal.classList.remove('hidden');

    const joinUrl = `${window.location.origin}/?room=${GameClient.roomId || ''}`;
    document.getElementById('lan-qr-image').src = `/api/qr?url=${encodeURIComponent(joinUrl)}`;
    document.getElementById('lan-qr-link-text').innerText = joinUrl;

    document.getElementById('btn-copy-lan-link').onclick = () => {
      navigator.clipboard.writeText(joinUrl);
      this.showToast('Link kopyalandı!', 'success');
    };

    document.getElementById('btn-close-qr').onclick = () => {
      modal.classList.add('hidden');
    };
  }

  showMiniGameResults(data) {
    AudioPlayer.playStarFanfare();
    const rankedText = data.ranked.map((p, i) => `${i + 1}. ${p.name}: ${p.score} Puan`).join(' | ');
    this.showToast(`🏆 Mini-Oyun Sonuçları: ${rankedText}`, 'success');
  }

  showVictoryPodium(data) {
    AudioPlayer.playStarFanfare();
    const modal = document.getElementById('modal-gameover');
    modal.classList.remove('hidden');

    this.triggerConfetti();

    // Reward XP with robust fallback matching
    const me = data.leaderboard.find(p =>
      (GameClient.player && (p.sessionId === GameClient.player.sessionId || p.id === GameClient.player.id)) ||
      (p.name === PlayerProgression.profile.name)
    );

    if (me) {
      const xpResult = PlayerProgression.addXp(me.xpEarned);
      const xpRewardElem = document.getElementById('go-xp-reward');
      if (xpRewardElem) xpRewardElem.innerText = `+${me.xpEarned} XP Kazandın!`;

      const fillBar = document.getElementById('go-xp-bar-fill');
      const detail = document.getElementById('go-xp-detail');
      if (fillBar) {
        const pct = Math.min(100, Math.floor((PlayerProgression.profile.xp / PlayerProgression.profile.xpToNext) * 100));
        setTimeout(() => { fillBar.style.width = `${pct}%`; }, 150);
      }
      if (detail) {
        detail.innerText = `Lv. ${PlayerProgression.profile.level} • ${PlayerProgression.profile.xp} / ${PlayerProgression.profile.xpToNext} XP`;
      }

      if (xpResult.leveledUp) {
        AudioPlayer.playLevelUp();
        const lvlBadge = document.getElementById('go-level-up');
        if (lvlBadge) {
          lvlBadge.classList.remove('hidden');
          lvlBadge.innerHTML = `🎉 <strong>TEBRİKLER! Seviye ${xpResult.newLevel} oldun!</strong> Yeni gardırop ve unvan ödülleri açıldı!`;
        }
      }
      this.renderProfileCard();
    }

    const list = document.getElementById('go-podium-list');
    list.innerHTML = '';
    data.leaderboard.forEach((p, idx) => {
      const avatarDef = AVATAR_DEFS[p.avatar] || AVATAR_DEFS.builder;
      const card = document.createElement('div');
      card.className = `podium-rank-card rank-${idx + 1}`;
      card.innerHTML = `
        <div class="podium-badge">${idx === 0 ? '🥇 1. Şampiyon' : (idx === 1 ? '🥈 2.' : '🥉 3.')}</div>
        <div class="podium-avatar">${avatarDef.svg}</div>
        <div class="podium-pname">${escapeHtml(p.name)}</div>
        <div class="podium-score">⭐ ${p.stars} Yıldız • 🪙 ${p.coins} Altın</div>
      `;
      list.appendChild(card);
    });

    document.getElementById('btn-back-home').onclick = () => {
      modal.classList.add('hidden');
      window.location.reload();
    };
  }

  triggerConfetti() {
    const container = document.getElementById('confetti-container');
    if (!container) return;
    container.innerHTML = '';

    const colors = ['#EF4444', '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899'];
    for (let i = 0; i < 70; i++) {
      const conf = document.createElement('div');
      conf.className = 'confetti-piece';
      conf.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
      conf.style.left = `${Math.random() * 100}%`;
      conf.style.animationDelay = `${Math.random() * 2}s`;
      conf.style.animationDuration = `${2 + Math.random() * 3}s`;
      container.appendChild(conf);
    }
  }

  renderEventLog(logs) {
    const feed = document.getElementById('hud-event-feed');
    if (!feed) return;
    feed.innerHTML = '';
    logs.slice(0, 3).forEach(log => {
      const item = document.createElement('div');
      item.className = 'hud-log-item';
      item.innerHTML = `<span class="log-time">${escapeHtml(log.time)}</span> ${escapeHtml(log.text)}`;
      feed.appendChild(item);
    });
  }

  showToast(text, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerText = text;
    this.toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('fade-out');
      setTimeout(() => toast.remove(), 400);
    }, 3500);
  }
}

const UI = new UIEngine();
window.UI = UI;


/* =================== 10. FX LAYER (GAME FEEL) =================== */
/**
 * FX layer — "game feel" for Parti Dünyası.
 *
 * Wraps a few UI hooks instead of rewriting them:
 *  - 3D lacquered dice (GSAP) instead of the flat 2D die
 *  - floating +/- coin and star numbers over player cards
 *  - a short board shake on penalties
 *  - confetti for stars, mini-game wins and the final podium (canvas-confetti)
 *  - one orchestrated "your turn" sweep
 * Everything degrades gracefully: without GSAP / confetti, or with
 * prefers-reduced-motion, the game keeps working with simpler feedback.
 */
(function () {
  'use strict';

  const hasGsap = typeof window.gsap !== 'undefined';
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const TEXT = {
    tr: { rolling: (n) => `${n} zar atıyor`, steps: (n, s) => `${n}: ${s} adım`, yourTurn: 'Sıra sende', yourTurnSub: 'Zar atmak için Boşluk tuşuna bas', you: 'Sen' },
    en: { rolling: (n) => `${n} is rolling`, steps: (n, s) => `${n}: ${s} steps`, yourTurn: 'Your turn', yourTurnSub: 'Press Space to roll the dice', you: 'You' },
    de: { rolling: (n) => `${n} würfelt`, steps: (n, s) => `${n}: ${s} Felder`, yourTurn: 'Du bist dran', yourTurnSub: 'Drücke die Leertaste zum Würfeln', you: 'Du' },
    es: { rolling: (n) => `${n} tira los dados`, steps: (n, s) => `${n}: ${s} casillas`, yourTurn: 'Tu turno', yourTurnSub: 'Pulsa Espacio para tirar el dado', you: 'Tú' },
    fr: { rolling: (n) => `${n} lance les dés`, steps: (n, s) => `${n} : ${s} cases`, yourTurn: 'À toi de jouer', yourTurnSub: 'Appuie sur Espace pour lancer le dé', you: 'Toi' }
  };
  const t = () => {
    const lang = (window.i18n && window.i18n.getLang && window.i18n.getLang()) || 'tr';
    return TEXT[lang] || TEXT.tr;
  };

  // Pip layout on a 3x3 grid (cells 1..9, row-major)
  const PIP_CELLS = {
    1: [5],
    2: [1, 9],
    3: [1, 5, 9],
    4: [1, 3, 7, 9],
    5: [1, 3, 5, 7, 9],
    6: [1, 3, 4, 6, 7, 9]
  };
  // Cube rotation that brings each face to the front
  const FACE_ROTATION = {
    1: { x: 0, y: 0 },
    6: { x: 0, y: 180 },
    3: { x: 0, y: -90 },
    4: { x: 0, y: 90 },
    2: { x: -90, y: 0 },
    5: { x: 90, y: 0 }
  };

  function buildDie(isBrass) {
    const wrap = document.createElement('div');
    wrap.className = 'dice3d-shadow-wrap';
    const shadow = document.createElement('div');
    shadow.className = 'dice3d-shadow';
    const cube = document.createElement('div');
    cube.className = 'dice3d' + (isBrass ? ' is-brass' : '');
    for (let face = 1; face <= 6; face++) {
      const f = document.createElement('div');
      f.className = 'dice3d-face';
      f.dataset.face = String(face);
      for (let cell = 1; cell <= 9; cell++) {
        const slot = document.createElement('span');
        if (PIP_CELLS[face].includes(cell)) slot.className = 'pip3d';
        slot.style.gridArea = `${Math.ceil(cell / 3)} / ${((cell - 1) % 3) + 1}`;
        f.appendChild(slot);
      }
      cube.appendChild(f);
    }
    wrap.appendChild(shadow);
    wrap.appendChild(cube);
    return { wrap, cube, shadow };
  }

  // ---------------------------------------------------------------------------
  // 3D dice roll
  // ---------------------------------------------------------------------------
  function show3dDice(data) {
    const overlay = document.getElementById('dice-roll-overlay');
    const stage = document.getElementById('dice3d-stage');
    const legacy = document.getElementById('dice-legacy-wrapper');
    const titleBox = document.getElementById('dice-roller-name');
    const resultBox = document.getElementById('dice-result-value');
    if (!overlay || !stage) return false;

    const speed = window.GAME_SPEED || 1;
    const name = data.playerName || t().you;
    const values = [data.die1 || data.totalRoll, data.die2].filter(v => v >= 1 && v <= 6);
    if (values.length === 0) values.push(Math.min(6, Math.max(1, data.totalRoll || 1)));

    if (window.AudioPlayer) AudioPlayer.playDiceRoll();
    // The dice own the screen: clear any title card or turn banner still showing
    document.querySelectorAll('.fx-city-intro, .fx-turn-sweep').forEach(el => el.remove());
    overlay.classList.remove('hidden');
    if (legacy) legacy.hidden = true;
    stage.hidden = false;
    stage.innerHTML = '';
    if (titleBox) titleBox.textContent = t().rolling(name);
    if (resultBox) resultBox.textContent = '…';

    const dice = values.map((v, i) => {
      const d = buildDie(i === 1); // second die (double dice) is brass
      stage.appendChild(d.wrap);
      return { ...d, value: v };
    });

    const tumble = Math.max(0.35, 0.95 / speed);
    const hold = Math.max(0.25, 0.55 / speed);
    let closeTimer = null;

    const finish = () => {
      if (resultBox) resultBox.textContent = t().steps(name, data.totalRoll);
      if (window.AudioPlayer) AudioPlayer.playStep();
      if (hasGsap && !reduceMotion && resultBox) {
        gsap.fromTo(resultBox, { scale: 0.85, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2)' });
      }
      closeTimer = setTimeout(close, hold * 1000);
    };
    const close = () => {
      clearTimeout(closeTimer);
      overlay.classList.add('hidden');
      overlay.onclick = null;
      if (hasGsap) dice.forEach(d => gsap.killTweensOf([d.cube, d.wrap, d.shadow]));
    };
    overlay.onclick = close;

    if (!hasGsap || reduceMotion) {
      dice.forEach(d => {
        const r = FACE_ROTATION[d.value];
        d.cube.style.transform = `rotateX(${r.x}deg) rotateY(${r.y}deg)`;
      });
      setTimeout(finish, 150);
      return true;
    }

    dice.forEach((d, i) => {
      const r = FACE_ROTATION[d.value];
      // Several full turns plus the landing face; slight tilt so it reads as 3D
      const spinsX = 360 * (2 + i);
      const spinsY = 360 * (3 - i);
      gsap.set(d.cube, { rotationX: gsap.utils.random(-40, 40), rotationY: gsap.utils.random(-40, 40), transformPerspective: 900 });
      const tl = gsap.timeline({ onComplete: i === dice.length - 1 ? finish : null });
      tl.fromTo(d.wrap, { y: -160, x: i === 0 ? -60 : 60, scale: 0.7 },
        { y: 0, x: 0, scale: 1, duration: tumble, ease: 'bounce.out' }, 0)
        .to(d.cube, { rotationX: spinsX + r.x - 12, rotationY: spinsY + r.y + 10, duration: tumble, ease: 'power3.out' }, 0)
        .to(d.cube, { rotationX: spinsX + r.x, rotationY: spinsY + r.y, duration: 0.25, ease: 'back.out(3)' })
        .fromTo(d.shadow, { scale: 0.3, opacity: 0.2 }, { scale: 1, opacity: 1, duration: tumble, ease: 'bounce.out' }, 0);
    });
    return true;
  }

  // ---------------------------------------------------------------------------
  // Floating numbers over player cards
  // ---------------------------------------------------------------------------
  function floatText(anchor, text, kind, dx) {
    if (!anchor) return;
    const rect = anchor.getBoundingClientRect();
    if (!rect.width) return;
    const el = document.createElement('div');
    el.className = 'fx-float' + (kind ? ` is-${kind}` : '');
    el.textContent = text;
    el.style.left = `${rect.left + rect.width / 2 + (dx || 0)}px`;
    el.style.top = `${rect.top + rect.height / 2}px`;
    document.body.appendChild(el);
    if (!hasGsap || reduceMotion) {
      el.style.transform = 'translate(-50%, 24px)';
      setTimeout(() => el.remove(), 1200);
      return;
    }
    gsap.fromTo(el,
      { xPercent: -50, yPercent: -50, y: 0, scale: 0.6, opacity: 0 },
      { y: 46, scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)' });
    gsap.to(el, { y: 96, opacity: 0, duration: 0.6, delay: 0.9, ease: 'power2.in', onComplete: () => el.remove() });
  }

  // ---------------------------------------------------------------------------
  // Confetti in the game palette
  // ---------------------------------------------------------------------------
  let confettiFire = null;
  function getConfetti() {
    if (confettiFire || typeof window.confetti !== 'function') return confettiFire;
    const canvas = document.createElement('canvas');
    canvas.className = 'fx-confetti-canvas';
    document.body.appendChild(canvas);
    confettiFire = window.confetti.create(canvas, { resize: true, useWorker: true, disableForReducedMotion: true });
    return confettiFire;
  }
  const PALETTE = ['#E8B44A', '#F6DA9A', '#E5484D', '#2BB3A3', '#FFF4DE'];
  function burst(origin, power) {
    const fire = getConfetti();
    if (!fire || reduceMotion) return;
    const p = power || 1;
    fire({ particleCount: Math.round(60 * p), spread: 70, startVelocity: 38 * Math.sqrt(p), origin, colors: PALETTE, scalar: 1.05, ticks: 220 });
  }
  function grandFinale() {
    const fire = getConfetti();
    if (!fire || reduceMotion) return;
    const end = Date.now() + 1600;
    (function frame() {
      fire({ particleCount: 6, angle: 60, spread: 60, origin: { x: 0, y: 0.75 }, colors: PALETTE });
      fire({ particleCount: 6, angle: 120, spread: 60, origin: { x: 1, y: 0.75 }, colors: PALETTE });
      if (Date.now() < end) requestAnimationFrame(frame);
    })();
  }
  function originOf(el) {
    if (!el) return { x: 0.5, y: 0.4 };
    const r = el.getBoundingClientRect();
    return { x: (r.left + r.width / 2) / window.innerWidth, y: (r.top + r.height / 2) / window.innerHeight };
  }

  // ---------------------------------------------------------------------------
  // Board shake (penalties)
  // ---------------------------------------------------------------------------
  function shakeBoard(strength) {
    const target = document.querySelector('.board-arena-container');
    if (!target || !hasGsap || reduceMotion) return;
    const s = strength || 8;
    gsap.killTweensOf(target);
    gsap.fromTo(target, { x: 0 }, {
      keyframes: [{ x: -s }, { x: s * 0.8 }, { x: -s * 0.5 }, { x: s * 0.3 }, { x: 0 }],
      duration: 0.32,
      ease: 'power1.out',
      clearProps: 'transform'
    });
  }

  // ---------------------------------------------------------------------------
  // "Your turn" sweep — the one orchestrated moment per turn
  // ---------------------------------------------------------------------------
  function yourTurnSweep() {
    const old = document.querySelector('.fx-turn-sweep');
    if (old) old.remove();
    const wrap = document.createElement('div');
    wrap.className = 'fx-turn-sweep';
    wrap.setAttribute('role', 'status');
    const band = document.createElement('div');
    band.className = 'fx-turn-sweep-band';
    band.textContent = t().yourTurn;
    wrap.appendChild(band);
    document.body.appendChild(wrap);
    // Hard upper bound, even if animation frames are throttled
    setTimeout(() => wrap.remove(), 1600);
    if (!hasGsap || reduceMotion) return;
    gsap.timeline({ onComplete: () => wrap.remove() })
      .fromTo(band, { y: 12, opacity: 0, scale: 0.96 }, { y: 0, opacity: 1, scale: 1, duration: 0.3, ease: 'power3.out' })
      .to(band, { y: -8, opacity: 0, duration: 0.3, ease: 'power2.in' }, '+=0.6');
  }

  // ---------------------------------------------------------------------------
  // City intro card — shown once when a match starts on a map
  // ---------------------------------------------------------------------------
  let introShownFor = null;
  let introUntil = 0;
  function cityIntro(themeId) {
    if (!window.WorldMaps || introShownFor === themeId) return;
    introShownFor = themeId;
    const info = window.WorldMaps.info(themeId);
    const wrap = document.createElement('div');
    wrap.className = 'fx-city-intro';
    wrap.setAttribute('role', 'status');
    wrap.innerHTML = '<div class="fx-city-intro-card"><div class="fx-city-intro-kicker"></div><div class="fx-city-intro-title"></div><div class="fx-city-intro-rule"></div></div>';
    wrap.querySelector('.fx-city-intro-kicker').textContent = info.subtitle;
    wrap.querySelector('.fx-city-intro-title').textContent = info.title;
    document.body.appendChild(wrap);
    introUntil = Date.now() + 2300;
    const card = wrap.querySelector('.fx-city-intro-card');
    // Hard upper bound, even if animation frames are throttled
    setTimeout(() => wrap.remove(), 2600);
    if (!hasGsap || reduceMotion) return;
    gsap.timeline({ onComplete: () => wrap.remove() })
      .fromTo(wrap, { opacity: 0 }, { opacity: 1, duration: 0.25 })
      .fromTo(card.querySelector('.fx-city-intro-title'), { y: 30, opacity: 0, letterSpacing: '0.2em' }, { y: 0, opacity: 1, letterSpacing: '-0.03em', duration: 0.7, ease: 'power3.out' }, 0.05)
      .fromTo(card.querySelector('.fx-city-intro-kicker'), { y: -10, opacity: 0 }, { y: 0, opacity: 1, duration: 0.45, ease: 'power2.out' }, 0.25)
      .fromTo(card.querySelector('.fx-city-intro-rule'), { scaleX: 0 }, { scaleX: 1, duration: 0.6, ease: 'power3.inOut' }, 0.3)
      .to(wrap, { opacity: 0, duration: 0.4, ease: 'power2.in' }, '+=0.9');
  }

  // ---------------------------------------------------------------------------
  // HUD enrichment: rank medals, star meters, round beads
  // ---------------------------------------------------------------------------
  // Effects worth knowing at a glance; everything else (hats, bot badges) is dropped
  const EFFECTS = [
    ['jailedTurns', '🔒', 'Kodes'],
    ['frozenTurns', '🧊', 'Donmuş'],
    ['shieldActive', '🛡️', 'Kalkan'],
    ['ghostCloakActive', '👻', 'Pelerin'],
    ['reverseNext', '🔄', 'Ters yön']
  ];
  function enrichHud(state) {
    if (!state || !Array.isArray(state.players)) return;
    const ranked = [...state.players].sort((a, b) => (b.stars - a.stars) || (b.coins - a.coins));
    ranked.forEach((p, i) => {
      const card = document.getElementById(`player-card-${p.id}`);
      if (!card || card.querySelector('.hud-rank')) return;
      const rank = document.createElement('span');
      rank.className = `hud-rank rank-${i + 1}`;
      rank.textContent = String(i + 1);
      card.appendChild(rank);
      const active = EFFECTS.filter(([k]) => p[k] && p[k] !== 0);
      const nameEl = card.querySelector('.hud-pname');
      if (active.length && nameEl) {
        const fx = document.createElement('span');
        fx.className = 'hud-effects';
        fx.textContent = active.map(e => e[1]).join('');
        fx.title = active.map(e => e[2]).join(', ');
        nameEl.appendChild(fx);
      }
    });

    // City name + round beads in one compact card
    const roundCard = document.getElementById('hud-round-card');
    if (roundCard && window.WorldMaps) {
      let city = roundCard.querySelector('.hud-city');
      if (!city) {
        city = document.createElement('span');
        city.className = 'hud-city';
        roundCard.prepend(city);
      }
      city.textContent = window.WorldMaps.info(state.mapTheme || 'istanbul').title;
    }
    if (roundCard && state.totalRounds) {
      let beads = roundCard.querySelector('.hud-round-beads');
      if (!beads) {
        beads = document.createElement('div');
        beads.className = 'hud-round-beads';
        roundCard.appendChild(beads);
      }
      const total = Math.min(30, state.totalRounds);
      if (beads.childElementCount !== total) {
        beads.innerHTML = '';
        for (let i = 1; i <= total; i++) beads.appendChild(document.createElement('i'));
      }
      Array.from(beads.children).forEach((b, i) => {
        const r = i + 1;
        b.className = r < state.currentRound ? 'done' : (r === state.currentRound ? 'now' : '');
        if (r === total) b.classList.add('final');
      });
    }
  }

  // ---------------------------------------------------------------------------
  // Map previews on the lobby and create-room city buttons
  // ---------------------------------------------------------------------------
  function decorateMapButtons() {
    if (!window.WorldMaps) return;
    document.querySelectorAll('.lobby-map-btn[data-theme]').forEach(btn => {
      if (btn.querySelector('canvas')) return;
      const c = window.WorldMaps.preview(btn.dataset.theme, 132);
      c.className = 'map-preview-thumb';
      btn.prepend(c);
      btn.classList.add('has-preview');
    });
    document.querySelectorAll('.map-card-label input[name="map-theme-select"]').forEach(input => {
      const box = input.parentElement && input.parentElement.querySelector('.map-card-box');
      if (!box || box.querySelector('canvas')) return;
      const c = window.WorldMaps.preview(input.value, 150);
      c.className = 'map-preview-thumb';
      box.prepend(c);
      box.classList.add('has-preview');
    });
  }

  // ---------------------------------------------------------------------------
  // State diffing: who gained/lost coins or stars, whose turn it is now
  // ---------------------------------------------------------------------------
  let lastPlayers = null;
  let lastTurnKey = null;

  // One-line ticker for the newest game event (replaces the always-open event feed)
  const seenLog = new Set();
  let logPrimed = false;
  let tickerTimer = null;
  function showTicker(text) {
    let el = document.getElementById('fx-ticker');
    if (!el) {
      el = document.createElement('div');
      el.id = 'fx-ticker';
      el.className = 'fx-ticker';
      el.setAttribute('aria-live', 'polite');
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.classList.add('is-visible');
    clearTimeout(tickerTimer);
    tickerTimer = setTimeout(() => el.classList.remove('is-visible'), 3200);
  }
  function onLog(log) {
    if (!Array.isArray(log) || log.length === 0) return;
    const unseen = log.filter(e => e && e.id && !seenLog.has(e.id));
    log.forEach(e => e && e.id && seenLog.add(e.id));
    if (!logPrimed) { logPrimed = true; return; }
    if (unseen.length === 0) return;
    // Node keeps newest first, PHP appends: pick the newest of the unseen entries
    const newest = unseen[0] === log[0] ? unseen[0] : unseen[unseen.length - 1];
    if (newest && newest.text) showTicker(newest.text);
  }

  function isMe(p) {
    const me = window.GameClient && window.GameClient.player;
    return !!(me && ((p.sessionId && p.sessionId === me.sessionId) || (me.id && p.id === me.id)));
  }

  function onState(state) {
    if (!state || !Array.isArray(state.players)) return;
    const prev = lastPlayers;
    lastPlayers = {};
    state.players.forEach(p => { lastPlayers[p.id] = { coins: p.coins, stars: p.stars }; });

    if (prev && state.state === 'playing') {
      state.players.forEach(p => {
        const before = prev[p.id];
        if (!before) return;
        const card = document.getElementById(`player-card-${p.id}`);
        const dStars = (p.stars || 0) - (before.stars || 0);
        const dCoins = (p.coins || 0) - (before.coins || 0);
        if (dStars > 0) {
          floatText(card, `+${dStars} ⭐`, 'star', -38);
          burst(originOf(card), isMe(p) ? 1.4 : 0.7);
        }
        // Coin changes float only over your own card; rivals' coins just update quietly
        if (dCoins !== 0 && isMe(p)) {
          // When a star was bought the price shows next to it, slightly later
          setTimeout(() => floatText(card, `${dCoins > 0 ? '+' : ''}${dCoins} 🪙`, dCoins < 0 ? 'loss' : null, dStars > 0 ? 42 : 0), dStars > 0 ? 300 : 0);
        }
      });
    }

    if (state.state === 'playing' || state.state === 'minigame') {
      enrichHud(state);
      onLog(state.log);
    }

    if (state.state === 'playing') {
      cityIntro(state.mapTheme || 'istanbul');
      const current = state.players[state.turnIndex];
      const key = `${state.currentRound}:${state.turnIndex}`;
      if (key !== lastTurnKey) {
        lastTurnKey = key;
        if (current && isMe(current) && !current.isBot) {
          // Let the dice / event cards (and the city intro) finish before announcing the turn,
          // then re-check: the game may have ended or the turn moved on in the meantime
          setTimeout(() => {
            const live = window.CURRENT_ROOM_STATE;
            const isOpen = (id) => { const el = document.getElementById(id); return !!el && !el.classList.contains('hidden'); };
            // Never stack the banner on top of another overlay
            if (!live || live.state !== 'playing' || isOpen('modal-gameover') || isOpen('dice-roll-overlay') || isOpen('tile-event-overlay') || isOpen('minigame-overlay')) return;
            const now = live.players && live.players[live.turnIndex];
            if (now && isMe(now) && `${live.currentRound}:${live.turnIndex}` === key) yourTurnSweep();
          }, Math.max(450, introUntil - Date.now()));
        }
      }
    } else {
      lastTurnKey = null;
      if (state.state === 'lobby') {
        lastPlayers = null;
        introShownFor = null;
        decorateMapButtons();
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Hook into the existing UI once it exists
  // ---------------------------------------------------------------------------
  function install() {
    const UIRef = window.UI;
    if (!UIRef || UIRef.__fxInstalled) return;
    UIRef.__fxInstalled = true;

    const origDice = UIRef.showDiceRollAnimation.bind(UIRef);
    UIRef.showDiceRollAnimation = function (data) {
      if (!show3dDice(data || {})) origDice(data);
    };

    const origState = UIRef.updateGameState.bind(UIRef);
    UIRef.updateGameState = function (state) {
      origState(state);
      try { onState(state); } catch (e) { console.warn('fx state hook failed', e); }
    };

    const origTile = UIRef.showTileEventCard.bind(UIRef);
    UIRef.showTileEventCard = function (data) {
      origTile(data);
      if (data && data.category === 'penalty') shakeBoard(data.sound === 'jail' ? 10 : 7);
      const card = document.getElementById('tile-event-card');
      if (card && hasGsap && !reduceMotion) {
        gsap.fromTo(card, { y: 30, rotation: -3, scale: 0.92, opacity: 0 }, { y: 0, rotation: 0, scale: 1, opacity: 1, duration: 0.42, ease: 'back.out(1.8)' });
      }
    };

    // Replace the old DOM confetti pieces with palette-matched canvas confetti
    if (UIRef.triggerConfetti && typeof window.confetti === 'function') {
      UIRef.triggerConfetti = function () { burst({ x: 0.5, y: 0.35 }, 1.5); };
    }

    // Mini-game results: one short line instead of the full ranking string
    if (UIRef.showMiniGameResults) {
      UIRef.showMiniGameResults = function (data) {
        if (window.AudioPlayer) AudioPlayer.playStarFanfare();
        const winner = data && data.ranked && data.ranked[0];
        if (!winner) return;
        const me = window.GameClient && window.GameClient.player;
        const mine = me && winner.id === me.id;
        UIRef.showToast(mine ? '🏆 Mini oyunu sen kazandın!' : `🏆 ${winner.name} mini oyunu kazandı`, 'success');
        if (mine) burst({ x: 0.5, y: 0.45 }, 1.6);
      };
    }

    if (UIRef.showVictoryPodium) {
      const origPodium = UIRef.showVictoryPodium.bind(UIRef);
      UIRef.showVictoryPodium = function (data) {
        origPodium(data);
        grandFinale();
      };
    }

    // City thumbnails in the create-room modal (rendered once the page is ready)
    setTimeout(decorateMapButtons, 0);

    // Character card: compact by default, full customiser on demand
    const editBtn = document.getElementById('btn-studio-edit');
    const studio = document.querySelector('.char-studio-card');
    if (editBtn && studio) {
      editBtn.addEventListener('click', () => {
        const open = studio.classList.toggle('is-editing');
        editBtn.textContent = open ? 'Bitti' : 'Düzenle';
        editBtn.setAttribute('aria-expanded', String(open));
      });
    }

    // Keyboard access for the div-based action cards on the home screen
    document.addEventListener('keydown', (e) => {
      const el = e.target;
      if (!el || el.getAttribute('role') !== 'button') return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        el.click();
      }
    });
  }

  // ui.js creates window.UI synchronously; install right after it is defined
  if (window.UI) install();
  else window.addEventListener('DOMContentLoaded', install);

  window.FX = { burst, grandFinale, shakeBoard, floatText, yourTurnSweep, cityIntro, decorateMapButtons };
})();

  </script>

  <script>
    // Initialize UI on window load
    window.addEventListener('DOMContentLoaded', () => {
      UI.init();
    });
  </script>
</body>
</html>

