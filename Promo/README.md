# Promo assets

Everything here is excluded from release builds (`Promo` is in `EXCLUDE_LIST` in `gulpfile.js` and in `bin/simulate-free-build.php`).

| Folder | What |
|---|---|
| `wp-org/` | Ready for the WordPress.org SVN `assets/` folder: `banner-1544x500.png`, `banner-772x250.png`, `icon-256x256.png`, `icon-128x128.png`, `screenshot-1..4.png` (2× resolution; order matches `== Screenshots ==` in `readme.txt`) |
| `images/` | The previous assets copied from `Documents/Factorial2000/catalog-sync/svn/assets` (for comparison) |
| `shots-raw/` | Raw 2× admin captures (git-ignored) |
| `tools/` | `capture-screenshots.mjs` (Playwright, logs in to a local dev site) and `make-assets.mjs` (icon + banner) |
| `video/` | The promo video, built on [promo-video-generator](https://github.com/maximus2526/promo-video-generator) |

## Regenerate images

```bash
cd Promo/video && npm install          # once (provides Playwright)
cd .. && node tools/capture-screenshots.mjs http://localhost:10015 <user> <pass>
node tools/make-assets.mjs
```

python tools/crop-screenshots.py

## Render the video

Needs `ffmpeg` on PATH.

```bash
cd Promo/video
node plugin-cli.mjs stills --t 3,9,18      # review frames
node plugin-cli.mjs all                     # audio → frames → out/factorial2000-catalog-sync.mp4
```

The storyboard (44 s, 120 BPM): intro → import (Free) → stock & price sync (Free + Pro) → Free vs Pro board → Prom.ua marketplaces (Pro) → XML editor / images / fields updater (Pro) → end card.
Timing lives in `video/src/plugin/timeline.js`, visuals in `scene.js`, sound in `synth.js`.
