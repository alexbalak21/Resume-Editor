# Implementation: Hydrating an HTML Template Using Markdown Content

This document explains how to structure a `cv.md` file, how to split it into sections, and how to inject each section into an HTML template using PHP.

---

# 1. Project Structure

```
/cv/
   index.php
   template.html
   cv.md
   Parsedown.php
   shorter.css
   script.js
```

- `cv.md` contains the CV content only.
- `template.html` contains the styled HTML layout with placeholders.
- `index.php` loads the Markdown, splits it into sections, converts it to HTML, and injects it into the template.

---

# 2. Recommended Structure for `cv.md`

Each section begins with a level‑1 Markdown heading (`#`).

Example:

```md
# HEADER
Alexandre Balakirev  
TypeScript / Node / React Developer

# PROFILE
Full‑Stack Developer specialized in JavaScript, TypeScript, Node.js and React...

# SKILLS
- JavaScript / TypeScript
- Node.js / NestJS
- PostgreSQL
- Docker / AWS

# EXPERIENCE
## Full‑Stack Developer — Novocib (2025–2026)
- Internal API development...
- Backend automation...

## SAGE X3 Developer — Kardol (2024–2025)
- ERP development...
```

Each `# SECTION_NAME` becomes a key you can target in your HTML template.

---

# 3. Splitting Markdown into Sections (PHP)

```php
$md = file_get_contents('cv.md');

// Split by level‑1 headings
$sections = preg_split('/^# (.+)$/m', $md, -1, PREG_SPLIT_DELIM_CAPTURE);

$parsed = [];
for ($i = 1; $i < count($sections); $i += 2) {
    $title = trim($sections[$i]);
    $content = trim($sections[$i + 1]);
    $parsed[$title] = $content;
}
```

This produces:

```
$parsed["HEADER"]
$parsed["PROFILE"]
$parsed["SKILLS"]
$parsed["EXPERIENCE"]
```

---

# 4. Converting Markdown to HTML

```php
require 'Parsedown.php';
$Parsedown = new Parsedown();

foreach ($parsed as $key => $value) {
    $parsed[$key] = $Parsedown->text($value);
}
```

---

# 5. Injecting Sections into the HTML Template

In `template.html`, add placeholders:

```html
<section class="summary">
    {{PROFILE}}
</section>

<section class="skills">
    {{SKILLS}}
</section>

<section class="experience">
    {{EXPERIENCE}}
</section>
```

Then in `index.php`:

```php
$template = file_get_contents('template.html');

foreach ($parsed as $key => $html) {
    $template = str_replace('{{'.$key.'}}', $html, $template);
}

echo $template;
```

---

# 6. Result

- Content is fully managed in `cv.md`
- Styling stays in `template.html` + CSS
- The server automatically hydrates each section
- You can reorder sections simply by moving headings in the Markdown file
- The system behaves like a lightweight CMS for your CV

---
