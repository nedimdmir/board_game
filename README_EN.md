# 🎲 PartyCraft: Wonder Board

<p align="center">
  <img src="assets/hero-banner.svg" alt="PartyCraft: Wonder Board" width="100%">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Languages-TR%20%E2%80%A2%20EN%20%E2%80%A2%20DE%20%E2%80%A2%20ES%20%E2%80%A2%20FR-0284c7?style=flat-square" alt="Languages">
  <img src="https://img.shields.io/badge/Architecture-Single--File%20(index.html%20%2B%20api.php)-10b981?style=flat-square" alt="Single-File">
  <img src="https://img.shields.io/badge/Dependencies-Zero%20NPM%20%2F%20Zero%20DB-f59e0b?style=flat-square" alt="Zero Dependencies">
  <img src="https://img.shields.io/badge/Platform-Web%20%E2%80%A2%20Mobile%20%E2%80%A2%20Tablet-8b5cf6?style=flat-square" alt="Platforms">
  <img src="https://img.shields.io/badge/License-MIT-64748b?style=flat-square" alt="License">
</p>

---

**PartyCraft: Wonder Board** is a vibrant, zero-dependency turn-based multiplayer board game celebrating world cities (Istanbul, Tokyo, Paris, and New York) with interactive board tiles, tactical items, and 6 competitive mini-games.

<p align="center">
  <img src="assets/gameplay-preview.svg" alt="Gameplay Preview" width="100%">
</p>

## 🌟 Key Highlights

- ⚡ **Ultra Zero-Setup Single-File Deployment:**
  - **Option 1 (PHP Web Hosting):** Upload **ONLY `index.php`** to your web server. It delivers both the web client and manages multiplayer room synchronization via atomic JSON files. No database or npm setup required!
  - **Option 2 (Static Hosting / GitHub Pages):** Upload **`index.html`** for 100% serverless offline play with smart AI bots.
- 🌐 **Automatic Geolocation / Language Detection:**
  - Visitors from the **United States, United Kingdom, or international locations** immediately see the game in **English**!
  - Full support for 5 languages: 🇬🇧 English, 🇹🇷 Turkish, 🇩🇪 German, 🇪🇸 Spanish, and 🇫🇷 French.
  - One-click language switching from the top header or settings menu.
- 🎮 **6 Competitive Mini-Games:**
  1. 🪙 **Coin Catcher:** Catch falling gold with your basket (mobile double-tap zoom protected).
  2. 🎯 **Reflex Pop:** Hit rapidly popping targets before they vanish.
  3. 🎈 **Balloon Mash:** Rapid-click to inflate and pop the party balloon.
  4. 🎁 **Lucky Chests:** Pick the chest hiding the grand treasure.
  5. 🧠 **Memory Matrix:** Remember and repeat the glowing tile sequence.
  6. 🔨 **Timing Hammer (Power Striker):** Stop the oscillating power gauge at 100% bullseye to ring the bell and win +15 Gold!

<p align="center">
  <img src="assets/minigames-showcase.svg" alt="Mini Games Showcase" width="100%">
</p>

## 🎒 Fully Functional Tactical Items (Inventory)

| Item | Icon | Effect |
| :--- | :---: | :--- |
| **Double Dice** | 🎲 | Roll 2 dice at once on your next turn (2 - 12 steps) with glowing amber turn button. |
| **Super Rocket** | 🚀 | Launch 7 tiles forward instantly towards the next star. |
| **Magnetic Shield** | 🛡️ | Complete protection sphere against traps, steals, and hazards for 2 turns. |
| **Coin Magnet** | 🧲 | Siphon 4 coins from every unshielded rival on the board. |
| **Banana Peel** | 🍌 | Lay a slippery trap on current tile; rivals stepping on it slip 3 steps backward. |
| **Warp Wand** | 🪄 | Interactive modal opens to choose any rival and instantly swap board positions! |
| **Ghost Cloak** | 👻 | Radiant cyan aura: bypass all penalties, traps, police, and tax tiles for 2 turns! |
| **Lucky Gold Die** | 🎯 | Interactive 1-6 selector: pick your exact next roll guaranteed! |

## 🚀 How to Deploy to Your Website (Single File!)

```text
📁 Your Web Root (e.g. public_html/ or public_html/party/)
└── 📄 index.php   <-- UPLOAD ONLY THIS SINGLE FILE!
```

1. Upload **`index.php`** to your web hosting via FTP or cPanel File Manager.
2. Visit `https://your-domain.com/` in any browser.
3. You're live! Room codes, QR code invitations, and multiplayer synchronization work immediately.

## 🕹️ Controls

- **Desktop (PC / Mac):**
  - **Roll Dice:** Click button or press <kbd>SPACE</kbd>.
  - **Inventory:** Press <kbd>I</kbd>.
  - **Camera:** Click and drag to pan, scroll wheel to zoom.
- **Mobile & Tablet:**
  - Touch to drag, pinch to zoom.
  - Ergonomic bottom action dock.

## 📜 License

- **Developer:** Nedim Demir
- **License:** [MIT License](LICENSE)
