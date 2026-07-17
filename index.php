<?php
/**
 * index.php
 * ---------
 * Loads cv.md, splits it into sections, converts each section to HTML,
 * and injects the result into the appropriate language template.
 *
 * Multilingual support: append ?lang=fr (default) or ?lang=en to the URL.
 *
 * Section display titles are defined IN cv.md itself, right after the # heading:
 *
 *   # PROFILE
 *   title.fr: Profil
 *   title.en: Profile
 *
 * The "title.XX:" lines are stripped from the section body before rendering.
 * If no title.XX: line exists for the current lang, the locale fallback in
 * $locale[lang]['sectionLabels'] is used. If that's also absent, the raw key is shown.
 *
 * Font Awesome icons can be added to titles:
 *   title.fr: [fa:solid:user] Profil
 */

require_once __DIR__ . '/MiniMarkdown.php';

// ----------------------------------------------------------------
// 0. Detect language
// ----------------------------------------------------------------
$lang = strtolower(trim($_GET['lang'] ?? 'fr'));
if (!in_array($lang, ['fr', 'en'], true)) {
    $lang = 'fr';
}

// ----------------------------------------------------------------
// 1. Locale configuration
//    sectionLabels = fallback only (used when cv.md has no title.XX: line)
// ----------------------------------------------------------------
$locale = [
    'fr' => [
        'sectionLabels' => [
            'CONTACT'        => 'Contact',
            'SKILLS'         => 'Compétences',
            'CERTIFICATIONS' => 'Certifications',
            'LANGUAGES'      => 'Langues',
            'HOBBIES'        => 'Intérêts',
            'EXPERIENCE'     => 'Expériences Professionnelles',
            'EDUCATION'      => 'Formations',
            'PROFILE'        => 'Profil',
        ],
        'contactIcons' => [
            'téléphone'         => '<i class="fa-solid fa-phone"></i>',
            'email'             => '<i class="fa-solid fa-envelope"></i>',
            'localisation'      => '<i class="fa-solid fa-location-dot"></i>',
            'date de naissance' => '<i class="fa-solid fa-cake-candles"></i>',
            'permis'            => '<i class="fa-solid fa-car"></i>',
            'phone'             => '<i class="fa-solid fa-phone"></i>',
            'location'          => '<i class="fa-solid fa-location-dot"></i>',
            'birthday'          => '<i class="fa-solid fa-cake-candles"></i>',
            'license'           => '<i class="fa-solid fa-car"></i>',
            'driving'           => '<i class="fa-solid fa-car"></i>',
        ],
        'template' => 'template_fr.html',
    ],
    'en' => [
        'sectionLabels' => [
            'CONTACT'        => 'Contact',
            'SKILLS'         => 'Skills',
            'CERTIFICATIONS' => 'Certifications',
            'LANGUAGES'      => 'Languages',
            'HOBBIES'        => 'Interests',
            'EXPERIENCE'     => 'Work Experience',
            'EDUCATION'      => 'Education',
            'PROFILE'        => 'Profile',
        ],
        'contactIcons' => [
            'phone'             => '<i class="fa-solid fa-phone"></i>',
            'email'             => '<i class="fa-solid fa-envelope"></i>',
            'location'          => '<i class="fa-solid fa-location-dot"></i>',
            'birthday'          => '<i class="fa-solid fa-cake-candles"></i>',
            'license'           => '<i class="fa-solid fa-car"></i>',
            'driving'           => '<i class="fa-solid fa-car"></i>',
            'téléphone'         => '<i class="fa-solid fa-phone"></i>',
            'localisation'      => '<i class="fa-solid fa-location-dot"></i>',
            'date de naissance' => '<i class="fa-solid fa-cake-candles"></i>',
            'permis'            => '<i class="fa-solid fa-car"></i>',
        ],
        'template' => 'template.html',
    ],
];

$sectionLabels = $locale[$lang]['sectionLabels'];
$contactIcons  = $locale[$lang]['contactIcons'];
$templateFile  = $locale[$lang]['template'];

// ----------------------------------------------------------------
// 2. Load and split cv.md into top-level sections ("# SECTION_KEY")
// ----------------------------------------------------------------
$mdPath = __DIR__ . '/cv.md';
if (!file_exists($mdPath)) {
    die('cv.md introuvable. Place le fichier à côté de index.php.');
}
$md = file_get_contents($mdPath);

// Global slot store: maps index → real HTML kept safe from htmlspecialchars()
$GLOBALS['__mm_slots'] = [];

function parseFaShortcodes(string $text): string
{
    return preg_replace_callback(
        '/\[fa:(brands|solid|regular):([a-z0-9\-]+)\]/i',
        function ($m) {
            $html  = '<i class="fa-' . $m[1] . ' fa-' . $m[2] . '"></i>';
            $index = count($GLOBALS['__mm_slots']);
            $GLOBALS['__mm_slots'][$index] = $html;
            return '§§' . $index . '§§';
        },
        $text
    );
}

// Split on "# KEY" — the key is the section identifier (all-caps)
$chunks   = preg_split('/^# (.+)$/m', $md, -1, PREG_SPLIT_DELIM_CAPTURE);
$sections      = [];   // KEY => raw body markdown (title.XX: lines included, stripped later)
$sectionKeys   = [];   // ordered list of keys

for ($i = 1; $i < count($chunks); $i += 2) {
    // The key is the heading text stripped of any [fa:...] and uppercased
    $key = strtoupper(trim(preg_replace('/\[fa:[^\]]+\]/i', '', trim($chunks[$i]))));
    $sectionKeys[]  = $key;
    $sections[$key] = trim($chunks[$i + 1]);
}

/**
 * Extract a single "title: ..." line from a section body.
 * Returns [title|null, cleaned_body].
 */
function extractTitleLine(string $body): array
{
    $title   = null;
    $cleaned = [];
    foreach (preg_split('/\r?\n/', $body) as $line) {
        if ($title === null && preg_match('/^title\s*:\s*(.+)$/i', $line, $m)) {
            $title = trim($m[1]);
        } else {
            $cleaned[] = $line;
        }
    }
    return [$title, trim(implode("\n", $cleaned))];
}

// Parse title line out of every section body, store clean bodies back
$sectionInlineTitles = [];   // KEY => string|null
foreach ($sections as $key => $body) {
    [$title, $cleanBody]        = extractTitleLine($body);
    $sectionInlineTitles[$key]  = $title;
    $sections[$key]             = $cleanBody;
}

// Now apply FA shortcodes to the cleaned bodies
foreach ($sections as $k => $v) {
    $sections[$k] = parseFaShortcodes($v);
}

// ----------------------------------------------------------------
// 3. Resolve display title
//    Priority: cv.md "title:" line > locale fallback > raw key
// ----------------------------------------------------------------
function resolveTitle(string $key, string $lang, array $inlineTitles, array $fallbacks): string
{
    // 1. Inline title from cv.md
    $raw = $inlineTitles[$key] ?? null;

    // 2. Fallback from locale config
    if ($raw === null) {
        $raw = $fallbacks[$key] ?? $key;
    }

    // Parse FA shortcodes in the title string
    $raw = parseFaShortcodes($raw);
    return MiniMarkdown::inline($raw);
}

// ----------------------------------------------------------------
// 4. Helper: parse "## Title" blocks into timeline items
// ----------------------------------------------------------------
function parseTimelineSection(string $content): array
{
    $blocks = preg_split('/^## (.+)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $items  = [];
    for ($i = 1; $i < count($blocks); $i += 2) {
        $title     = trim($blocks[$i]);
        $body      = trim($blocks[$i + 1]);
        $metaParts = [];
        $bullets   = [];

        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^-\s+(.*)$/', $line, $m)) {
                $bullets[] = trim($m[1]);
            } elseif (preg_match('/^\*\*(.+)\*\*$/', $line, $m)) {
                $metaParts[] = trim($m[1]);
            } else {
                $metaParts[] = $line;
            }
        }

        $items[] = [
            'title'   => $title,
            'meta'    => implode(' — ', $metaParts),
            'bullets' => $bullets,
        ];
    }
    return $items;
}

function renderTimeline(array $items): string
{
    $html = '';
    foreach ($items as $item) {
        $html .= "<div class=\"timeline-item\">\n";
        $html .= '  <h4 class="job-title">' . MiniMarkdown::inline($item['title']) . "</h4>\n";
        $html .= '  <p class="job-meta">' . MiniMarkdown::inline($item['meta']) . "</p>\n";
        if (!empty($item['bullets'])) {
            $html .= "  <ul>\n";
            foreach ($item['bullets'] as $bullet) {
                $html .= '    <li>' . MiniMarkdown::inline($bullet) . "</li>\n";
            }
            $html .= "  </ul>\n";
        }
        $html .= "</div>\n";
    }
    return $html;
}

// ----------------------------------------------------------------
// 5. Build placeholders
// ----------------------------------------------------------------
$placeholders = [];

// --- SECTION TITLES (from cv.md inline titles or locale fallback) ---
$knownSections = array_keys($sectionLabels);
foreach ($knownSections as $sec) {
    $placeholders[$sec . '_TITLE'] = resolveTitle($sec, $lang, $sectionInlineTitles, $sectionLabels);
}

// --- HEADER ---
$headerLines = preg_split('/\r?\n/', $sections['HEADER'] ?? '');
$headerLines = array_values(array_filter(array_map('trim', $headerLines), fn($l) => $l !== ''));

$placeholders['FULL_NAME'] = MiniMarkdown::inline($headerLines[0] ?? '');
$placeholders['JOB_TITLE'] = MiniMarkdown::inline($headerLines[1] ?? '');

$linksHtml = '';
foreach (array_slice($headerLines, 2) as $line) {
    if (preg_match('/^(.+?):\s*(.+?)(?:\s*\|\s*(\S+))?$/', $line, $m)) {
        $lbl  = trim($m[1]);
        $text = trim($m[2]);
        $url  = isset($m[3]) ? trim($m[3]) : $text;
        $linkIcons = [
            'linkedin'  => '<i class="fa-brands fa-linkedin"></i>',
            'github'    => '<i class="fa-brands fa-github"></i>',
            'twitter'   => '<i class="fa-brands fa-x-twitter"></i>',
            'site web'  => '<i class="fa-solid fa-globe"></i>',
            'website'   => '<i class="fa-solid fa-globe"></i>',
            'portfolio' => '<i class="fa-solid fa-globe"></i>',
        ];
        $icon = '<i class="fa-solid fa-globe"></i>';
        foreach ($linkIcons as $keyword => $faIcon) {
            if (stripos($lbl, $keyword) !== false) { $icon = $faIcon; break; }
        }
        $linksHtml .= '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank">'
                    . '<span class="icon">' . $icon . '</span> ' . MiniMarkdown::inline($text)
                    . "</a>\n";
    }
}
$placeholders['LINKS'] = $linksHtml;

// --- PROFILE ---
$placeholders['PROFILE'] = '<p>' . MiniMarkdown::inline($sections['PROFILE'] ?? '') . '</p>';

// --- CONTACT ---
$contactHtml = '';
foreach (preg_split('/\r?\n/', trim($sections['CONTACT'] ?? '')) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    if (!preg_match('/^-\s+(.+?)\s*:\s*(.+?)(?:\s*\|\s*((?:tel|mailto):[^\s]+))?$/u', $line, $m)) continue;
    $lbl     = trim($m[1]);
    $display = trim($m[2]);
    $href    = isset($m[3]) ? trim($m[3]) : null;
    $icon    = $contactIcons[mb_strtolower($lbl)] ?? '<i class="fa-solid fa-circle-dot"></i>';
    $text    = MiniMarkdown::inline($display);
    $content = $href
        ? '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . $text . '</a>'
        : $text;
    $contactHtml .= '<div class="contact-item"><span class="icon">' . $icon . '</span> '
                  . $content . "</div>\n";
}
$placeholders['CONTACT'] = $contactHtml;

// --- Simple bullet-list sections ---
$placeholders['SKILLS']         = '<ul>' . MiniMarkdown::listItems($sections['SKILLS'] ?? '') . '</ul>';
$placeholders['CERTIFICATIONS'] = '<ul class="no-bullets">' . MiniMarkdown::listItems($sections['CERTIFICATIONS'] ?? '') . '</ul>';
$placeholders['HOBBIES']        = '<ul class="no-bullets">' . MiniMarkdown::listItems($sections['HOBBIES'] ?? '') . '</ul>';

// --- LANGUAGES ---
$langHtml = '';
foreach (preg_split('/\r?\n/', trim($sections['LANGUAGES'] ?? '')) as $line) {
    $line = trim($line);
    if ($line === '' || !preg_match('/^-\s+(.+?)\s*—\s*(.+)$/u', $line, $m)) continue;
    $langHtml .= '<div class="lang-item"><span class="lang-name">' . MiniMarkdown::inline(trim($m[1]))
               . '</span><span class="lang-level">' . MiniMarkdown::inline(trim($m[2])) . "</span></div>\n";
}
$placeholders['LANGUAGES'] = $langHtml;

// --- EXPERIENCE / EDUCATION ---
$placeholders['EXPERIENCE'] = renderTimeline(parseTimelineSection($sections['EXPERIENCE'] ?? ''));
$placeholders['EDUCATION']  = renderTimeline(parseTimelineSection($sections['EDUCATION'] ?? ''));

// ----------------------------------------------------------------
// 6. Inject into template and output
// ----------------------------------------------------------------
$templatePath = __DIR__ . '/' . $templateFile;
if (!file_exists($templatePath)) {
    die('Template introuvable : ' . htmlspecialchars($templateFile));
}
$template = file_get_contents($templatePath);

foreach ($placeholders as $key => $html) {
    $template = str_replace('{{' . $key . '}}', $html, $template);
}

echo $template;
