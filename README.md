# Resume Editor

A lightweight PHP CV/resume generator driven entirely by a single Markdown file (`cv.md`). No database, no build step — just edit your Markdown and reload.

## Features

- **Markdown-only editing** — all content lives in `cv.md`; no HTML to touch
- **Multilingual** — French (`?lang=fr`, default) and English (`?lang=en`) out of the box
- **Language switcher** — a one-click toggle button appears on the rendered CV
- **Font Awesome icons** — embed icons anywhere with `[fa:solid:icon-name]` shortcodes
- **Two-column layout** — sidebar (contact, skills, languages…) + main column (profile, experience, education)
- **Zero dependencies** — pure PHP 8+, no Composer packages required

## Project structure

```
.
├── index.php            # Main controller: parses cv.md, renders HTML
├── MiniMarkdown.php     # Tiny inline Markdown renderer (bold, FA shortcodes)
├── cv.md                # YOUR resume content — only file you need to edit
├── template.html        # English HTML template (lang=en)
├── template_fr.html     # French HTML template (lang=fr, default)
├── style.css            # All styles
├── photo.jpg            # Profile photo (replace with yours)
└── README.md            # This file
```

## Quick start

1. **Requirements:** PHP 8.0+ with a web server (Apache, Nginx, or `php -S`)
2. **Clone / copy** the project folder to your web root or run locally:
   ```bash
   cd resume-editor
   php -S localhost:8080
   ```
3. Open `http://localhost:8080` in your browser.
4. Edit `cv.md` and reload — changes appear instantly.

## cv.md structure

The file is split into top-level sections with `# SECTION_NAME` headings. The key names must stay in uppercase English (they are internal identifiers); the display labels are controlled by the locale config in `index.php`.

```markdown
# HEADER
Your Name
Job Title
LinkedIn: @handle | https://linkedin.com/in/handle
Site web: www.example.com | https://www.example.com

# PROFILE
One-paragraph professional summary.

# CONTACT
- Téléphone : +33 6 00 00 00 00 | tel:+33600000000
- Email : you@example.com | mailto:you@example.com
- Localisation : Paris, France
- Date de naissance : 01.01.1990
- Permis : Permis B

# SKILLS
- [fa:solid:code] Full-Stack Development
- [fa:brands:python] Python / Django

# CERTIFICATIONS
- AWS Solutions Architect
- Google Cloud Professional

# LANGUAGES
- Français — Natif
- Anglais — Courant - C1

# HOBBIES
- Photography
- Hiking

# EXPERIENCE

## Job Title - Company
**Jan 2023 – Present**
Company Name
- Achievement or responsibility
- Another bullet point

# EDUCATION

## Degree Name
School Name
**Sept 2018 – June 2020**
```

### Font Awesome shortcodes

Use `[fa:prefix:icon-name]` anywhere in `cv.md`:

| Prefix | Example |
|--------|---------|
| `solid` | `[fa:solid:database]` |
| `brands` | `[fa:brands:github]` |
| `regular` | `[fa:regular:clock]` |

Browse icons at [fontawesome.com/icons](https://fontawesome.com/icons).

## Multilingual support

### Switching language

Append `?lang=fr` (default) or `?lang=en` to the URL, or click the globe button on the CV itself.

| URL | Behaviour |
|-----|-----------|
| `index.php` | French (default) |
| `index.php?lang=fr` | French — uses `template_fr.html` |
| `index.php?lang=en` | English — uses `template.html` |

### How it works

The content in `cv.md` is language-neutral — section keys (`PROFILE`, `SKILLS`, …) are internal identifiers. Each locale in `index.php` provides:

- **`sectionLabels`** — display labels for sidebar/main headings when no custom text appears in the `# heading`
- **`contactIcons`** — maps contact label keywords (e.g. `téléphone`, `phone`) to Font Awesome icons
- **`template`** — which HTML template file to load

### Adding a new language

1. Duplicate `template_fr.html` → `template_de.html` (adjust `<html lang="...">` and `<title>`)
2. Add a new key to the `$locale` array in `index.php`:
   ```php
   'de' => [
       'sectionLabels' => [
           'PROFILE'    => 'Profil',
           'SKILLS'     => 'Fähigkeiten',
           'EXPERIENCE' => 'Berufserfahrung',
           // ...
       ],
       'contactIcons' => [
           'telefon'   => '<i class="fa-solid fa-phone"></i>',
           'email'     => '<i class="fa-solid fa-envelope"></i>',
           // ...
       ],
       'template' => 'template_de.html',
   ],
   ```
3. Visit `index.php?lang=de`.

## Customising the design

All visual styling is in `style.css`. CSS custom properties at the top of the file control colours:

```css
:root {
  --accent: #4A6FA5;   /* sidebar background & highlight colour */
}
```

## Profile photo

Place a `photo.jpg` next to `index.php`. The image is displayed in the sidebar. Any square or portrait JPEG/PNG works; it is cropped to a circle via CSS.

## Extending section rendering

`index.php` maps each section key to a rendering strategy:

| Strategy | Used for |
|----------|----------|
| `MiniMarkdown::inline()` | PROFILE (plain paragraph) |
| `MiniMarkdown::listItems()` | SKILLS, CERTIFICATIONS, HOBBIES |
| `renderTimeline()` | EXPERIENCE, EDUCATION |
| Custom loop | CONTACT, LANGUAGES, LINKS |

To add a new section, add the key to `$sectionLabels`, write a rendering block in section 4 of `index.php`, and add `{{MY_SECTION}}` / `{{MY_SECTION_TITLE}}` placeholders to both templates.
