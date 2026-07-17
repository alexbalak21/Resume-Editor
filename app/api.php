<?php
/**
 * api.php — small JSON API backing the editor.
 *
 * Actions (all via ?action=):
 *   list             GET   -> {"profiles": ["technician", "developer", "data_scientist"]}
 *   load             GET   -> ?profile=developer  -> full JSON data for that profile
 *   save             POST  -> body: {"profile": "developer", "data": {...}} -> writes data/<profile>.json
 *   render           POST  -> body: {"data": {...}} -> {"html": "<div id=page>...</div>"}
 */

require_once __DIR__ . '/src/Renderer.php';

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/data';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function safeProfileName(string $name): ?string
{
    // Only allow simple filenames: letters, numbers, underscore, dash.
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
        return null;
    }
    return $name;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

switch ($action) {
    case 'list': {
        $files = glob($dataDir . '/*.json');
        $profiles = array_map(fn($f) => basename($f, '.json'), $files);
        sort($profiles);
        echo json_encode(['profiles' => $profiles]);
        break;
    }

    case 'load': {
        $profile = safeProfileName($_GET['profile'] ?? '');
        if ($profile === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid profile name']);
            break;
        }
        $path = $dataDir . '/' . $profile . '.json';
        if (!file_exists($path)) {
            http_response_code(404);
            echo json_encode(['error' => 'Profile not found']);
            break;
        }
        $data = json_decode(file_get_contents($path), true);
        echo json_encode(['profile' => $profile, 'data' => $data]);
        break;
    }

    case 'save': {
        $body = readJsonBody();
        $profile = safeProfileName($body['profile'] ?? '');
        $data = $body['data'] ?? null;
        if ($profile === null || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            break;
        }
        $path = $dataDir . '/' . $profile . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($path, $json) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not write file']);
            break;
        }
        echo json_encode(['ok' => true]);
        break;
    }

    case 'render': {
        $body = readJsonBody();
        $data = $body['data'] ?? null;
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            break;
        }
        echo json_encode(['html' => Renderer::renderPageInner($data)]);
        break;
    }

    case 'create': {
        // Create a brand new (blank-ish) profile file.
        $body = readJsonBody();
        $profile = safeProfileName($body['profile'] ?? '');
        if ($profile === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid profile name']);
            break;
        }
        $path = $dataDir . '/' . $profile . '.json';
        if (file_exists($path)) {
            http_response_code(409);
            echo json_encode(['error' => 'Profile already exists']);
            break;
        }
        $blank = [
            'lang' => 'fr',
            'header' => ['fullName' => '', 'jobTitle' => '', 'photo' => '', 'links' => []],
            'profile' => ['title' => 'Profil', 'text' => ''],
            'contact' => ['title' => 'Contact', 'items' => []],
            'skills' => ['title' => 'Compétences', 'items' => []],
            'certifications' => ['title' => 'Certifications', 'items' => []],
            'languages' => ['title' => 'Langues', 'items' => []],
            'hobbies' => ['title' => 'Intérêts', 'items' => []],
            'experience' => ['title' => 'Expériences Professionnelles', 'items' => []],
            'education' => ['title' => 'Formations', 'items' => []],
        ];
        file_put_contents($path, json_encode($blank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['ok' => true, 'profile' => $profile, 'data' => $blank]);
        break;
    }

    case 'upload_photo': {
        $profile = safeProfileName($_POST['profile'] ?? '');
        if ($profile === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid profile name']);
            break;
        }
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Aucun fichier reçu']);
            break;
        }

        $maxBytes = 1 * 1024 * 1024; // 1 MB max
        $file = $_FILES['photo'];

        if ($file['size'] > $maxBytes) {
            http_response_code(413);
            echo json_encode(['error' => 'Fichier trop volumineux (1 Mo max)']);
            break;
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            http_response_code(415);
            echo json_encode(['error' => 'Format non supporté (jpg, png, webp uniquement)']);
            break;
        }

        $storageDir = __DIR__ . '/storage/photos';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        // Remove any previous photo(s) for this profile before saving the new one.
        foreach (glob($storageDir . '/' . $profile . '.*') as $old) {
            @unlink($old);
        }

        $ext = $allowed[$mime];
        $filename = $profile . '.' . $ext;
        $destPath = $storageDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Échec de l\'enregistrement du fichier']);
            break;
        }

        // Relative path usable directly as an <img src> from editor.php / preview.
        $publicPath = 'storage/photos/' . $filename . '?v=' . time();
        echo json_encode(['ok' => true, 'path' => $publicPath]);
        break;
    }

    case 'delete_photo': {
        $body = readJsonBody();
        $profile = safeProfileName($body['profile'] ?? '');
        if ($profile === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid profile name']);
            break;
        }
        $storageDir = __DIR__ . '/storage/photos';
        foreach (glob($storageDir . '/' . $profile . '.*') as $old) {
            @unlink($old);
        }
        echo json_encode(['ok' => true]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
