// ============================================================================
// PartyCraft: Wonder Board - Internationalization (i18n) Engine
// Supports Turkish (tr) and English (en) with live dynamic switching & persistence
// ============================================================================

const I18N_DATA = {
  tr: {
    hero_badge: '🎲 Çok Oyunculu Dünya Turu Masa Oyunu',
    hero_title: 'Parti <span>Dünyası</span>',
    hero_subtitle: 'İstanbul, Tokyo, Paris ve New York sokaklarında zar at, yarış ve eğlen!',
    studio_badge: '✨ KARAKTERİNİ TASARLA',
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
    studio_badge: '✨ DESIGN YOUR CHARACTER',
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
  }
};

class I18nManager {
  constructor() {
    this.currentLang = 'tr';
    try {
      const saved = localStorage.getItem('partycraft_lang');
      if (saved && (saved === 'tr' || saved === 'en')) {
        this.currentLang = saved;
      }
    } catch (e) {}
  }

  getLang() {
    return this.currentLang;
  }

  t(key, fallback = '') {
    const dict = I18N_DATA[this.currentLang] || I18N_DATA.tr;
    if (dict[key] !== undefined) {
      return dict[key];
    }
    const defaultDict = I18N_DATA.tr;
    return defaultDict[key] !== undefined ? defaultDict[key] : (fallback || key);
  }

  setLang(lang) {
    if (lang !== 'tr' && lang !== 'en') return;
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
