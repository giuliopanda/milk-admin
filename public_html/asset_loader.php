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

// Validate file extension
$allowed_extensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'xml', 'tab', 'json', 'txt', 'md', 'mp4', 'mp3', 'wav', 'avi', 'mov', 'wmv', 'flv', 'ico', 'svg', 'webm', 'zip', 'rar', '7z', 'tar', 'gz', 'bz2'];

$file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(403);
    exit('Forbidden file type');
}

// Validate file exists
if (!file_exists($full_path) || !is_readable($full_path)) {
    // Debug info (rimuovi in produzione)
    http_response_code(404);
    exit('File not found. Debug info:<br> Requested file: ' . $_SERVER['REQUEST_URI'] . '<br> Full path tried: ' . $full_path);
}


// Headers
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

// ETag intelligent cache 
$file_mtime = filemtime($full_path);
$file_size = filesize($full_path);
$etag = md5($full_path . $file_mtime . $file_size);

// Check if the client already has the cached version
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
    http_response_code(304);
    exit;
}

// Set common headers
header('Content-Type: ' . ($mime_types[$file_extension] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
header('ETag: "' . $etag . '"');

// Compression management for CSS and JS
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
    // Determine the protocol
    $protocol = 'http://';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
        $_SERVER['SERVER_PORT'] == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
    ) {
        $protocol = 'https://';
    }

    $host = $_SERVER['HTTP_HOST'];
    $requestUri = $_SERVER['REQUEST_URI'];
    
    $currentUrl = $protocol . $host . $requestUri;
    
    return $currentUrl;
}


function getFilePathFromUrl() {
    $fullUrl = getCurrentUrl();
   
    $urlPath = strtok($fullUrl, '?');
    $urlPath =  ltrim($urlPath, '/');
    $searchPath = str_replace([BASE_URL,  rtrim(MILK_DIR, '/').'//'], [rtrim(MILK_DIR, '/').'/', rtrim(MILK_DIR, '/').'/'], $urlPath);
    
   
    // Verify that the file exists
    $realPath = realpath($searchPath);
    // Security check: file must be in MILK_DIR or LOCAL_DIR
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
     $baseDir = MILK_DIR;
    $baseDir = rtrim($baseDir, '/') . '/';
    $baseDirReal = realpath($baseDir);
    
    if (!$baseDirReal) {
        return false;
    }
    
    // Check for obvious directory traversal attempts
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
            error_log("SECURITY WARNING: Directory traversal attempt detected: $path");
            return false;
        }
    }
    
    // Remove dangerous characters
    $path = str_replace(chr(0), '', $path);
    
    $resolvedPath = realpath($path);
   
    if (!$resolvedPath) {
        return false;
    }
    
    // Verify that the resolved path is inside the base directory
    if (strpos($resolvedPath, $baseDirReal) !== 0) {
        error_log("SECURITY WARNING: Path escape attempt - Resolved: $resolvedPath | Base: $baseDirReal");
        return false;
    }
    
    // Check that there are no symlinks leading out
    if (is_link($resolvedPath)) {
        $linkTarget = readlink($resolvedPath);
        if (strpos(realpath($linkTarget), $baseDirReal) !== 0) {
            error_log("SECURITY WARNING: Symlink points outside base directory");
            return false;
        }
    }
    
    return $resolvedPath;
}
