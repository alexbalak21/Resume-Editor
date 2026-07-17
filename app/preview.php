<?php
/**
 * preview.php
 * -----------
 * Standalone, chrome-free A4 view of a saved CV profile — meant to be
 * opened in its own tab and printed (Ctrl/Cmd+P -> Save as PDF).
 * Reads the profile straight from data/<profile>.json (so make sure
 * you hit "Enregistrer" in the editor first).
 */

require_once __DIR__ . '/src/Renderer.php';

function safeProfileName(string $name): ?string
{
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
        return null;
    }
    return $name;
}

$profile = safeProfileName($_GET['profile'] ?? '');
if ($profile === null) {
    http_response_code(400);
    die('Profil invalide.');
}

$path = __DIR__ . '/data/' . $profile . '.json';
if (!file_exists($path)) {
    http_response_code(404);
    die('Profil introuvable : ' . htmlspecialchars($profile));
}

$data = json_decode(file_get_contents($path), true);
if (!is_array($data)) {
    http_response_code(500);
    die('Fichier JSON invalide pour ce profil.');
}

$inner = Renderer::renderPageInner($data);
$fullName = htmlspecialchars($data['header']['fullName'] ?? 'CV', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CV - <?= $fullName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
<link rel="stylesheet" href="assets/template.css">
<style>
    #print-toolbar {
        position: fixed;
        top: 12px;
        right: 12px;
        z-index: 100;
        display: flex;
        gap: 8px;
    }
    #print-toolbar button, #print-toolbar a {
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
        border: none;
        border-radius: 6px;
        padding: 8px 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }
    #print-toolbar .print-btn { background: #2563eb; color: #fff; }
    #print-toolbar .print-btn:hover { background: #1d4ed8; }
    #print-toolbar .back-btn { background: #fff; color: #1f2937; border: 1px solid #d1d5db !important; }
    #print-toolbar .back-btn:hover { background: #f3f4f6; }

    @media print {
        #print-toolbar { display: none !important; }
    }
</style>
</head>
<body>

<div id="print-toolbar">
    <a class="back-btn" href="editor.php"><i class="fa-solid fa-arrow-left"></i> Retour à l'éditeur</a>
    <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer / PDF</button>
</div>

<?= $inner ?>

</body>
</html>
