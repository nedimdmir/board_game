const fs = require('fs');
const path = require('path');

console.log('Generating standalone single-file game: parti-dunyasi-tek-dosya.html...');

const htmlSrc = fs.readFileSync('public/index.html', 'utf8');
const boardCss = fs.readFileSync('public/css/board.css', 'utf8');
const styleCss = fs.readFileSync('public/css/style.css', 'utf8');

const iconsJs = fs.readFileSync('public/js/icons.js', 'utf8');
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

// Remove duplicate const BOARD_TILES = generateBoardForTheme('istanbul'); which is already defined in board-map.js
let cleanBoardGen = boardGenMatch[0].replace(/const BOARD_TILES = generateBoardForTheme\('istanbul'\);/, '// BOARD_TILES already declared in board-map.js');
cleanBoardGen = cleanBoardGen.replace(/const SHOP_ITEMS =[\s\S]*?\];/, '// SHOP_ITEMS already declared in board-map.js');

const localEngineJs = `
// ============================================================================
// STANDALONE IN-BROWSER GAME ENGINE (Local / Bot / Pass-and-Play Mode)
// Enables the game to run 100% offline from a single HTML file without Node.js!
// ============================================================================

${cleanBoardGen}

${roomClassMatch[0].replace(/class Room/, 'class LocalRoom')}

class LocalGameServer {
  constructor() {
    this.currentRoom = null;
  }

  createRoom(hostPlayer, settings = {}) {
    const roomId = 'SOLO';
    this.currentRoom = new LocalRoom(roomId, hostPlayer, settings);
    // Bind broadcast to GameClient
    this.currentRoom.broadcast = (type, payload = {}) => {
      setTimeout(() => {
        if (window.GameClient) {
          window.GameClient.handleServerMessage({ type, ...payload });
        }
      }, 20);
    };

    setTimeout(() => {
      window.GameClient.handleServerMessage({
        type: 'room_created',
        roomId: roomId,
        player: hostPlayer
      });
      this.currentRoom.broadcastState();
    }, 50);

    return this.currentRoom;
  }

  handleMessage(sessionId, type, payload) {
    if (!this.currentRoom) return;
    const room = this.currentRoom;

    switch (type) {
      case 'add_bot':
        room.addBot();
        break;
      case 'switch_map':
        room.switchMap(payload.mapTheme);
        break;
      case 'start_game':
        room.startGame();
        break;
      case 'roll_dice':
        room.rollDice(sessionId);
        break;
      case 'buy_item':
        room.buyItem(sessionId, payload.itemId);
        break;
      case 'use_item':
        room.useItem(sessionId, payload.itemId);
        break;
      case 'trade_offer':
        room.sendTradeOffer(sessionId, payload);
        break;
      case 'accept_trade':
        room.acceptTrade(sessionId);
        break;
      case 'decline_trade':
        room.declineTrade(sessionId);
        break;
      case 'send_emote':
        room.sendEmote(sessionId, payload.emoteId);
        break;
      case 'quick_chat':
        room.sendQuickChat(sessionId, payload.text);
        break;
      case 'minigame_score':
        room.handleMiniGameScore(sessionId, payload.score);
        break;
    }
  }
}

window.LocalServer = new LocalGameServer();
`;

// Now adapt game-client.js so it seamlessly falls back to LocalServer if offline/standalone
const adaptedGameClientJs = gameClientJs.replace(
  `async connect() {`,
  `async connect() {
    // If running from file:// or no websocket server, use standalone LocalServer
    if (window.location.protocol === 'file:' || !window.location.host) {
      this.isStandalone = true;
      return true;
    }
  `
).replace(
  `this.ws.send(JSON.stringify(data));`,
  `if (this.isStandalone || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
      if (window.LocalServer) {
        window.LocalServer.handleMessage(this.player ? this.player.sessionId : 'host_session', type, payload);
      }
      return;
    }
    this.ws.send(JSON.stringify(data));`
).replace(
  `joinRoom(roomId) {`,
  `joinRoom(roomId) {
    if (this.isStandalone) {
      this.createRoom({ mapTheme: 'istanbul', totalRounds: 10 });
      return;
    }
  `
).replace(
  `createRoom(settings = {}) {`,
  `createRoom(settings = {}) {
    if (this.isStandalone || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
      this.isStandalone = true;
      const prof = PlayerProgression.profile;
      const hostP = {
        id: 'p_host',
        sessionId: 'session_host',
        name: prof.name || 'Oyuncu',
        avatar: prof.avatar || 'builder',
        hat: prof.hat || 'none',
        color: prof.color || '#3B82F6',
        title: prof.title || 'Gezgin',
        level: prof.level || 1,
        isBot: false,
        isHost: true
      };
      this.player = hostP;
      window.LocalServer.createRoom(hostP, settings);
      return;
    }
  `
);

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

/* =================== 2. AUDIO SYNTHESIZER =================== */
${audioJs}

/* =================== 3. PROGRESSION =================== */
${progressionJs}

/* =================== 4. BOARD MAP RENDERER =================== */
${boardMapJs}

/* =================== 5. MINI GAMES =================== */
${minigamesJs}

/* =================== 6. LOCAL GAME SERVER (STANDALONE ENGINE) =================== */
${localEngineJs}

/* =================== 7. GAME CLIENT =================== */
${adaptedGameClientJs}

/* =================== 8. UI MANAGER =================== */
${uiJs}
  </script>`
);

// Write to parti-dunyasi-tek-dosya.html and oyun.html (convenient short name)
fs.writeFileSync('parti-dunyasi-tek-dosya.html', singleHtml, 'utf8');
fs.writeFileSync('oyun.html', singleHtml, 'utf8');

console.log('SUCCESS: Generated "parti-dunyasi-tek-dosya.html" and "oyun.html" successfully!');
console.log('File size:', (Buffer.byteLength(singleHtml) / 1024).toFixed(1), 'KB');
