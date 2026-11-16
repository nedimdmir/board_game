/**
 * WebSocket Game Client & Networking Layer
 * Connects to the server, keeps game state in sync, and handles reconnection & trading.
 */
class ClientEngine {
  constructor() {
    this.ws = null;
    this.connected = false;
    this.roomId = null;
    this.player = null; // My player object
    this.state = null; // Current room state
    this.reconnectAttempts = 0;
  }

  connect() {
    return new Promise((resolve, reject) => {
      const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
      const wsUrl = `${protocol}//${window.location.host}`;

      this.ws = new WebSocket(wsUrl);

      this.ws.onopen = () => {
        console.log('✅ WebSocket sunucusuna bağlanıldı!');
        this.connected = true;
        this.reconnectAttempts = 0;
        resolve();
      };

      this.ws.onmessage = (event) => {
        try {
          const msg = JSON.parse(event.data);
          this.handleServerMessage(msg);
        } catch (e) {
          console.error('Mesaj ayrıştırma hatası:', e);
        }
      };

      this.ws.onclose = () => {
        console.warn('⚠️ WebSocket bağlantısı koptu.');
        this.connected = false;
        // Auto-reconnect after short delay
        if (this.roomId && this.reconnectAttempts < 5) {
          this.reconnectAttempts++;
          setTimeout(() => this.reconnect(), 2000);
        }
      };

      this.ws.onerror = (err) => {
        console.error('WebSocket hatası:', err);
        reject(err);
      };
    });
  }

  reconnect() {
    console.log(`Yeniden bağlanılıyor (Deneme ${this.reconnectAttempts})...`);
    this.connect().then(() => {
      if (this.roomId) {
        this.joinRoom(this.roomId);
      }
    });
  }

  send(type, payload = {}) {
    if (!this.connected || !this.ws) {
      console.warn('Sunucuya bağlı değil.');
      return;
    }
    this.ws.send(JSON.stringify({ type, ...payload }));
  }

  createRoom(rounds = 10, mapTheme = 'istanbul') {
    const prof = PlayerProgression.profile;
    this.send('create_room', {
      sessionId: prof.sessionId,
      playerName: prof.name,
      avatar: prof.avatar,
      hat: prof.hat || 'none',
      color: prof.color || '#3B82F6',
      diceSkin: prof.diceSkin,
      level: prof.level,
      title: prof.title,
      rounds: rounds,
      mapTheme: mapTheme
    });
  }

  joinRoom(roomId) {
    const prof = PlayerProgression.profile;
    this.send('join_room', {
      roomId: roomId.trim().toUpperCase(),
      sessionId: prof.sessionId,
      playerName: prof.name,
      avatar: prof.avatar,
      hat: prof.hat || 'none',
      color: prof.color || '#3B82F6',
      diceSkin: prof.diceSkin,
      level: prof.level,
      title: prof.title
    });
  }

  updateProfile(profileData) {
    this.send('update_profile', profileData);
  }

  switchMap(mapTheme) {
    this.send('switch_map', { mapTheme });
  }

  addBot() {
    this.send('add_bot');
  }

  startGame() {
    this.send('start_game');
  }

  rollDice() {
    this.send('roll_dice');
  }

  useItem(itemId, targetPlayerId = null) {
    this.send('use_item', { itemId, targetPlayerId });
  }

  buyItem(itemId) {
    this.send('buy_item', { itemId });
  }

  closeShop() {
    this.send('close_shop');
  }

  sendEmote(emoteId) {
    this.send('send_emote', { emoteId });
  }

  sendQuickChat(text) {
    this.send('send_quick_chat', { text });
  }

  sendTradeOffer(offer) {
    this.send('send_trade_offer', { offer });
  }

  respondTradeOffer(tradeId, accepted) {
    this.send('respond_trade_offer', { tradeId, accepted });
  }

  sendMiniGameScore(score) {
    this.send('minigame_score', { score });
  }

  handleServerMessage(msg) {
    switch (msg.type) {
      case 'room_created':
      case 'room_joined':
        this.roomId = msg.roomId;
        this.player = msg.player;
        UI.showLobbyScreen(msg.roomId, this.player);
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
