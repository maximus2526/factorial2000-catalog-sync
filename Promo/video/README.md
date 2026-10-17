# Promo video generator: from a prompt and store URLs to a motion-graphics video

This tool makes a 1920×1080, 60 fps promo video with motion blur and a generated soundtrack, starting from a prompt and the URLs of real WooCommerce / Woodmart stores. Everything runs headless, and you never open a video editor.

The video has three parts:

1. **Claude Code terminal.** Your prompt is typed in, the woodmart MCP is called, and the to-do list gets ticked off.
2. **The store assembled in 3D** from real crops of the site, set to a beat drop and feature badges.
3. **The finished store in a 3D browser.** It plays a real browsing session captured from the site: scroll, product hover, Quick View, *Add to cart*, side cart, the **Shop** page and the AJAX filter. Any extra stores open in new tabs. It finishes with a PageSpeed card and the end titles.

## Quick start

```bash
npm install && npx playwright install chromium-headless-shell   # needs ffmpeg on PATH

node cli.js new "Build me a complete store with the design of https://my-store.com/ and also https://second-store.com/"
node cli.js all my-store
```

`new` creates `projects/my-store.json`:

- the prompt text, with the URLs removed, is what appears in the terminal
- the first URL is the main store, used for the full purchase flow
- any other URLs become extra tabs

`all` captures the sites, synthesizes the audio, renders the frames and saves `out/my-store.mp4`.

While it renders, a **progress window** opens at `http://localhost:5179`. It shows the current stage, a live preview of the frame being rendered, the ETA and a **⏹ Stop** button. When the render finishes, the window plays the video. Use `--no-window` to turn it off.

## Project config

A project can be as small as this:

```json
{ "prompt": "Build me a complete store with the design of this site", "sites": [{ "url": "https://my-store.com/" }] }
```

Anything you leave out is filled in from defaults (`src/config.js`):

- **Language:** Ukrainian or English, detected from the prompt.
- **Terminal reply and to-do list:** written in that language.
- **Brand:** Woodmart.
- **Feature badges and headline:** standard texts.
- **Pins, PageSpeed card and end titles:** standard texts.

[`promo.config.json`](promo.config.json) is the full example and lists every option:

| Key | What it controls |
|---|---|
| `prompt` | Text typed into the terminal |
| `sites[]` | `{ url, label? }`. The first is the main store, the rest open as extra tabs. The label defaults to the page title |
| `brand` | Name, terminal watermark, browser tab suffix |
| `terminal` | `cwd`, `reply`, MCP `calls` (tool / ability / result), `todos`, spinner text |
| `features`, `marquee` | Feature badges and the outlined background text in scene 2 |
| `headline` | Big typography card (`kicker`, two `lines`, `stars`) |
| `pins` | `designSystem`, `pageLoad`, `mobile`. Set one to `false` to hide it. Values like `0.2s` or `100%` animate |
| `pagespeed` | `enabled`, four `scores` |
| `lockup` | End title and tagline |
| `audio.track` | Your own music file instead of the synthesized one |
| `render` | `blur` (motion-blur sub-frames), `crf` |

**The timeline adapts to the project.** A longer prompt types longer. If the capture can't find a step (for example, a site without Quick View, a side cart or a filter), that step is skipped instead of failing. Every extra site adds a tab segment. The end titles always land on the beat.

## Commands

`[project]` is a name from `projects/` or a path to a `.json` file. It defaults to `promo.config.json`.

| Command | What it does |
|---|---|
| `node cli.js new "<prompt with URLs>" [--name x]` | Creates `projects/<name>.json` |
| `node cli.js all [project]` | Full pipeline: capture (if needed), audio, video, mux |
| `node cli.js capture [project]` | Re-captures the sites into `work/<name>/site<i>/` |
| `node cli.js video [project] [--from 3 --to 7] [--blur 1]` | Frames only. `--blur 1` is a quick draft |
| `node cli.js audio [project]` | Synthesizes the soundtrack |
| `node cli.js mux [project] [--audio file]` | Merges video and audio, normalized to −14 LUFS / −1 dBTP |
| `node cli.js stills [project] --t 2,5,9` | Saves review PNGs |
| `node cli.js preview [project]` | Live loop in your browser |

## How it works

| Layer | Tool |
|---|---|
| Site capture | **Playwright**, headless Chromium (`src/capture/site.js`). It closes marketing popups and captures the real hover, Quick View, side cart, sticky header, mega menu, Shop page and AJAX filter states. Each step is optional |
| Animation | Plain **HTML/CSS/SVG** with 3D transforms (`src/scenes/`). There are no CSS animations: every element is a pure function of `t` |
| Timing | `src/timeline.js`. `configure(config, layouts)` builds the schedule. The browser scenes and the audio synth both run it, so picture and sound stay in sync |
| Frame render | Parallel headless pages call `__seek(t)` and take a CDP screenshot. **FFmpeg** blends 4 sub-frames per frame (`tmix`, 180° shutter) and encodes libx264 at 60 fps |
| Sound | `src/audio/synth.js`. Generated sample by sample at 48 kHz with no samples: drums, bass, pads, risers, UI clicks and reverb |
| Mix | FFmpeg two-pass `loudnorm` to −14 LUFS, AAC 320k |

Output folders are git-ignored: `work/<name>/` holds the captures and intermediates, `out/<name>.mp4` is the final video.

**Note:** capturing the main store adds one product to the cart inside the headless browser session, so the side cart can be filmed. No order is created, and the session is discarded when the browser closes.
