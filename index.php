<?php
/**
 * index.php
 * ---------
 * Loads cv.md, splits it into sections, converts each section to HTML,
 * and injects the result into template.html.
 *
 * Edit cv.md to update your CV content — no HTML editing required.
 */

require_once __DIR__ . '/MiniMarkdown.php';

// ----------------------------------------------------------------
// 1. Load and split cv.md into top-level sections ("# SECTION")
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
            return '§§' . $index . '§§';  // placeholder — no < or > to escape
        },
        $text
    );
}

$md = parseFaShortcodes($md);

$chunks = preg_split('/^# (.+)$/m', $md, -1, PREG_SPLIT_DELIM_CAPTURE);
$sections = [];
for ($i = 1; $i < count($chunks); $i += 2) {
    $title = strtoupper(trim($chunks[$i]));
    $content = trim($chunks[$i + 1]);
    $sections[$title] = $content;
}

// ----------------------------------------------------------------
// 2. Helper: parse "## Title" blocks into timeline items
//    Used for EXPERIENCE and EDUCATION sections.
// ----------------------------------------------------------------
function parseTimelineSection(string $content): array
{
    $blocks = preg_split('/^## (.+)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $items = [];
    for ($i = 1; $i < count($blocks); $i += 2) {
        $title = trim($blocks[$i]);
        $body  = trim($blocks[$i + 1]);

        $metaParts = [];
        $bullets   = [];

        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^-\s+(.*)$/', $line, $m)) {
                $bullets[] = trim($m[1]);
            } elseif (preg_match('/^\*\*(.+)\*\*$/', $line, $m)) {
                $metaParts[] = trim($m[1]); // bold date range
            } else {
                $metaParts[] = $line; // e.g. institution / company line
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

/** Render timeline items array as the .timeline-item HTML used by the template. */
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
// 3. Build each placeholder's HTML from its Markdown section
// ----------------------------------------------------------------
$placeholders = [];

// --- HEADER (name, job title, links) ---
$headerLines = preg_split('/\r?\n/', $sections['HEADER'] ?? '');
$headerLines = array_values(array_filter(array_map('trim', $headerLines), fn($l) => $l !== ''));

$placeholders['FULL_NAME'] = MiniMarkdown::inline($headerLines[0] ?? '');
$placeholders['JOB_TITLE'] = MiniMarkdown::inline($headerLines[1] ?? '');

$linksHtml = '';
foreach (array_slice($headerLines, 2) as $line) {
    // Expected format: "Label: text | https://url"
    if (preg_match('/^(.+?):\s*(.+?)(?:\s*\|\s*(\S+))?$/', $line, $m)) {
        $label = trim($m[1]);
        $text  = trim($m[2]);
        $url   = isset($m[3]) ? trim($m[3]) : $text;
        $icon  = stripos($label, 'linkedin') !== false ? 'in' : '&#127760;';
        $linksHtml .= '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank">'
                    . '<span class="icon">' . $icon . '</span> ' . MiniMarkdown::inline($text)
                    . "</a>\n";
    }
}
$placeholders['LINKS'] = $linksHtml;

// --- PROFILE ---
$placeholders['PROFILE'] = '<p>' . MiniMarkdown::inline($sections['PROFILE'] ?? '') . '</p>';

// --- CONTACT (icon per known label) ---
$contactIcons = [
    'téléphone'        => '&#128222;',
    'email'            => '&#9993;',
    'localisation'     => '&#128205;',
    'date de naissance'=> '&#128197;',
    'permis'           => '&#128663;',
];
$contactHtml = '';
foreach (preg_split('/\r?\n/', trim($sections['CONTACT'] ?? '')) as $line) {
    $line = trim($line);
    if ($line === '' || !preg_match('/^-\s+(.+?)\s*:\s*(.+)$/', $line, $m)) continue;
    $label = trim($m[1]);
    $value = trim($m[2]);
    $icon  = $contactIcons[mb_strtolower($label)] ?? '&#8226;';
    $contactHtml .= '<div class="contact-item"><span class="icon">' . $icon . '</span> '
                  . MiniMarkdown::inline($value) . "</div>\n";
}
$placeholders['CONTACT'] = $contactHtml;

// --- Simple bullet-list sections ---
$placeholders['SKILLS']         = '<ul>' . MiniMarkdown::listItems($sections['SKILLS'] ?? '') . '</ul>';
$placeholders['CERTIFICATIONS'] = '<ul class="no-bullets">' . MiniMarkdown::listItems($sections['CERTIFICATIONS'] ?? '') . '</ul>';
$placeholders['HOBBIES']        = '<ul class="no-bullets">' . MiniMarkdown::listItems($sections['HOBBIES'] ?? '') . '</ul>';

// --- LANGUAGES ("Name — Level" pairs) ---
$langHtml = '';
foreach (preg_split('/\r?\n/', trim($sections['LANGUAGES'] ?? '')) as $line) {
    $line = trim($line);
    if ($line === '' || !preg_match('/^-\s+(.+?)\s*—\s*(.+)$/u', $line, $m)) continue;
    $langHtml .= '<div class="lang-item"><span class="lang-name">' . MiniMarkdown::inline(trim($m[1]))
               . '</span><span class="lang-level">' . MiniMarkdown::inline(trim($m[2])) . "</span></div>\n";
}
$placeholders['LANGUAGES'] = $langHtml;

// --- EXPERIENCE / EDUCATION (timelines) ---
$placeholders['EXPERIENCE'] = renderTimeline(parseTimelineSection($sections['EXPERIENCE'] ?? ''));
$placeholders['EDUCATION']  = renderTimeline(parseTimelineSection($sections['EDUCATION'] ?? ''));

// ----------------------------------------------------------------
// 4. Inject placeholders into template.html and output
// ----------------------------------------------------------------
$template = file_get_contents(__DIR__ . '/template.html');

foreach ($placeholders as $key => $html) {
    $template = str_replace('{{' . $key . '}}', $html, $template);
}

echo $template;
