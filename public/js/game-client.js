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

    // 2. Try checking PHP Backend (api.php)
    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 1800);
      const res = await fetch('api.php?action=health', {
        signal: controller.signal,
        cache: 'no-store'
      });
      clearTimeout(timeoutId);

      if (res.ok) {
        const data = await res.json();
        if (data && data.status === 'ok') {
          console.log('✅ PHP Çok Oyunculu Arka Ucu aktif ve çalışıyor! Depolama:', data.storage);
          this.backendType = 'php';
          this.isStandalone = false;
          this.connected = true;
          if (pill) pill.className = 'server-status-pill online';
          if (pillText) pillText.innerText = '🟢 Çevrimiçi Çok Oyunculu Mod Aktif (api.php)';
          return;
        }
      }
    } catch (e) {
      // PHP not available on this host (e.g. GitHub Pages or static host)
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

  async apiPost(action, payload = {}) {
    try {
      const sessId = this.player ? this.player.sessionId : (PlayerProgression.profile ? PlayerProgression.profile.sessionId : 's_guest');
      const res = await fetch(`api.php?action=${action}`, {
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
        const res = await fetch(`api.php?action=get_state&roomId=${encodeURIComponent(this.roomId)}&sessionId=${encodeURIComponent(sessId)}`, {
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
        UI.showLobbyScreen(msg.roomId, this.player);
        if (msg.state) UI.updateGameState(msg.state);
        break;

      case 'state_update':
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
