# Resume Editor — Web App Implementation Plan

## 1. Overview

Transform the current file-based PHP CV renderer into a multi-user SaaS web application where users can:

- Create an account and log in
- Own multiple CV documents
- Edit each CV section in Markdown (with live preview)
- Export to print-ready PDF
- Switch between CV templates

The existing `index.php` / `MiniMarkdown.php` / `template.html` / `style.css` pipeline becomes the **rendering engine** — it stays almost unchanged but is called dynamically instead of reading from a flat file.

---

## 2. Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Language | PHP 8.2 | Existing codebase, no rewrite |
| Framework | Laravel 11 | Auth, ORM, routing, queues — batteries included |
| Database | MySQL 8 | Relational, proven for user/document data |
| Frontend | Blade + Alpine.js + Axios | Lightweight, no heavy build step |
| Editor | CodeMirror 6 | Markdown syntax highlighting in-browser |
| Preview | `<iframe>` rendered server-side | Reuses existing renderer exactly |
| PDF export | Puppeteer (Node) or wkhtmltopdf | Prints the rendered CV page to PDF |
| Auth | Laravel Breeze (session-based) | Simple, clean, included |
| Storage | Local disk / S3 | Profile photos |
| Queue | Laravel Queues + Redis | PDF generation jobs |

---

## 3. Database Schema

```sql
-- Users (handled by Laravel Breeze)
CREATE TABLE users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password      VARCHAR(255) NOT NULL,
    avatar        VARCHAR(500) NULL,          -- S3 or local path
    remember_token VARCHAR(100) NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL
);

-- One user → many CVs
CREATE TABLE resumes (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title         VARCHAR(255) NOT NULL DEFAULT 'Mon CV',   -- user-facing label
    template      VARCHAR(100) NOT NULL DEFAULT 'default',  -- template key
    is_public     BOOLEAN NOT NULL DEFAULT FALSE,           -- shareable link
    public_slug   VARCHAR(64) UNIQUE NULL,                  -- e.g. "alex-balak-2025"
    photo_path    VARCHAR(500) NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL
);

-- One CV → many sections (each section = one markdown block)
CREATE TABLE resume_sections (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resume_id     BIGINT UNSIGNED NOT NULL REFERENCES resumes(id) ON DELETE CASCADE,
    section_key   VARCHAR(64) NOT NULL,     -- 'HEADER','PROFILE','EXPERIENCE', etc.
    title_raw     VARCHAR(255) NOT NULL,    -- raw heading e.g. "# [fa:solid:address-card] CONTACT"
    content_md    LONGTEXT NOT NULL,        -- the raw markdown body for this section
    sort_order    SMALLINT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    UNIQUE KEY uq_resume_section (resume_id, section_key)
);

-- Audit log (optional but useful)
CREATE TABLE resume_history (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resume_id     BIGINT UNSIGNED NOT NULL REFERENCES resumes(id) ON DELETE CASCADE,
    section_key   VARCHAR(64) NOT NULL,
    content_md    LONGTEXT NOT NULL,        -- snapshot before the change
    saved_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### Why sections are stored individually

Each section (`HEADER`, `PROFILE`, `EXPERIENCE`…) is its own row. This means:
- The editor can save/preview **one section at a time** without touching the others
- Undo history is per-section
- You can reorder sections later
- The full `.md` file is reassembled on the fly when rendering

---

## 4. Application Structure (Laravel)

```
app/
  Http/
    Controllers/
      Auth/                        ← Breeze controllers (login, register)
      DashboardController.php      ← list user's CVs
      ResumeController.php         ← CRUD for resumes
      SectionController.php        ← save/preview individual sections
      PreviewController.php        ← full CV render (iframe src)
      ExportController.php         ← trigger PDF generation
      PublicResumeController.php   ← public shareable view
  Models/
    User.php
    Resume.php
    ResumeSection.php
    ResumeHistory.php
  Services/
    ResumeRendererService.php      ← wraps the existing index.php logic
    PdfExportService.php           ← calls Puppeteer / wkhtmltopdf
  Jobs/
    GeneratePdfJob.php

resources/
  views/
    layouts/
      app.blade.php                ← authenticated shell
      guest.blade.php              ← login/register shell
    dashboard/
      index.blade.php              ← CV list / dashboard
    resume/
      edit.blade.php               ← main editor page
      preview.blade.php            ← standalone preview (iframe target)
      public.blade.php             ← shareable public view
    components/
      section-editor.blade.php     ← Alpine.js CodeMirror component
      cv-card.blade.php            ← dashboard card per CV

renderer/                          ← the existing PHP renderer (kept separate)
  MiniMarkdown.php
  template.html                    ← default template
  templates/
    default/
      template.html
      style.css
    minimal/
      template.html
      style.css
```

---

## 5. Routing

```php
// routes/web.php

// Auth (Breeze)
require __DIR__.'/auth.php';

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');

// Resume CRUD
Route::middleware('auth')->prefix('resumes')->name('resumes.')->group(function () {
    Route::get('/',                   [ResumeController::class, 'index']);
    Route::get('/create',             [ResumeController::class, 'create'])->name('create');
    Route::post('/',                  [ResumeController::class, 'store'])->name('store');
    Route::get('/{resume}/edit',      [ResumeController::class, 'edit'])->name('edit');
    Route::patch('/{resume}',         [ResumeController::class, 'update'])->name('update');
    Route::delete('/{resume}',        [ResumeController::class, 'destroy'])->name('destroy');

    // Section save (AJAX)
    Route::post('/{resume}/sections/{key}', [SectionController::class, 'save'])->name('sections.save');

    // Live preview (iframe src)
    Route::get('/{resume}/preview',   [PreviewController::class, 'show'])->name('preview');

    // PDF export
    Route::post('/{resume}/export',   [ExportController::class, 'generate'])->name('export');
    Route::get('/{resume}/download',  [ExportController::class, 'download'])->name('download');

    // Photo upload
    Route::post('/{resume}/photo',    [ResumeController::class, 'uploadPhoto'])->name('photo');
});

// Public shareable CV
Route::get('/cv/{slug}', [PublicResumeController::class, 'show'])->name('cv.public');
```

---

## 6. Core Service — ResumeRendererService

This is the heart of the app. It takes a `Resume` model, reassembles the full `.md` string from its sections, and returns rendered HTML — using the exact same logic as `index.php`.

```php
// app/Services/ResumeRendererService.php

namespace App\Services;

use App\Models\Resume;

class ResumeRendererService
{
    /**
     * Render a Resume to a full HTML string.
     * Accepts an optional array to override specific sections (used for live preview).
     */
    public function render(Resume $resume, array $overrides = []): string
    {
        // 1. Reassemble the full markdown from sections
        $md = $this->assembleMarkdown($resume, $overrides);

        // 2. Run through the renderer (extracted from index.php into a callable)
        $html = $this->renderMarkdown($md, $resume);

        return $html;
    }

    /**
     * Build the full cv.md string from section rows, in sort_order.
     */
    public function assembleMarkdown(Resume $resume, array $overrides = []): string
    {
        $sections = $resume->sections()->orderBy('sort_order')->get();
        $md = '';
        foreach ($sections as $section) {
            $content = $overrides[$section->section_key] ?? $section->content_md;
            $md .= $section->title_raw . "\n" . $content . "\n\n";
        }
        return $md;
    }

    /**
     * Core renderer — logic extracted verbatim from index.php.
     * Accepts markdown string, returns full HTML page.
     */
    public function renderMarkdown(string $md, Resume $resume): string
    {
        // Reset global slot store for Font Awesome shortcodes
        $GLOBALS['__mm_slots'] = [];

        // --- all the parsing logic from index.php goes here ---
        // parseFaShortcodes(), parseTimelineSection(), renderTimeline(), etc.
        // Build $placeholders array...
        // Load the template for $resume->template
        // Inject $placeholders into template HTML
        // Return the complete HTML string

        $templatePath = resource_path("renderer/templates/{$resume->template}/template.html");
        // ... (full logic, see index.php)

        return $html;
    }
}
```

**Key point:** extract all the functions from `index.php` into this service class. Nothing else changes — same regex, same MiniMarkdown, same template.

---

## 7. Editor Page — How It Works

`/resumes/{id}/edit` is the main editor. It has two panels:

**Left panel — Section tabs + CodeMirror editor**
- Tabs: HEADER | PROFILE | CONTACT | SKILLS | EXPERIENCE | EDUCATION | …
- Clicking a tab loads that section's markdown into the CodeMirror editor
- A "Save" button (or auto-save after 1s debounce) POSTs to `/resumes/{id}/sections/{key}`
- A "Preview" button (or live auto-preview) refreshes the `<iframe>` on the right

**Right panel — Live preview iframe**
- `<iframe src="/resumes/{id}/preview">` 
- After any save, JS calls `iframe.contentWindow.location.reload()`

### Blade template sketch

```html
{{-- resources/views/resume/edit.blade.php --}}
<div class="editor-layout" x-data="resumeEditor({{ $resume->id }})">

    {{-- LEFT: section tabs + editor --}}
    <div class="editor-panel">
        <div class="section-tabs">
            @foreach($resume->sections as $section)
            <button
                class="tab"
                :class="{ active: activeSection === '{{ $section->section_key }}' }"
                @click="loadSection('{{ $section->section_key }}')">
                {{ $section->section_key }}
            </button>
            @endforeach
        </div>

        <div id="codemirror-host"></div>  {{-- CodeMirror mounts here --}}

        <div class="editor-actions">
            <button @click="saveSection()" :disabled="saving">
                <span x-show="!saving">💾 Sauvegarder</span>
                <span x-show="saving">Sauvegarde…</span>
            </button>
            <span x-show="saved" class="saved-badge">✓ Sauvegardé</span>
        </div>
    </div>

    {{-- RIGHT: live preview iframe --}}
    <div class="preview-panel">
        <div class="preview-toolbar">
            <a href="{{ route('resumes.download', $resume) }}" class="btn-export">
                ⬇ Exporter PDF
            </a>
        </div>
        <iframe
            id="cv-preview"
            src="{{ route('resumes.preview', $resume) }}"
            class="cv-frame">
        </iframe>
    </div>
</div>

<script>
function resumeEditor(resumeId) {
    return {
        activeSection: '{{ $resume->sections->first()->section_key }}',
        sections: @json($resume->sections->pluck('content_md', 'section_key')),
        saving: false,
        saved: false,
        editor: null,   // CodeMirror instance

        init() {
            this.mountEditor(this.sections[this.activeSection]);
        },

        mountEditor(content) {
            // CodeMirror 6 setup with markdown language
            if (this.editor) this.editor.destroy();
            this.editor = new EditorView({
                doc: content,
                extensions: [
                    basicSetup,
                    markdown(),
                    EditorView.updateListener.of(update => {
                        if (update.docChanged) {
                            this.sections[this.activeSection] = update.state.doc.toString();
                            this.saved = false;
                            this.scheduleAutoSave();
                        }
                    })
                ],
                parent: document.getElementById('codemirror-host')
            });
        },

        loadSection(key) {
            this.activeSection = key;
            this.editor.dispatch({
                changes: {
                    from: 0,
                    to: this.editor.state.doc.length,
                    insert: this.sections[key] ?? ''
                }
            });
        },

        autoSaveTimer: null,
        scheduleAutoSave() {
            clearTimeout(this.autoSaveTimer);
            this.autoSaveTimer = setTimeout(() => this.saveSection(), 1500);
        },

        async saveSection() {
            this.saving = true;
            await axios.post(`/resumes/${resumeId}/sections/${this.activeSection}`, {
                content_md: this.sections[this.activeSection]
            });
            this.saving = false;
            this.saved = true;
            document.getElementById('cv-preview').contentWindow.location.reload();
        }
    }
}
</script>
```

---

## 8. Section Save — Controller

```php
// app/Http/Controllers/SectionController.php

public function save(Request $request, Resume $resume, string $key): JsonResponse
{
    // Ownership check
    abort_if($resume->user_id !== auth()->id(), 403);

    $request->validate([
        'content_md' => 'required|string|max:100000',
    ]);

    $section = $resume->sections()->where('section_key', $key)->firstOrFail();

    // Save history snapshot before overwriting
    ResumeHistory::create([
        'resume_id'   => $resume->id,
        'section_key' => $key,
        'content_md'  => $section->content_md,
    ]);

    $section->update([
        'content_md' => $request->content_md,
    ]);

    return response()->json(['ok' => true]);
}
```

---

## 9. Preview — Controller

```php
// app/Http/Controllers/PreviewController.php

public function show(Resume $resume): Response
{
    abort_if($resume->user_id !== auth()->id(), 403);

    $html = app(ResumeRendererService::class)->render($resume);

    // Inject the photo URL into the HTML
    // (replace src="photo.jpg" with the actual storage URL)
    if ($resume->photo_path) {
        $photoUrl = Storage::url($resume->photo_path);
        $html = str_replace('src="photo.jpg"', 'src="' . $photoUrl . '"', $html);
    }

    return response($html)->header('Content-Type', 'text/html');
}
```

---

## 10. PDF Export

Use **Puppeteer** (Node.js) called from PHP via `exec()`, or install `wkhtmltopdf`.

### Puppeteer approach (recommended)

```js
// resources/js/pdf-export.js  (run by Node)
const puppeteer = require('puppeteer');

const url  = process.argv[2];   // the preview URL with a signed token
const out  = process.argv[3];   // output path

(async () => {
    const browser = await puppeteer.launch({ args: ['--no-sandbox'] });
    const page    = await browser.newPage();
    await page.goto(url, { waitUntil: 'networkidle0' });
    await page.pdf({
        path:   out,
        format: 'A4',
        printBackground: true,
        margin: { top: 0, right: 0, bottom: 0, left: 0 }
    });
    await browser.close();
})();
```

```php
// app/Services/PdfExportService.php

public function generate(Resume $resume): string
{
    $signedUrl = URL::temporarySignedRoute('resumes.preview', now()->addMinutes(5), $resume);
    $outPath   = storage_path("app/exports/cv-{$resume->id}.pdf");

    exec("node " . base_path('resources/js/pdf-export.js') . " '{$signedUrl}' '{$outPath}'");

    return $outPath;
}
```

```php
// app/Http/Controllers/ExportController.php

public function download(Resume $resume): BinaryFileResponse
{
    abort_if($resume->user_id !== auth()->id(), 403);
    $path = app(PdfExportService::class)->generate($resume);
    return response()->download($path, $resume->title . '.pdf');
}
```

---

## 11. Dashboard

Simple grid of CV cards. Each card shows:
- CV title (editable inline)
- Last updated date
- Thumbnail (first-render screenshot, optional — can be a placeholder initially)
- Buttons: Edit | Preview | Export PDF | Delete | Duplicate

```php
// app/Http/Controllers/DashboardController.php

public function index(): View
{
    $resumes = auth()->user()->resumes()->latest()->get();
    return view('dashboard.index', compact('resumes'));
}
```

### Creating a new CV

When a user clicks "New CV", the app creates a `Resume` row and seeds it with the default section rows (copied from the bundled `cv.md` template), then redirects to the editor.

```php
// app/Http/Controllers/ResumeController.php

public function store(Request $request): RedirectResponse
{
    $request->validate(['title' => 'required|string|max:255']);

    $resume = auth()->user()->resumes()->create([
        'title'    => $request->title,
        'template' => 'default',
    ]);

    // Seed default sections from the bundled template
    $this->seedDefaultSections($resume);

    return redirect()->route('resumes.edit', $resume);
}

private function seedDefaultSections(Resume $resume): void
{
    $defaults = [
        ['key' => 'HEADER',        'title' => '# HEADER',        'order' => 0, 'content' => "Votre Nom\nVotre Titre\nLinkedIn: @pseudo | https://linkedin.com"],
        ['key' => 'PROFILE',       'title' => '# PROFILE',       'order' => 1, 'content' => "Décrivez votre profil ici."],
        ['key' => 'CONTACT',       'title' => '# CONTACT',       'order' => 2, 'content' => "- Téléphone : 06.00.00.00.00\n- Email : email@example.com\n- Localisation : Paris, France"],
        ['key' => 'SKILLS',        'title' => '# SKILLS',        'order' => 3, 'content' => "- Compétence 1\n- Compétence 2"],
        ['key' => 'CERTIFICATIONS','title' => '# CERTIFICATIONS','order' => 4, 'content' => "- Certification 1"],
        ['key' => 'LANGUAGES',     'title' => '# LANGUAGES',     'order' => 5, 'content' => "- Français — Natif"],
        ['key' => 'HOBBIES',       'title' => '# HOBBIES',       'order' => 6, 'content' => "- Activité 1"],
        ['key' => 'EXPERIENCE',    'title' => '# EXPERIENCE',    'order' => 7, 'content' => "## Poste — Entreprise\n**Mois AAAA – Mois AAAA**\n- Description"],
        ['key' => 'EDUCATION',     'title' => '# EDUCATION',     'order' => 8, 'content' => "## Diplôme\nÉtablissement\n**AAAA – AAAA**"],
    ];

    foreach ($defaults as $d) {
        ResumeSection::create([
            'resume_id'   => $resume->id,
            'section_key' => $d['key'],
            'title_raw'   => $d['title'],
            'content_md'  => $d['content'],
            'sort_order'  => $d['order'],
        ]);
    }
}
```

---

## 12. Models

```php
// app/Models/Resume.php
class Resume extends Model
{
    protected $fillable = ['user_id','title','template','is_public','public_slug','photo_path'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function sections(): HasMany   { return $this->hasMany(ResumeSection::class)->orderBy('sort_order'); }
    public function history(): HasMany    { return $this->hasMany(ResumeHistory::class); }
}

// app/Models/ResumeSection.php
class ResumeSection extends Model
{
    protected $fillable = ['resume_id','section_key','title_raw','content_md','sort_order'];

    public function resume(): BelongsTo  { return $this->belongsTo(Resume::class); }
}
```

---

## 13. Public / Shareable CV

Users can toggle `is_public = true` and get a link like `https://app.com/cv/alex-balak-2025`.

```php
// app/Http/Controllers/PublicResumeController.php

public function show(string $slug): Response
{
    $resume = Resume::where('public_slug', $slug)->where('is_public', true)->firstOrFail();
    $html = app(ResumeRendererService::class)->render($resume);
    return response($html)->header('Content-Type', 'text/html');
}
```

---

## 14. Photo Upload

Profile photos are stored on disk (or S3 in production).

```php
// app/Http/Controllers/ResumeController.php

public function uploadPhoto(Request $request, Resume $resume): JsonResponse
{
    abort_if($resume->user_id !== auth()->id(), 403);
    $request->validate(['photo' => 'required|image|max:2048']);

    $path = $request->file('photo')->store("resumes/{$resume->id}", 'public');
    $resume->update(['photo_path' => $path]);

    return response()->json(['url' => Storage::url($path)]);
}
```

---

## 15. Feature Roadmap

### Phase 1 — MVP
- [ ] Laravel install + Breeze auth (register/login/logout)
- [ ] Resume CRUD (create, list, delete)
- [ ] Section editor with CodeMirror
- [ ] Auto-save + live preview iframe
- [ ] Photo upload
- [ ] PDF export (Puppeteer)

### Phase 2 — Polish
- [ ] Multiple templates (switch in settings panel)
- [ ] Section reordering (drag-and-drop, `sort_order` update via AJAX)
- [ ] Undo history (browse `resume_history` per section)
- [ ] Public shareable link toggle + slug generator
- [ ] Duplicate CV

### Phase 3 — Advanced
- [ ] AI-assisted content suggestions (OpenAI/Claude API) — "Améliore cette expérience"
- [ ] Real-time collaboration (Laravel Reverb + WebSockets)
- [ ] Custom CSS editor per CV
- [ ] Import existing `.md` file
- [ ] CV analytics (view count on public link)

---

## 16. File / Folder Layout (final)

```
resume-editor/
├── app/
│   ├── Http/Controllers/
│   │   ├── DashboardController.php
│   │   ├── ResumeController.php
│   │   ├── SectionController.php
│   │   ├── PreviewController.php
│   │   ├── ExportController.php
│   │   └── PublicResumeController.php
│   ├── Models/
│   │   ├── Resume.php
│   │   ├── ResumeSection.php
│   │   └── ResumeHistory.php
│   └── Services/
│       ├── ResumeRendererService.php
│       └── PdfExportService.php
├── database/
│   └── migrations/
│       ├── create_resumes_table.php
│       ├── create_resume_sections_table.php
│       └── create_resume_history_table.php
├── renderer/                        ← your existing engine
│   ├── MiniMarkdown.php
│   └── templates/
│       ├── default/
│       │   ├── template.html
│       │   └── style.css
│       └── minimal/
│           ├── template.html
│           └── style.css
├── resources/
│   ├── views/
│   │   ├── layouts/app.blade.php
│   │   ├── dashboard/index.blade.php
│   │   └── resume/
│   │       ├── edit.blade.php
│   │       ├── preview.blade.php
│   │       └── public.blade.php
│   └── js/
│       ├── app.js                   ← Alpine.js + CodeMirror bootstrap
│       └── pdf-export.js            ← Puppeteer script
├── routes/web.php
└── .env
```

---

## 17. Quick-Start Commands

```bash
# Install Laravel
composer create-project laravel/laravel resume-editor
cd resume-editor

# Install Breeze (auth scaffolding)
composer require laravel/breeze --dev
php artisan breeze:install blade

# Install JS deps
npm install @codemirror/view @codemirror/state @codemirror/lang-markdown codemirror alpinejs axios
npm run build

# Install Puppeteer for PDF
npm install puppeteer

# Run migrations
php artisan migrate

# Serve locally
php artisan serve
```

---

## 18. Security Checklist

- All resume/section routes check `$resume->user_id === auth()->id()` → 403 otherwise
- PDF export uses a signed URL with 5-minute expiry (Puppeteer cannot be fed raw HTML to avoid SSRF)
- `content_md` is stored raw, only rendered server-side — no XSS risk from the editor
- Photo uploads: validate MIME type + max size, store outside public root or use signed URLs
- Public slugs are UUID-based or manually chosen — never expose internal IDs
- Rate-limit PDF export endpoint (1 per 10s per user)

