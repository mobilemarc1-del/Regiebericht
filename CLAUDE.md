# CLAUDE.md - Regiebericht Generator

## Project Overview

A Progressive Web Application (PWA) for generating construction/work site reports ("Regieberichte") as PDFs. Built entirely with vanilla HTML, CSS, and JavaScript in a single monolithic `index.html` file. German-language UI. No backend — all data lives in browser localStorage.

## Tech Stack

- **HTML5 / CSS3 / Vanilla JavaScript (ES5)** — no frameworks, no build tools
- **jsPDF 2.5.1** + **jsPDF-AutoTable 3.8.1** (loaded via CDN) — PDF generation
- **Service Worker** (`sw.js`) — offline caching
- **PWA Manifest** (`manifest.json`) — installable app
- **localStorage** — all data persistence

## File Structure

```
index.html        # Entire application (HTML + CSS + JS, ~2000 lines)
sw.js             # Service Worker for offline caching
manifest.json     # PWA manifest (standalone mode, theme: #1e3c72)
icon-192.png      # PWA icon 192x192
icon-512.png      # PWA icon 512x512
README.md         # Basic project README
```

## Architecture

The app is a **single-file monolith** (`index.html`). Code is organized into clearly commented sections:

| Section | Purpose |
|---|---|
| KONFIGURATION | `CONFIG` object with all constants and localStorage keys |
| WOCHENTAG & ZUSCHLAG ERKENNUNG | Day-of-week detection and surcharge calculation |
| STAMMDATEN: FIRMEN | Company master data CRUD |
| STAMMDATEN: MITARBEITER | Employee master data CRUD |
| SETTINGS DIALOG | Settings UI management |
| E-MAIL FUNKTION | Email sharing via mailto: / Web Share API |
| ZÄHLER-VERWALTUNG | Auto-incrementing report number (RB-YYYY-NNN) |
| EINSTELLUNGEN SPEICHERN | Settings persistence to localStorage |
| HISTORIE | Report history (last 20 reports) |
| MITARBEITER | Employee time entry rows |
| MATERIAL | Material/equipment entry rows |
| UNTERSCHRIFTEN | Canvas-based signature capture (touch + Apple Pencil) |
| PDF GENERIERUNG | Full PDF creation with jsPDF |
| INITIALISIERUNG | App startup and event binding |
| PWA SERVICE WORKER | Service Worker registration |

## Key Data Models

**CONFIG object** defines all app constants:
- `prefix: 'RB-'` — report number prefix
- `normalStunden: 8` — standard hours/day
- `zuschlagSamstag/Sonntag/Ueberzeit/Nacht` — surcharge percentages
- `nachtBeginn: 20, nachtEnde: 6` — night work hours
- Storage keys: `regiebericht_counter`, `regiebericht_history`, `regiebericht_settings`, `regiebericht_firmen`, `regiebericht_stamm_mitarbeiter`

**Report number format:** `RB-YYYY-NNN` (auto-increments, resets annually)

## Conventions

- **Language:** All UI text, variable names, and function names are in **German** (e.g., `mitarbeiter`, `taetigkeiten`, `datum`). Keep this consistent.
- **Naming:** camelCase for JS identifiers, hyphenated IDs in HTML (e.g., `berichtNr`, `firmaSelect`)
- **CSS classes:** BEM-like naming (e.g., `ma-details`, `mat-menge`)
- **No classes/prototypes:** Functional programming style with plain functions
- **Dynamic UI:** Event listeners attached dynamically, innerHTML for rendering lists
- **All German comments** — maintain German for code comments

## Development

### No Build Process

Open `index.html` directly in a browser or serve with any static file server. No npm, no bundling, no compilation.

### Testing

No automated tests. Test manually in browser (Chrome, Safari, Firefox). Key test scenarios:
- Employee row add/remove and hour calculation
- Surcharge detection (weekend, overtime, night)
- PDF generation and download
- Signature drawing (mouse + touch)
- localStorage persistence across reloads
- Offline functionality via Service Worker

### Deployment

Deployed via **GitHub Pages**. Push to main branch to deploy. No CI/CD pipeline.

## Browser Requirements

- ES5+ JavaScript, Canvas API, Service Worker, localStorage, Touch/Pointer Events
- Optimized for iPad with Apple Pencil (pressure sensitivity support)
- iOS Safari 12+, modern Chrome/Firefox/Edge

## Important Notes for AI Assistants

1. **Single-file architecture:** All changes to the app go in `index.html`. Do not split into separate files unless explicitly asked.
2. **No dependencies to install:** No package.json, no node_modules.
3. **German language:** All user-facing text and most code identifiers are German. Maintain this convention.
4. **localStorage is the database:** There is no backend. All CRUD operations use localStorage with the keys defined in `CONFIG`.
5. **CDN dependencies:** jsPDF libraries are loaded from CDN (`cdnjs.cloudflare.com`). The Service Worker caches these for offline use.
6. **Canvas signatures:** The signature implementation uses Bezier curves with pressure sensitivity. Be careful modifying this — it's touch-device sensitive.
7. **PDF layout:** The PDF generation section is the most complex part (~300+ lines). Changes here require visual verification of the output PDF.
8. **No linting/formatting tools:** There are no eslint, prettier, or other code quality tools configured. Follow existing code style.
