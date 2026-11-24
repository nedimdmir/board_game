const fs = require('fs');
const path = require('path');

console.log('Generating standalone single-file packages: index.html and index.php...');

const htmlSrc = fs.readFileSync('public/index.html', 'utf8');
const boardCss = fs.readFileSync('public/css/board.css', 'utf8');
const styleCss = fs.readFileSync('public/css/style.css', 'utf8');

const iconsJs = fs.readFileSync('public/js/icons.js', 'utf8');
const i18nJs = fs.readFileSync('public/js/i18n.js', 'utf8');
const qrSvgJs = fs.readFileSync('public/js/qr-svg.js', 'utf8');
const audioJs = fs.readFileSync('public/js/audio.js', 'utf8');
const progressionJs = fs.readFileSync('public/js/progression.js', 'utf8');
const boardMapJs = fs.readFileSync('public/js/board-map.js', 'utf8');
const minigamesJs = fs.readFileSync('public/js/minigames.js', 'utf8');
const gameClientJs = fs.readFileSync('public/js/game-client.js', 'utf8');
const uiJs = fs.readFileSync('public/js/ui.js', 'utf8');

// Read Room logic from server.js to create the standalone LocalGameEngine
const serverJs = fs.readFileSync('server.js', 'utf8');

// Extract generateBoardForTheme, BOT_NAMES, and Room class
let boardGenMatch = serverJs.match(/function generateBoardForTheme[\s\S]*?const BOT_NAMES =[\s\S]*?\];/);
const roomClassMatch = serverJs.match(/class Room \{[\s\S]*?\n\}/);

if (!boardGenMatch || !roomClassMatch) {
  console.error('Failed to extract Room logic from server.js');
  process.exit(1);
}

// Remove duplicate const BOARD_TILES and SHOP_ITEMS
let cleanBoardGen = boardGenMatch[0].replace(/const BOARD_TILES = generateBoardForTheme\('istanbul'\);/, '// BOARD_TILES already declared in board-map.js');
cleanBoardGen = cleanBoardGen.replace(/const SHOP_ITEMS =[\s\S]*?\];/, '// SHOP_ITEMS already declared in board-map.js');

const localEngineJs = `
// ============================================================================
// STANDALONE IN-BROWSER GAME ENGINE (Local / Bot / Pass-and-Play Mode)
// Enables the game to run 100% offline from a single HTML file without Node.js!
// ============================================================================

${cleanBoardGen}

${roomClassMatch[0].replace(/class Room/, 'class LocalRoom')}

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

  handleMessage(sessionId, type, data = {}) {
    if (!this.currentRoom) return;
    const room = this.currentRoom;

    switch (type) {
      case 'add_bot':
        room.addBot();
        room.broadcastState();
        break;

      case 'switch_map':
        room.mapTheme = data.mapTheme || 'istanbul';
        room.board = generateBoardForTheme(room.mapTheme);
        room.addLog('🗺️ Harita seçildi: ' + room.mapTheme.toUpperCase());
        room.broadcastState();
        break;

      case 'start_game':
        room.startGame();
        break;

      case 'roll_dice':
        room.rollDice(sessionId);
        break;

      case 'set_game_speed':
        room.gameSpeed = data.speed || 1;
        break;

      case 'buy_item': {
        const player = room.players.find(p => p.sessionId === sessionId);
        const item = SHOP_ITEMS.find(i => i.id === data.itemId);
        if (player && item && player.coins >= item.cost) {
          player.coins -= item.cost;
          player.inventory.push(item.id);
          room.addLog('🛍️ ' + player.name + ' ' + item.name + ' satın aldı!');
          room.broadcastState();
        }
        break;
      }

      case 'use_item':
        room.useItem(sessionId, data.itemId, data.targetPlayerId);
        break;

      case 'close_shop':
        if (room.getCurrentPlayer() && room.getCurrentPlayer().sessionId === sessionId) {
          room.finishTurn();
        }
        break;

      case 'send_trade_offer':
        room.handleTradeOffer(sessionId, data.offer || data);
        break;

      case 'respond_trade_offer':
        room.resolveTrade(data.tradeId, data.accepted);
        break;

      case 'send_emote':
        room.sendEmote(sessionId, data.emoteId);
        break;

      case 'leave_room': {
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
`;

// Build single HTML
// Replace CSS links with <style>
let singleHtml = htmlSrc;
singleHtml = singleHtml.replace(
  /<link rel="stylesheet" href="css\/style\.css">[\s\S]*?<link rel="stylesheet" href="css\/board\.css">/,
  `<style>
/* =================== BOARD CSS =================== */
${boardCss}

/* =================== STYLE CSS =================== */
${styleCss}
  </style>`
);

// Replace script tags with inlined scripts
singleHtml = singleHtml.replace(
  /<script src="js\/icons\.js"><\/script>[\s\S]*?<script src="js\/ui\.js"><\/script>/,
  `<script>
/* =================== 1. ICONS =================== */
${iconsJs}

/* =================== 1.5. I18N (MULTI-LANGUAGE) =================== */
${i18nJs}

/* =================== 2. QR CODE SVG GENERATOR =================== */
${qrSvgJs}

/* =================== 3. AUDIO SYNTHESIZER =================== */
${audioJs}

/* =================== 4. PROGRESSION =================== */
${progressionJs}

/* =================== 5. BOARD MAP RENDERER =================== */
${boardMapJs}

/* =================== 6. MINI GAMES =================== */
${minigamesJs}

/* =================== 7. LOCAL GAME SERVER (STANDALONE ENGINE) =================== */
${localEngineJs}

/* =================== 8. GAME CLIENT =================== */
${gameClientJs}

/* =================== 9. UI MANAGER =================== */
${uiJs}
  </script>`
);

// Write root index.html (Static / GitHub Pages / Offline Single-File)
fs.writeFileSync('index.html', singleHtml, 'utf8');

// Build All-In-One index.php (Multiplayer PHP Backend + Full Web Frontend in ONE single file)
const apiPhpRaw = fs.readFileSync('api.php', 'utf8');
const cleanApiPhp = apiPhpRaw.replace(/^\s*<\?php\s*/, '');

const allInOnePhp = `<?php
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
${cleanApiPhp}
    exit;
}
?>
${singleHtml}
`;

fs.writeFileSync('index.php', allInOnePhp, 'utf8');

console.log('SUCCESS: Generated root "index.html" and All-in-One "index.php" successfully!');
console.log('index.html size:', (Buffer.byteLength(singleHtml) / 1024).toFixed(1), 'KB');
console.log('index.php (All-In-One) size:', (Buffer.byteLength(allInOnePhp) / 1024).toFixed(1), 'KB');
