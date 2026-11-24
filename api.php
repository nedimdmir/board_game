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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure data storage directory exists and is secured
$roomsDir = __DIR__ . DIRECTORY_SEPARATOR . 'rooms';
if (!is_dir($roomsDir)) {
    @mkdir($roomsDir, 0777, true);
    // Protect JSON files from direct web browsing
    @file_put_contents($roomsDir . DIRECTORY_SEPARATOR . '.htaccess', "Deny from all\n");
    @file_put_contents($roomsDir . DIRECTORY_SEPARATOR . 'index.html', "<!-- Protected -->");
}

$storageWritable = is_writable($roomsDir);

// Helper: Parse JSON input
function getRequestData() {
    $raw = file_get_contents('php://input');
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
    $files = @glob($roomsDir . DIRECTORY_SEPARATOR . '*.json');
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
function getRoomFilePath($roomId) {
    global $roomsDir;
    $cleanId = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper($roomId));
    if (empty($cleanId)) return null;
    return $roomsDir . DIRECTORY_SEPARATOR . $cleanId . '.json';
}

function loadRoom($roomId, &$fp = null, $lockType = LOCK_SH) {
    $path = getRoomFilePath($roomId);
    if (!$path || !file_exists($path)) return null;

    $fp = fopen($path, 'c+');
    if (!$fp) return null;

    if (!flock($fp, $lockType)) {
        fclose($fp);
        return null;
    }

    $size = filesize($path);
    $content = $size > 0 ? fread($fp, $size) : '';
    if (empty($content)) {
        return null;
    }

    $room = json_decode($content, true);
    return is_array($room) ? $room : null;
}

function saveRoomAndUnlock(&$room, &$fp) {
    if (!$fp) return false;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($room, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $fp = null;
    return true;
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

// Health Check
if ($action === 'health' || empty($action)) {
    sendJson([
        'status' => 'ok',
        'php' => true,
        'version' => PHP_VERSION,
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

    // Generate unique 6-character room code
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $roomId = '';
    for ($i = 0; $i < 6; $i++) {
        $roomId .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
    }

    $theme = !empty($data['mapTheme']) ? $data['mapTheme'] : 'istanbul';
    $rounds = !empty($data['rounds']) ? max(5, min(30, intval($data['rounds']))) : 10;
    $password = !empty($data['password']) ? trim($data['password']) : null;

    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : ('s_' . substr(md5(uniqid()), 0, 10));
    $playerName = !empty($data['playerName']) ? mb_substr(trim($data['playerName']), 0, 16) : 'Oyuncu 1';
    $avatar = !empty($data['avatar']) ? $data['avatar'] : 'builder';
    $hat = !empty($data['hat']) ? $data['hat'] : 'none';
    $color = !empty($data['color']) ? $data['color'] : '#3B82F6';
    $diceSkin = !empty($data['diceSkin']) ? $data['diceSkin'] : 'default';
    $level = !empty($data['level']) ? intval($data['level']) : 1;
    $title = !empty($data['title']) ? $data['title'] : 'Acemi Gezgin';

    $hostPlayer = [
        'id' => 'p_' . substr(md5(uniqid()), 0, 6),
        'sessionId' => $sessionId,
        'name' => $playerName,
        'avatar' => $avatar,
        'hat' => $hat,
        'diceSkin' => $diceSkin,
        'level' => $level,
        'title' => $title,
        'color' => $color,
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
        'activeStarTile' => [7, 15, 23, 31][mt_rand(0, 3)],
        'trapsOnBoard' => [],
        'board' => $board,
        'players' => [$hostPlayer],
        'log' => [],
        'lastAction' => null
    ];

    addRoomLog($room, "🏰 {$playerName} odayı kurdu! Kod: {$roomId}");

    $path = getRoomFilePath($roomId);
    $fp = fopen($path, 'w');
    if ($fp && flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($room, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    } else {
        sendError('Oda dosyası oluşturulamadı.');
    }

    sendJson([
        'type' => 'room_created',
        'roomId' => $roomId,
        'sessionId' => $sessionId,
        'player' => $hostPlayer,
        'state' => $room
    ]);
}

// 2. Join Room
if ($action === 'join_room') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);

    if (!$room) {
        sendError("\"{$roomId}\" kodlu oda bulunamadı veya süresi doldu!");
    }

    if (!empty($room['password'])) {
        $givenPass = isset($data['password']) ? trim($data['password']) : '';
        if ($givenPass !== $room['password']) {
            flock($fp, LOCK_UN);
            fclose($fp);
            sendError('Oda şifresi hatalı!');
        }
    }

    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : ('s_' . substr(md5(uniqid()), 0, 10));
    $existingIndex = -1;
    foreach ($room['players'] as $idx => $p) {
        if ($p['sessionId'] === $sessionId) {
            $existingIndex = $idx;
            break;
        }
    }

    if ($existingIndex !== -1) {
        // Reconnecting existing player!
        $room['players'][$existingIndex]['disconnected'] = false;
        $room['players'][$existingIndex]['lastSeen'] = time();
        if (!empty($data['playerName'])) $room['players'][$existingIndex]['name'] = mb_substr(trim($data['playerName']), 0, 16);
        if (!empty($data['avatar'])) $room['players'][$existingIndex]['avatar'] = $data['avatar'];
        if (!empty($data['hat'])) $room['players'][$existingIndex]['hat'] = $data['hat'];
        if (!empty($data['color'])) $room['players'][$existingIndex]['color'] = $data['color'];

        addRoomLog($room, "🔄 {$room['players'][$existingIndex]['name']} odaya tekrar bağlandı!");
        $player = $room['players'][$existingIndex];
    } else {
        // New Player Joining
        if ($room['state'] !== 'lobby') {
            flock($fp, LOCK_UN);
            fclose($fp);
            sendError('Bu oda şu anda oynanıyor, yeni oyuncu katılamaz!');
        }

        if (count($room['players']) >= 4) {
            flock($fp, LOCK_UN);
            fclose($fp);
            sendError('Oda dolu! (Maksimum 4 oyuncu)');
        }

        $colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4'];
        $playerColor = !empty($data['color']) ? $data['color'] : $colors[count($room['players']) % count($colors)];

        $player = [
            'id' => 'p_' . substr(md5(uniqid()), 0, 6),
            'sessionId' => $sessionId,
            'name' => !empty($data['playerName']) ? mb_substr(trim($data['playerName']), 0, 16) : ('Oyuncu ' . (count($room['players']) + 1)),
            'avatar' => !empty($data['avatar']) ? $data['avatar'] : 'builder',
            'hat' => !empty($data['hat']) ? $data['hat'] : 'none',
            'diceSkin' => !empty($data['diceSkin']) ? $data['diceSkin'] : 'default',
            'level' => !empty($data['level']) ? intval($data['level']) : 1,
            'title' => !empty($data['title']) ? $data['title'] : 'Acemi Gezgin',
            'color' => $playerColor,
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
        'roomId' => $roomId,
        'sessionId' => $sessionId,
        'player' => $player,
        'state' => $room
    ]);
}

// 3. Get State (Periodic Polling & Heartbeat)
if ($action === 'get_state') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) {
        sendError('Oda bulunamadı', 404);
    }

    $now = time();
    $stateChanged = false;

    // Heartbeat update for caller
    foreach ($room['players'] as &$p) {
        if ($p['sessionId'] === $sessionId) {
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

    // Auto-advance bot turn or disconnected player turn after 3 seconds of inactivity
    if ($room['state'] === 'playing' && !empty($room['players'])) {
        $activePlayer = &$room['players'][$room['turnIndex'] % count($room['players'])];
        $isBot = !empty($activePlayer['isBot']);
        $isAfk = !empty($activePlayer['disconnected']);

        // Check if turn should be played by server bot
        if ($isBot || $isAfk) {
            $turnStart = isset($room['turnStartedAt']) ? $room['turnStartedAt'] : 0;
            if ($now - $turnStart >= 2) {
                // Execute server-side turn for bot/disconnected player
                $room['turnStartedAt'] = $now;
                
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
                    $room['activeStarTile'] = [7, 15, 23, 31][mt_rand(0, 3)];
                } else if ($tile['type'] === 'trap') {
                    $pen = isset($tile['penalty']) ? $tile['penalty'] : 4;
                    $activePlayer['coins'] = max(0, $activePlayer['coins'] - $pen);
                }

                // Advance turn
                $room['turnIndex']++;
                if ($room['turnIndex'] >= count($room['players'])) {
                    $room['turnIndex'] = 0;
                    $room['currentRound']++;
                    if ($room['currentRound'] > $room['totalRounds']) {
                        $room['state'] = 'gameover';
                        addRoomLog($room, "🏆 PARTİ TAMAMLANDI!");
                    } else {
                        $minigamePool = [
                            ['id' => 'coin_catch', 'title' => 'Gökten Altın Yağmuru', 'desc' => 'Düşen altınları sepete topla, bombalardan kaç!', 'duration' => 15],
                            ['id' => 'reflex_pop', 'title' => 'Hızlı Parmak / Refleks', 'desc' => 'Yeşil sinyal geldiğinde ilk tıklayan kazanır!', 'duration' => 10],
                            ['id' => 'balloon_mash', 'title' => 'Lego Balon Şişirme', 'desc' => 'En hızlı tıkla, balonunu ilk patlatan sen ol!', 'duration' => 12],
                            ['id' => 'lucky_chests', 'title' => 'Hazine Sandığı Kumarı', 'desc' => '3 gizemli sandıktan birini seç! Ya büyük ödül ya da tuzak!', 'duration' => 11],
                            ['id' => 'memory_matrix', 'title' => 'Parti Hafızası & Hızlı Renkler', 'desc' => 'Yanan renk dizisini hafızanda tut ve hatasız tekrarla!', 'duration' => 14],
                            ['id' => 'timing_hammer', 'title' => 'Güç Çekici & Hassas Zamanlama', 'desc' => 'Tam ortadaki 100 puanlık altın zili tuttur!', 'duration' => 12]
                        ];
                        $chosen = $minigamePool[array_rand($minigamePool)];
                        $room['minigame'] = $chosen;
                        addRoomLog($room, "🎮 " . ($room['currentRound'] - 1) . ". Tur bitti! Mini-Oyun: {$chosen['title']}");
                    }
                }
                $stateChanged = true;
            }
        }
    }

    if ($stateChanged) {
        $room['updatedAt'] = $now;
    }

    saveRoomAndUnlock($room, $fp);

    sendJson([
        'type' => 'state_update',
        'roomId' => $roomId,
        'state' => $room['state'],
        'mapTheme' => $room['mapTheme'],
        'totalRounds' => $room['totalRounds'],
        'currentRound' => $room['currentRound'],
        'turnIndex' => $room['turnIndex'],
        'activeStarTile' => $room['activeStarTile'],
        'trapsOnBoard' => $room['trapsOnBoard'],
        'board' => $room['board'],
        'players' => $room['players'],
        'log' => $room['log'],
        'lastAction' => $room['lastAction'],
        'minigame' => isset($room['minigame']) ? $room['minigame'] : null
    ]);
}

// 4. Start Game
if ($action === 'start_game') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    // Auto-fill with bots up to 4 players so the game is always fun and full
    while (count($room['players']) < 4) {
        $template = $BOT_TEMPLATES[count($room['players']) % count($BOT_TEMPLATES)];
        $botHats = ['crown', 'wizard', 'pirate', 'cat_ears', 'party_hat', 'visor', 'detective', 'viking'];
        $room['players'][] = [
            'id' => 'bot_' . substr(md5(uniqid()), 0, 6),
            'sessionId' => 'bot_sess_' . substr(md5(uniqid()), 0, 8),
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

    $room['state'] = 'playing';
    $room['currentRound'] = 1;
    $room['turnIndex'] = 0;
    $room['turnStartedAt'] = time();
    $room['activeStarTile'] = [7, 15, 23, 31][mt_rand(0, 3)];

    addRoomLog($room, "🚀 Parti başladı! Toplam {$room['totalRounds']} tur oynanacak. Yıldız kaidesi {$room['activeStarTile']}. karoda!");

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);

    sendJson([
        'type' => 'state_update',
        'roomId' => $roomId,
        'state' => 'playing',
        'room' => $room
    ]);
}

// 5. Add Bot
if ($action === 'add_bot') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    if (count($room['players']) < 4) {
        $template = $BOT_TEMPLATES[count($room['players']) % count($BOT_TEMPLATES)];
        $botHats = ['crown', 'wizard', 'pirate', 'cat_ears', 'party_hat', 'visor', 'detective', 'viking'];
        $bot = [
            'id' => 'bot_' . substr(md5(uniqid()), 0, 6),
            'sessionId' => 'bot_sess_' . substr(md5(uniqid()), 0, 8),
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
        $room['players'][] = $bot;
        addRoomLog($room, "🤖 {$bot['name']} odaya eklendi!");
        $room['updatedAt'] = time();
    }

    saveRoomAndUnlock($room, $fp);
    sendJson(['type' => 'state_update', 'players' => $room['players']]);
}

// 6. Switch Map
if ($action === 'switch_map') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $mapTheme = !empty($data['mapTheme']) ? $data['mapTheme'] : 'istanbul';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    $room['mapTheme'] = $mapTheme;
    $room['board'] = generateBoardForTheme($mapTheme);
    addRoomLog($room, "🗺️ Harita seçildi: " . strtoupper($mapTheme));

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);
    sendJson(['type' => 'state_update', 'mapTheme' => $mapTheme]);
}

// 7. Roll Dice
if ($action === 'roll_dice') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    $player = &$room['players'][$room['turnIndex'] % count($room['players'])];
    if ($player['sessionId'] !== $sessionId) {
        flock($fp, LOCK_UN);
        fclose($fp);
        sendError('Sıra sende değil!');
    }

    // Check jail
    if ($player['jailedTurns'] > 0) {
        if ($player['coins'] >= 5) {
            $player['coins'] -= 5;
            $player['jailedTurns'] = 0;
            addRoomLog($room, "🔑 {$player['name']} 5 altın kefalet ödeyerek serbest kaldı!");
        } else {
            $player['jailedTurns']--;
            addRoomLog($room, "⛓️ {$player['name']} Kodes cezasında, turu pas geçti!");
            $room['turnIndex']++;
            saveRoomAndUnlock($room, $fp);
            sendJson(['type' => 'turn_skipped']);
        }
    }

    $die1 = mt_rand(1, 6);
    $die2 = null;
    $totalRoll = $die1;

    if (!empty($player['doubleDiceActive'])) {
        $die2 = mt_rand(1, 6);
        $totalRoll = $die1 + $die2;
        $player['doubleDiceActive'] = false;
        addRoomLog($room, "🎲 {$player['name']} ÇİFT ZAR attı: [{$die1}] + [{$die2}] = {$totalRoll} adım!");
    } else {
        addRoomLog($room, "🎲 {$player['name']} zar attı: [{$die1}]!");
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
    $tileEvent = null;

    if ($tile['type'] === 'coin') {
        $amt = isset($tile['amount']) ? $tile['amount'] : 4;
        $player['coins'] += $amt;
        addRoomLog($room, "🪙 {$player['name']}, {$tile['title']} karosundan +{$amt} Altın topladı!");
    } else if ($tile['type'] === 'star') {
        if ($player['coins'] >= 20) {
            $player['coins'] -= 20;
            $player['stars'] += 1;
            addRoomLog($room, "⭐ TEBRİKLER! {$player['name']} 20 Altına 1 YILDIZ satın aldı!");
            $room['activeStarTile'] = [7, 15, 23, 31][mt_rand(0, 3)];
        } else {
            addRoomLog($room, "⭐ {$player['name']} Yıldız kaidesine geldi ama 20 altını yoktu!");
        }
    } else if ($tile['type'] === 'trap') {
        $pen = isset($tile['penalty']) ? $tile['penalty'] : 4;
        $player['coins'] = max(0, $player['coins'] - $pen);
        addRoomLog($room, "🔥 TUZAK! {$player['name']} {$pen} altın kaybetti!");
    } else if ($tile['type'] === 'jail') {
        $player['jailedTurns'] = 1;
        addRoomLog($room, "🔒 {$player['name']} Kodese düştü!");
    } else if ($tile['type'] === 'freeze') {
        $player['frozenTurns'] = 1;
        addRoomLog($room, "🧊 {$player['name']} donduruldu!");
    } else if ($tile['type'] === 'reverse') {
        $player['reverseNext'] = true;
        addRoomLog($room, "🔄 {$player['name']} bir sonraki zarda geriye doğru yürüyecek!");
    }

    $room['lastAction'] = [
        'type' => 'dice_rolled',
        'playerId' => $player['id'],
        'playerName' => $player['name'],
        'die1' => $die1,
        'die2' => $die2,
        'totalRoll' => $totalRoll,
        'targetTile' => $newPos
    ];

    // Advance Turn
    $room['turnIndex']++;
    if ($room['turnIndex'] >= count($room['players'])) {
        $room['turnIndex'] = 0;
        $room['currentRound']++;
        if ($room['currentRound'] > $room['totalRounds']) {
            $room['state'] = 'gameover';
            addRoomLog($room, "🏆 PARTİ TAMAMLANDI! Tebrikler kazananlar!");
        } else {
            $minigamePool = [
                ['id' => 'coin_catch', 'title' => 'Gökten Altın Yağmuru', 'desc' => 'Düşen altınları sepete topla, bombalardan kaç!', 'duration' => 15],
                ['id' => 'reflex_pop', 'title' => 'Hızlı Parmak / Refleks', 'desc' => 'Yeşil sinyal geldiğinde ilk tıklayan kazanır!', 'duration' => 10],
                ['id' => 'balloon_mash', 'title' => 'Lego Balon Şişirme', 'desc' => 'En hızlı tıkla, balonunu ilk patlatan sen ol!', 'duration' => 12],
                ['id' => 'lucky_chests', 'title' => 'Hazine Sandığı Kumarı', 'desc' => '3 gizemli sandıktan birini seç! Ya büyük ödül ya da tuzak!', 'duration' => 11],
                ['id' => 'memory_matrix', 'title' => 'Parti Hafızası & Hızlı Renkler', 'desc' => 'Yanan renk dizisini hafızanda tut ve hatasız tekrarla!', 'duration' => 14],
                ['id' => 'timing_hammer', 'title' => 'Güç Çekici & Hassas Zamanlama', 'desc' => 'Tam ortadaki 100 puanlık altın zili tuttur!', 'duration' => 12]
            ];
            $chosen = $minigamePool[array_rand($minigamePool)];
            $room['minigame'] = $chosen;
            addRoomLog($room, "🎮 " . ($room['currentRound'] - 1) . ". Tur bitti! Mini-Oyun: {$chosen['title']}");
        }
    }
    $room['turnStartedAt'] = time();
    $room['updatedAt'] = time();

    saveRoomAndUnlock($room, $fp);

    sendJson([
        'type' => 'dice_rolled',
        'die1' => $die1,
        'die2' => $die2,
        'totalRoll' => $totalRoll,
        'newPosition' => $newPos,
        'state' => $room
    ]);
}

// 8. Buy Item
if ($action === 'buy_item') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';
    $itemId = !empty($data['itemId']) ? $data['itemId'] : '';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    $item = null;
    foreach ($SHOP_ITEMS as $it) {
        if ($it['id'] === $itemId) { $item = $it; break; }
    }

    if ($item) {
        foreach ($room['players'] as &$p) {
            if ($p['sessionId'] === $sessionId && $p['coins'] >= $item['cost']) {
                $p['coins'] -= $item['cost'];
                $p['inventory'][] = $item['id'];
                addRoomLog($room, "🛍️ {$p['name']} {$item['name']} satın aldı!");
                break;
            }
        }
    }

    $room['updatedAt'] = time();
    saveRoomAndUnlock($room, $fp);
    sendJson(['type' => 'state_update', 'room' => $room]);
}

// 9. Send Emote & Quick Chat
if ($action === 'send_emote' || $action === 'send_quick_chat') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    $player = null;
    foreach ($room['players'] as $p) {
        if ($p['sessionId'] === $sessionId) { $player = $p; break; }
    }

    if ($player) {
        if ($action === 'send_emote') {
            $emoteId = !empty($data['emoteId']) ? $data['emoteId'] : 'gg';
            $room['lastAction'] = ['type' => 'player_emote', 'playerId' => $player['id'], 'emoteId' => $emoteId];
        } else {
            $text = !empty($data['text']) ? mb_substr(strip_tags($data['text']), 0, 80) : '';
            $room['lastAction'] = ['type' => 'player_chat', 'playerId' => $player['id'], 'playerName' => $player['name'], 'text' => $text];
            addRoomLog($room, "💬 {$player['name']}: {$text}");
        }
        $room['updatedAt'] = time();
    }

    saveRoomAndUnlock($room, $fp);
    sendJson(['type' => 'success']);
}

// 10b. Leave Room (Any Player Anytime)
if ($action === 'leave_room') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if ($room) {
        $leftName = '';
        if ($room['state'] === 'lobby') {
            $newPlayers = [];
            foreach ($room['players'] as $p) {
                if ($p['sessionId'] === $sessionId || $p['id'] === $sessionId) {
                    $leftName = $p['name'];
                } else {
                    $newPlayers[] = $p;
                }
            }
            $room['players'] = $newPlayers;
            if (!empty($newPlayers) && $room['hostSessionId'] === $sessionId) {
                $room['hostSessionId'] = $newPlayers[0]['sessionId'];
            }
        } else {
            // In active game: convert to smart bot so match continues smoothly
            foreach ($room['players'] as &$p) {
                if ($p['sessionId'] === $sessionId || $p['id'] === $sessionId) {
                    $leftName = $p['name'];
                    $p['isBot'] = true;
                    $p['name'] .= ' (Bot)';
                }
            }
        }
        if ($leftName) {
            addRoomLog($room, '🚪 ' . $leftName . ' partiden ayrıldı.');
        }
        saveRoom($roomId, $room, $fp);
    }
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    sendJson(['type' => 'success', 'message' => 'left']);
}

// 10. Kick Player (Host Only)
if ($action === 'kick_player') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';
    $targetId = !empty($data['targetPlayerId']) ? $data['targetPlayerId'] : '';

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    if ($room['hostSessionId'] !== $sessionId) {
        flock($fp, LOCK_UN);
        fclose($fp);
        sendError('Yalnızca oda sahibi oyuncu çıkarabilir!');
    }

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
    sendJson(['type' => 'state_update', 'players' => $room['players']]);
}

// 11. Use Item
if ($action === 'use_item') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';
    $itemId = !empty($data['itemId']) ? $data['itemId'] : '';
    $targetId = !empty($data['targetPlayerId']) ? $data['targetPlayerId'] : null;

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if (!$room) sendError('Oda bulunamadı');

    $player = null;
    foreach ($room['players'] as &$p) {
        if ($p['sessionId'] === $sessionId) { $player = &$p; break; }
    }

    if ($player && in_array($itemId, $player['inventory'])) {
        $idx = array_search($itemId, $player['inventory']);
        array_splice($player['inventory'], $idx, 1);

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
            foreach ($room['players'] as &$opp) {
                if ($opp['id'] !== $player['id'] && empty($opp['shieldActive'])) {
                    $amt = min(4, $opp['coins']);
                    $opp['coins'] -= $amt;
                    $stolenTotal += $amt;
                }
            }
            $player['coins'] += $stolenTotal;
            addRoomLog($room, "🧲 {$player['name']} Altın Mıknatısıyla rakiplerden {$stolenTotal} altın çekti!");
        } else if ($itemId === 'banana') {
            $room['trapsOnBoard'][] = ['tileIndex' => $player['position'], 'ownerId' => $player['id']];
            addRoomLog($room, "🍌 {$player['name']} {$player['position']}. karoya Muz Kabuğu tuzağı kurdu!");
        } else if ($itemId === 'swap_wand' && $targetId) {
            foreach ($room['players'] as &$opp) {
                if ($opp['id'] === $targetId) {
                    $tmp = $player['position'];
                    $player['position'] = $opp['position'];
                    $opp['position'] = $tmp;
                    addRoomLog($room, "🪄 {$player['name']} Takas Asası ile {$opp['name']} ile yer değiştirdi!");
                    break;
                }
            }
        }

        $room['updatedAt'] = time();
    }

    saveRoomAndUnlock($room, $fp);
    sendJson(['type' => 'state_update', 'room' => $room]);
}

// 12. Close Shop
if ($action === 'close_shop') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if ($room) {
        $room['updatedAt'] = time();
        saveRoomAndUnlock($room, $fp);
    }
    sendJson(['type' => 'success']);
}

// 13. Minigame Score
if ($action === 'minigame_score') {
    $roomId = !empty($data['roomId']) ? trim($data['roomId']) : '';
    $sessionId = !empty($data['sessionId']) ? $data['sessionId'] : '';
    $score = isset($data['score']) ? intval($data['score']) : 0;

    $fp = null;
    $room = loadRoom($roomId, $fp, LOCK_EX);
    if ($room) {
        foreach ($room['players'] as &$p) {
            if ($p['sessionId'] === $sessionId) {
                $reward = min(15, max(3, intval($score / 2)));
                $p['coins'] += $reward;
                addRoomLog($room, "🎮 {$p['name']} mini oyundan {$score} puan aldı ve +{$reward} Altın kazandı!");
                break;
            }
        }
        $room['updatedAt'] = time();
        saveRoomAndUnlock($room, $fp);
    }
    sendJson(['type' => 'success']);
}

// Unknown Action Fallback
sendError('Geçersiz işlem isteği.');
