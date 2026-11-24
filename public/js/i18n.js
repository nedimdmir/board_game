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
  },

  de: {
    hero_badge: '🎲 Mehrspieler-Weltreise-Brettspiel',
    hero_title: 'Party<span>Craft</span>',
    hero_subtitle: 'Würfle, rase und feiere in den Straßen von Istanbul, Tokio, Paris und New York!',
    studio_badge: '✨ GESTALTE DEINE FIGUR',
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
    studio_badge: '✨ DISEÑA TU PERSONAJE',
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
    studio_badge: '✨ CRÉEZ VOTRE PERSONNAGE',
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
