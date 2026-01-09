<?php
require 'milkadmin.php';

// Ottieni il percorso richiesto
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host;
$requested_file = $_SERVER['REQUEST_URI'];

$requested_file = parse_url($requested_file);

$full_path =  getFilePathFromUrl();
if (!validateSecurePath($full_path)) {
    http_response_code(403);
    exit('Access denied');
}

// Validazione estensioni permesse
$allowed_extensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'xml', 'tab', 'json', 'txt', 'md', 'mp4', 'mp3', 'wav', 'avi', 'mov', 'wmv', 'flv', 'ico', 'svg', 'webm', 'zip', 'rar', '7z', 'tar', 'gz', 'bz2'];

$file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(403);
    exit('Forbidden file type');
}

// Verifica che il file esista
if (!file_exists($full_path) || !is_readable($full_path)) {
    // Debug info (rimuovi in produzione)
    http_response_code(404);
    exit('File not found. Debug info:<br> Requested file: ' . $_SERVER['REQUEST_URI'] . '<br> Full path tried: ' . $full_path);
}


// Headers appropriati per ogni tipo di file
$mime_types = [
    'css' => 'text/css',
    'js' => 'application/javascript',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'webp' => 'image/webp'
];

// ETag per cache intelligente
$file_mtime = filemtime($full_path);
$file_size = filesize($full_path);
$etag = md5($full_path . $file_mtime . $file_size);

// Controlla se il client ha già la versione cached
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
    http_response_code(304);
    exit;
}

// Imposta headers comuni
header('Content-Type: ' . ($mime_types[$file_extension] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
header('ETag: "' . $etag . '"');

// Gestione compressione per CSS e JS
if (in_array($file_extension, ['css', 'js'])) {
    $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    $use_gzip = strpos($accept_encoding, 'gzip') !== false;
    
    $content = file_get_contents($full_path);
    
    
    if ($use_gzip && extension_loaded('zlib')) {
        $compressed_content = gzencode($content, 6);
        if ($compressed_content !== false) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . strlen($compressed_content));
            echo $compressed_content;
        } else {
            header('Content-Length: ' . strlen($content));
            echo $content;
        }
    } else {
        header('Content-Length: ' . strlen($content));
        echo $content;
    }
} else {
    header('Content-Length: ' . $file_size);
    readfile($full_path);
}




function getCurrentUrl() {
    // Determina il protocollo
    $protocol = 'http://';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
        $_SERVER['SERVER_PORT'] == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
    ) {
        $protocol = 'https://';
    }
    
    // Ottieni l'host
    $host = $_SERVER['HTTP_HOST'];
    
    // Ottieni il percorso completo con query string
    $requestUri = $_SERVER['REQUEST_URI'];
    
    // Costruisci l'URL completo
    $currentUrl = $protocol . $host . $requestUri;
    
    return $currentUrl;
}


function getFilePathFromUrl() {
    $fullUrl = getCurrentUrl();
   
    $urlPath = strtok($fullUrl, '?');
    // Pulisci il path (rimuovi doppi slash iniziali)
    $urlPath =  ltrim($urlPath, '/');
    // Costruisci il percorso fisico del file
    $searchPath = str_replace([BASE_URL,  rtrim(MILK_DIR, '/').'//'], [rtrim(MILK_DIR, '/').'/', rtrim(MILK_DIR, '/').'/'], $urlPath);
    
   
    // Verifica che il file esista
    $realPath = realpath($searchPath);
    // Verifica sicurezza: il file deve essere dentro MILK_DIR
    if (strpos($realPath, realpath(MILK_DIR)) === 0) {
        return $realPath;
    } else {
        $searchPath2 = str_replace([BASE_URL,  rtrim(LOCAL_DIR, '/').'//'], [rtrim(LOCAL_DIR, '/').'/', rtrim(LOCAL_DIR, '/').'/'], $urlPath);
        $realPath2 = realpath($searchPath2);
        if (strpos($realPath2, realpath(LOCAL_DIR)) === 0) {
            return $realPath2;
        }
    }
    
    return false;
}


function validateSecurePath($path) {
    // Usa MILK_DIR come base di default
    $baseDir = MILK_DIR;
    $baseDir2 = LOCAL_DIR;
    // 1. Normalizza i percorsi
    $baseDir = rtrim($baseDir, '/') . '/';
    $baseDirReal = realpath($baseDir);
    $baseDir2 = rtrim($baseDir2, '/') . '/';
    $baseDirReal2 = realpath($baseDir2);

    if (!$baseDirReal && !$baseDirReal2) {
        return false;
    }

    // SECURITY FIX: Add trailing slash to prevent prefix collision attack
    $baseDirReal = rtrim($baseDirReal, '/') . '/';

    // 2. Check for directory traversal not necessary !!
    $dangerousPatterns = [
        '../',
        '..\\',
        '/..',
        '\\..',
        '%2e%2e%2f',  // URL encoded ../
        '%2e%2e/',
        '..%2f',
        '%2e%2e\\',
        '..%5c',
        '%2e%2e%5c',
        '..;',
        '..%00',      // null byte
        '..%0d%0a',   // CRLF
    ];

    $pathLower = strtolower($path);
    foreach ($dangerousPatterns as $pattern) {
        if (stripos($pathLower, $pattern) !== false) {
           // error_log("SECURITY WARNING: Directory traversal attempt detected: $path");
            return false;
        }
    }

    // null byte
    if (strpos($pathLower, chr(0)) !== false) {
        return false;
    }
  
    $resolvedPath = realpath($path);

    if (!$resolvedPath) {
        return false;
    }

    // SECURITY FIX: Aggiungi trailing slash al path risolto per il confronto
    $resolvedPathWithSlash = rtrim($resolvedPath, '/') . '/';

    if (strpos($resolvedPathWithSlash, $baseDirReal) !== 0 && strpos($resolvedPathWithSlash, $baseDirReal2) !== 0) {
       // error_log("SECURITY WARNING: Path escape attempt - Resolved: $resolvedPath | Base: $baseDirReal");
        return false;
    }
    return $resolvedPath;
}
