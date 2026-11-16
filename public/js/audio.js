/**
 * Web Audio API Sound Synthesizer Engine
 * 100% Procedural & Royalty-Free - Zero external dependencies!
 */
class SoundEngine {
  constructor() {
    this.ctx = null;
    this.muted = false;
    this.musicPlaying = false;
    this.musicTimer = null;
    this.musicStep = 0;
  }

  init() {
    if (!this.ctx) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (AudioCtx) {
        this.ctx = new AudioCtx();
      }
    }
    if (this.ctx && this.ctx.state === 'suspended') {
      this.ctx.resume();
    }
  }

  toggleMute() {
    this.muted = !this.muted;
    if (this.muted && this.musicPlaying) {
      this.stopMusic();
    }
    return this.muted;
  }

  playTone(freq, type = 'sine', duration = 0.1, gainVal = 0.15, delay = 0) {
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime + delay;

    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();

    osc.type = type;
    osc.frequency.setValueAtTime(freq, now);

    gain.gain.setValueAtTime(gainVal, now);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

    osc.connect(gain);
    gain.connect(this.ctx.destination);

    osc.start(now);
    osc.stop(now + duration);
  }

  playClick() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(450, now);
    osc.frequency.exponentialRampToValueAtTime(120, now + 0.04);
    gain.gain.setValueAtTime(0.2, now);
    gain.gain.linearRampToValueAtTime(0.001, now + 0.04);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.04);
  }

  playStep() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(320, now);
    osc.frequency.exponentialRampToValueAtTime(540, now + 0.08);
    gain.gain.setValueAtTime(0.18, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.09);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.09);
  }

  playDiceRoll() {
    this.init();
    if (this.muted || !this.ctx) return;
    const clicks = 8;
    for (let i = 0; i < clicks; i++) {
      const delay = i * 0.07;
      const freq = 180 + Math.random() * 220;
      this.playTone(freq, 'triangle', 0.03, 0.15, delay);
    }
  }

  playCoinSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Bouncy two-tone coin arpeggio
    this.playTone(987.77, 'sine', 0.12, 0.22, 0);       // B5
    this.playTone(1318.51, 'triangle', 0.25, 0.28, 0.09); // E6
  }

  playStarFanfare() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Triumphant Mario Party-style trumpet chords
    const notes = [
      { f: 523.25, d: 0.12, t: 0 },    // C5
      { f: 659.25, d: 0.12, t: 0.12 }, // E5
      { f: 783.99, d: 0.12, t: 0.24 }, // G5
      { f: 1046.50, d: 0.45, t: 0.36 } // C6
    ];
    notes.forEach(n => {
      this.playTone(n.f, 'triangle', n.d, 0.25, n.t);
      this.playTone(n.f * 1.002, 'sawtooth', n.d * 0.8, 0.08, n.t);
    });
  }

  playTrapSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(380, now);
    osc.frequency.linearRampToValueAtTime(110, now + 0.35);
    gain.gain.setValueAtTime(0.2, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.35);
  }

  playWarpSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(200, now);
    osc.frequency.exponentialRampToValueAtTime(1200, now + 0.28);
    gain.gain.setValueAtTime(0.22, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.3);
  }

  playCountdown(isFinal = false) {
    this.init();
    if (isFinal) {
      this.playTone(880, 'triangle', 0.35, 0.3);
    } else {
      this.playTone(440, 'sine', 0.15, 0.2);
    }
  }

  playEmoteReaction() {
    this.init();
    if (this.muted || !this.ctx) return;
    this.playTone(600, 'sine', 0.08, 0.15, 0);
    this.playTone(900, 'sine', 0.12, 0.2, 0.07);
  }

  playSlipSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    // Comedic slide whistle up then down + boing
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(300, now);
    osc.frequency.linearRampToValueAtTime(750, now + 0.15);
    osc.frequency.linearRampToValueAtTime(140, now + 0.38);
    gain.gain.setValueAtTime(0.25, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.4);
  }

  playJailSound() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Clang of iron prison bars
    this.playTone(220, 'sawtooth', 0.3, 0.3, 0);
    this.playTone(180, 'sawtooth', 0.4, 0.25, 0.05);
    this.playTone(110, 'triangle', 0.5, 0.35, 0.08);
  }

  playWheelTick() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(1200 + Math.random() * 300, now);
    osc.frequency.exponentialRampToValueAtTime(180, now + 0.025);
    gain.gain.setValueAtTime(0.18, now);
    gain.gain.linearRampToValueAtTime(0.001, now + 0.025);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.025);
  }

  playLevelUp() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Euphoric triumphant 5-note fanfare
    const notes = [
      { f: 523.25, d: 0.1, t: 0 },
      { f: 659.25, d: 0.1, t: 0.1 },
      { f: 783.99, d: 0.1, t: 0.2 },
      { f: 987.77, d: 0.12, t: 0.3 },
      { f: 1046.50, d: 0.55, t: 0.42 }
    ];
    notes.forEach(n => {
      this.playTone(n.f, 'triangle', n.d, 0.3, n.t);
      this.playTone(n.f * 1.003, 'sine', n.d, 0.15, n.t);
    });
  }

  playScreenShake() {
    this.init();
    if (this.muted || !this.ctx) return;
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(80, now);
    osc.frequency.linearRampToValueAtTime(35, now + 0.45);
    gain.gain.setValueAtTime(0.35, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.45);
  }

  playChestOpen() {
    this.init();
    if (this.muted || !this.ctx) return;
    // Mystical sparkle arpeggio
    const freqs = [587.33, 739.99, 880.00, 1174.66, 1479.98];
    freqs.forEach((f, idx) => {
      this.playTone(f, 'sine', 0.18, 0.2, idx * 0.06);
    });
  }

  // Procedural Background Music (Charming upbeat lounge arpeggios)
  startMusic() {
    if (this.musicPlaying || this.muted) return;
    this.init();
    if (!this.ctx) return;

    this.musicPlaying = true;
    const bpm = 120;
    const stepTime = (60 / bpm) / 2; // eighth notes

    // Cheerful pentatonic pattern
    const melody = [
      523.25, 0, 659.25, 783.99, 1046.50, 783.99, 659.25, 0,
      587.33, 0, 698.46, 880.00, 1174.66, 880.00, 698.46, 0,
      440.00, 0, 523.25, 659.25, 880.00, 659.25, 523.25, 0,
      392.00, 440.00, 523.25, 659.25, 783.99, 0, 0, 0
    ];

    const bass = [
      130.81, 0, 130.81, 0, 164.81, 0, 196.00, 0,
      146.83, 0, 146.83, 0, 174.61, 0, 220.00, 0,
      110.00, 0, 110.00, 0, 130.81, 0, 164.81, 0,
      98.00, 0, 98.00, 0, 130.81, 0, 196.00, 0
    ];

    const playLoop = () => {
      if (!this.musicPlaying || this.muted) return;
      const mFreq = melody[this.musicStep % melody.length];
      const bFreq = bass[this.musicStep % bass.length];

      if (mFreq > 0) {
        this.playTone(mFreq, 'sine', 0.14, 0.035);
      }
      if (bFreq > 0) {
        this.playTone(bFreq, 'triangle', 0.22, 0.045);
      }

      this.musicStep++;
      this.musicTimer = setTimeout(playLoop, stepTime * 1000);
    };

    playLoop();
  }

  stopMusic() {
    this.musicPlaying = false;
    if (this.musicTimer) {
      clearTimeout(this.musicTimer);
      this.musicTimer = null;
    }
  }

  toggleMusic() {
    if (this.musicPlaying) {
      this.stopMusic();
      return false;
    } else {
      this.startMusic();
      return true;
    }
  }
}

const AudioPlayer = new SoundEngine();
