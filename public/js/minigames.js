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
