<?php
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
