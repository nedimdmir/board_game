/**
 * UI Controller, Screen Transitions, Modals, Micro-interactions & Social Trade Engine
 */
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
          <div class="lobby-player-name">${p.name} ${hatDef.id !== 'none' ? hatDef.icon : ''} ${p.isBot ? '🤖' : ''}</div>
          <div class="lobby-player-title">${p.title} • Lv.${p.level}</div>
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
          <div class="hud-pname">${p.name}</div>
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
        dockStatus.innerHTML = `⏳ <strong>${activePlayer.name}</strong> hamlesini yapıyor...`;
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
    bubble.innerHTML = `<strong>${playerName}:</strong> ${text}`;

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
          AudioPlayer.playStarFanfare();
          GameClient.useItem(itemId);
          modal.classList.add('hidden');
        };

        list.appendChild(card);
      });
    }

    document.getElementById('btn-close-inventory').onclick = () => {
      AudioPlayer.playClick();
      modal.classList.add('hidden');
    };
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
        <div class="podium-pname">${p.name}</div>
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
      item.innerHTML = `<span class="log-time">${log.time}</span> ${log.text}`;
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
