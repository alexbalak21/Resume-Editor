<?php

/**
 * Renderer
 * --------
 * Turns the structured CV data array (loaded from a JSON profile file)
 * into the HTML markup used by the preview pane / final resume page.
 *
 * Supports the same lightweight inline syntax the old MiniMarkdown used:
 *   **bold**              -> <strong>
 *   [fa:solid:xxx]         -> Font Awesome <i> icon
 *   [fa:brands:xxx]        -> Font Awesome <i> icon
 */
class Renderer
{
    /** Inline text: escapes HTML, restores FA icons, applies **bold**. */
    public static function inline(string $text): string
    {
        $slots = [];
        $text = preg_replace_callback(
            '/\[fa:(brands|solid|regular):([a-z0-9\-]+)\]/i',
            function ($m) use (&$slots) {
                $html = '<i class="fa-' . $m[1] . ' fa-' . $m[2] . '"></i>';
                $idx = count($slots);
                $slots[$idx] = $html;
                return '§§' . $idx . '§§';
            },
            $text
        );

        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $text = preg_replace_callback('/§§(\d+)§§/', function ($m) use ($slots) {
            return $slots[$m[1]] ?? $m[0];
        }, $text);

        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        return $text;
    }

    private static function contactIcon(string $label): string
    {
        $map = [
            'téléphone' => 'fa-phone',
            'phone' => 'fa-phone',
            'email' => 'fa-envelope',
            'localisation' => 'fa-location-dot',
            'location' => 'fa-location-dot',
            'date de naissance' => 'fa-cake-candles',
            'birthday' => 'fa-cake-candles',
            'permis' => 'fa-car',
            'license' => 'fa-car',
            'driving' => 'fa-car',
        ];
        $key = mb_strtolower(trim($label));
        $icon = $map[$key] ?? 'fa-circle-dot';
        return '<i class="fa-solid ' . $icon . '"></i>';
    }

    private static function linkIcon(string $label): string
    {
        $map = [
            'linkedin' => 'fa-brands fa-linkedin',
            'github' => 'fa-brands fa-github',
            'twitter' => 'fa-brands fa-x-twitter',
            'site web' => 'fa-solid fa-globe',
            'website' => 'fa-solid fa-globe',
            'portfolio' => 'fa-solid fa-globe',
        ];
        foreach ($map as $needle => $class) {
            if (stripos($label, $needle) !== false) {
                return '<i class="' . $class . '"></i>';
            }
        }
        return '<i class="fa-solid fa-globe"></i>';
    }

    private static function renderTimeline(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $html .= "<div class=\"timeline-item\">\n";
            $html .= '  <h4 class="job-title">' . self::inline($item['title'] ?? '') . "</h4>\n";
            $html .= '  <p class="job-meta">' . self::inline($item['meta'] ?? '') . "</p>\n";
            $bullets = $item['bullets'] ?? [];
            if (!empty($bullets)) {
                $html .= "  <ul>\n";
                foreach ($bullets as $bullet) {
                    $html .= '    <li>' . self::inline($bullet) . "</li>\n";
                }
                $html .= "  </ul>\n";
            }
            $html .= "</div>\n";
        }
        return $html;
    }

    /** Build the full set of {{PLACEHOLDER}} => html values for a data array. */
    public static function buildPlaceholders(array $d): array
    {
        $p = [];

        $header = $d['header'] ?? [];
        $p['FULL_NAME'] = self::inline($header['fullName'] ?? '');
        $p['JOB_TITLE'] = self::inline($header['jobTitle'] ?? '');
        $photo = trim($header['photo'] ?? '');
        $p['PHOTO_SRC'] = htmlspecialchars($photo, ENT_QUOTES);
        $p['PHOTO_DISPLAY'] = $photo !== '' ? 'flex' : 'none';

        $linksHtml = '';
        foreach ($header['links'] ?? [] as $link) {
            $url = $link['url'] ?? ($link['text'] ?? '');
            $linksHtml .= '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank">'
                . '<span class="icon">' . self::linkIcon($link['label'] ?? '') . '</span> '
                . self::inline($link['text'] ?? '') . "</a>\n";
        }
        $p['LINKS'] = $linksHtml;

        $p['PROFILE_TITLE'] = self::inline($d['profile']['title'] ?? 'Profil');
        $p['PROFILE'] = '<p>' . self::inline($d['profile']['text'] ?? '') . '</p>';

        $p['CONTACT_TITLE'] = self::inline($d['contact']['title'] ?? 'Contact');
        $contactHtml = '';
        foreach ($d['contact']['items'] ?? [] as $item) {
            $text = self::inline($item['display'] ?? '');
            $href = trim($item['href'] ?? '');
            $content = $href !== ''
                ? '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . $text . '</a>'
                : $text;
            $contactHtml .= '<div class="contact-item"><span class="icon">'
                . self::contactIcon($item['label'] ?? '') . '</span> ' . $content . "</div>\n";
        }
        $p['CONTACT'] = $contactHtml;

        $p['SKILLS_TITLE'] = self::inline($d['skills']['title'] ?? 'Compétences');
        $skillsHtml = '';
        foreach ($d['skills']['items'] ?? [] as $item) {
            $skillsHtml .= '<li>' . self::inline($item) . "</li>\n";
        }
        $p['SKILLS'] = '<ul>' . $skillsHtml . '</ul>';

        $p['CERTIFICATIONS_TITLE'] = self::inline($d['certifications']['title'] ?? 'Certifications');
        $certHtml = '';
        foreach ($d['certifications']['items'] ?? [] as $item) {
            $certHtml .= '<li>' . self::inline($item) . "</li>\n";
        }
        $p['CERTIFICATIONS'] = '<ul class="no-bullets">' . $certHtml . '</ul>';

        $p['HOBBIES_TITLE'] = self::inline($d['hobbies']['title'] ?? 'Intérêts');
        $hobbiesHtml = '';
        foreach ($d['hobbies']['items'] ?? [] as $item) {
            $hobbiesHtml .= '<li>' . self::inline($item) . "</li>\n";
        }
        $p['HOBBIES'] = '<ul class="no-bullets">' . $hobbiesHtml . '</ul>';

        $p['LANGUAGES_TITLE'] = self::inline($d['languages']['title'] ?? 'Langues');
        $langHtml = '';
        foreach ($d['languages']['items'] ?? [] as $item) {
            $langHtml .= '<div class="lang-item"><span class="lang-name">' . self::inline($item['name'] ?? '')
                . '</span><span class="lang-level">' . self::inline($item['level'] ?? '') . "</span></div>\n";
        }
        $p['LANGUAGES'] = $langHtml;

        $p['EXPERIENCE_TITLE'] = self::inline($d['experience']['title'] ?? 'Expériences Professionnelles');
        $p['EXPERIENCE'] = self::renderTimeline($d['experience']['items'] ?? []);

        $p['EDUCATION_TITLE'] = self::inline($d['education']['title'] ?? 'Formations');
        $p['EDUCATION'] = self::renderTimeline($d['education']['items'] ?? []);

        return $p;
    }

    /** Render the inner #page HTML (used for the live preview iframe body). */
    public static function renderPageInner(array $d): string
    {
        $p = self::buildPlaceholders($d);
        $tpl = <<<'HTML'
<div id="page">
    <aside>
        <div id="photo-container" style="display:{{PHOTO_DISPLAY}}">
            <img src="{{PHOTO_SRC}}" alt="Profile photo" id="photo">
        </div>

        <div class="aside-block" id="contact">
            <h3 class="aside-title">{{CONTACT_TITLE}}</h3>
            <div class="contact-grid">{{CONTACT}}</div>
        </div>
        <div class="aside-block" id="skills">
            <h3 class="aside-title">{{SKILLS_TITLE}}</h3>
            {{SKILLS}}
        </div>
        <div class="aside-block" id="certifications">
            <h3 class="aside-title">{{CERTIFICATIONS_TITLE}}</h3>
            {{CERTIFICATIONS}}
        </div>
        <div class="aside-block" id="languages">
            <h3 class="aside-title">{{LANGUAGES_TITLE}}</h3>
            {{LANGUAGES}}
        </div>
        <div class="aside-block" id="hobbies">
            <h3 class="aside-title">{{HOBBIES_TITLE}}</h3>
            {{HOBBIES}}
        </div>
    </aside>
    <main>
        <header id="main-header">
            <div id="header-text">
                <h1 id="name">{{FULL_NAME}}</h1>
                <h2 id="job">{{JOB_TITLE}}</h2>
                <div id="links">{{LINKS}}</div>
            </div>
            <div id="header-icon">&lt;/&gt;</div>
        </header>
        <section id="profile">
            <h3 class="section-title">{{PROFILE_TITLE}}</h3>
            {{PROFILE}}
        </section>
        <section id="experience">
            <h3 class="section-title">{{EXPERIENCE_TITLE}}</h3>
            <div class="timeline">{{EXPERIENCE}}</div>
        </section>
        <section id="education">
            <h3 class="section-title">{{EDUCATION_TITLE}}</h3>
            <div class="timeline">{{EDUCATION}}</div>
        </section>
    </main>
</div>
HTML;
        foreach ($p as $key => $html) {
            $tpl = str_replace('{{' . $key . '}}', $html, $tpl);
        }
        return $tpl;
    }
}
