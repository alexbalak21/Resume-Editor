# CV Editor

A small PHP web app for maintaining several versions of your résumé (technician, developer, data scientist, etc.) as JSON profiles, editing them with a live split-screen preview, and printing a clean A4 page from any of them.

## Features

- **Multiple profiles** — each résumé version is a standalone JSON file in `data/` (`technician.json`, `developer.json`, `data_scientist.json`...). Switch between them from the topbar dropdown, or create a new blank one with **"+ Nouveau"**.
- **Split-screen editor** (`editor.php`) — form fields on the left (header, profile text, contact, skills, certifications, languages, hobbies, experience, education, each with add/remove controls for repeatable items), live-rendered CV preview on the right in an isolated iframe.
- **Photo upload** — JPG/PNG/WebP, 1 MB max, validated both client- and server-side (real MIME sniffing, not just the file extension). Stored in `storage/photos/<profile>.<ext>` and saved automatically.
- **A4 print / PDF export** — "Aperçu A4 / Imprimer" saves the current profile, then opens `preview.php` in a new tab: a bare CV page (no editor UI) sized exactly to A4, ready for Ctrl/Cmd+P → Save as PDF.
- **Inline formatting support** in text fields: `**bold**` and Font Awesome icon shortcodes like `[fa:solid:database]` or `[fa:brands:python]`.

## Requirements

- PHP 8.1+ with the `mbstring` and `fileinfo` extensions (both are enabled by default on most PHP installs; on a bare Ubuntu box you may need `sudo apt install php-cli php-mbstring`).
- No database, no Composer dependencies — everything runs off the filesystem.

## Getting started

```bash
cd app
php -S localhost:8000
```

Then open `http://localhost:8000/editor.php`.

## Project structure

```
app/
├── editor.php          Main editor page (split-screen form + live preview)
├── preview.php          Standalone printable A4 view for a saved profile
├── api.php               JSON API backing the editor (see below)
├── src/
│   └── Renderer.php      Converts a profile's JSON data into the CV's HTML markup
├── assets/
│   ├── editor.css        Editor UI styling
│   ├── editor.js          Editor UI logic (form binding, live preview, save/upload)
│   └── template.css       CV visual styling (also used by preview.php)
├── data/
│   ├── technician.json
│   ├── developer.json
│   └── data_scientist.json
└── storage/
    └── photos/            Uploaded profile photos (<profile>.<ext>)
```

## The profile JSON format

Each file in `data/` looks like this:

```json
{
  "lang": "fr",
  "header": {
    "fullName": "...",
    "jobTitle": "...",
    "photo": "storage/photos/developer.jpg",
    "links": [
      { "label": "LinkedIn", "text": "@handle", "url": "https://..." }
    ]
  },
  "profile": { "title": "Profil", "text": "**Bold intro** text..." },
  "contact": {
    "title": "Contact",
    "items": [
      { "label": "Téléphone", "display": "(+33) ...", "href": "tel:+33..." }
    ]
  },
  "skills":          { "title": "Compétences",   "items": ["[fa:solid:database] SQL"] },
  "certifications":  { "title": "Certifications", "items": ["IBM Java Developer"] },
  "languages":       { "title": "Langues", "items": [{ "name": "Anglais", "level": "C1" }] },
  "hobbies":         { "title": "Intérêts", "items": ["Escalade"] },
  "experience": {
    "title": "Expériences Professionnelles",
    "items": [
      { "title": "Poste - Entreprise", "meta": "Dates", "bullets": ["...", "..."] }
    ]
  },
  "education": {
    "title": "Formations",
    "items": [
      { "title": "Diplôme", "meta": "École — Dates", "bullets": [] }
    ]
  }
}
```

You can edit these files by hand or entirely through the editor UI.

## API reference (`api.php`)

All responses are JSON.

| Action | Method | Params | Description |
|---|---|---|---|
| `list` | GET | — | Returns `{"profiles": [...]}`, the list of available profile names. |
| `load` | GET | `profile` | Returns `{"profile": ..., "data": {...}}` for one profile. |
| `save` | POST | JSON body `{"profile": ..., "data": {...}}` | Overwrites `data/<profile>.json`. |
| `create` | POST | JSON body `{"profile": "name"}` | Creates a new blank profile (409 if it already exists). |
| `render` | POST | JSON body `{"data": {...}}` | Returns `{"html": "..."}`, the rendered `#page` markup for the live preview. |
| `upload_photo` | POST (multipart) | `profile`, `photo` (file) | Uploads/replaces a profile's photo. 1 MB max, jpg/png/webp only. |
| `delete_photo` | POST | JSON body `{"profile": ...}` | Removes the profile's photo file. |

Profile names are restricted to `[a-zA-Z0-9_-]` everywhere to prevent path traversal.

## Printing to PDF

1. Click **"Aperçu A4 / Imprimer"** in the editor (this saves your current edits first).
2. In the new tab, click **"Imprimer / PDF"** (or just Ctrl/Cmd+P).
3. Choose "Save as PDF" as the destination. Margins should already be at 0 and paper size A4 thanks to the `@page` rule in `template.css` — if your browser overrides this, set margins to "None" manually in the print dialog.

## Known limitations / not implemented

- No authentication — anyone with access to the running app can edit and save any profile.
- No EN/FR language switch in the editor UI yet, even though each profile has a `lang` field.
- `storage/photos/` needs to be writable by the PHP process.
