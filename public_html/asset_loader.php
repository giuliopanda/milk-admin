<?php
/**
 * Asset loader fallback.
 *
 * New behavior:
 * - In production (`$conf['environment'] = 'production'`), theme static files are copied to
 *   `public_html/Theme` and CSS/JS bundles are generated there.
 * - Because `.htaccess` serves existing files directly (`RewriteCond %{REQUEST_FILENAME} !-f`),
 *   most Theme asset requests no longer hit this PHP script.
 * - This loader is mainly used as a fallback when the requested asset does not exist physically
 *   under `public_html` (typical during development or before first production sync/build).
 */
require 'milkadmin.php';

$full_path = getFilePathFromUrl();
$validated_path = validateSecurePath($full_path);
if ($validated_path === false) {
    http_response_code(403);
    exit('Access denied');
}
$full_path = $validated_path;

// Validate allowed file extensions
$allowed_extensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'xml', 'tab', 'json', 'txt', 'md', 'mp4', 'mp3', 'wav', 'avi', 'mov', 'wmv', 'flv', 'ico', 'svg', 'webm', 'zip', 'rar', '7z', 'tar', 'gz', 'bz2'];

$file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions, true)) {
    http_response_code(403);
    exit('Forbidden file type');
}

// Ensure the file exists and is readable
if (!file_exists($full_path) || !is_readable($full_path)) {
    // Debug info (remove in production)
    http_response_code(404);
    exit('File not found. Debug info:<br> Requested file: ' . $_SERVER['REQUEST_URI'] . '<br> Full path tried: ' . $full_path);
}


// Proper headers for each file type
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

// ETag for smart caching
$file_mtime = filemtime($full_path);
$file_size = filesize($full_path);
$etag = md5($full_path . $file_mtime . $file_size);

// Check whether the client already has the cached version
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
    http_response_code(304);
    exit;
}

// Set common headers
header('Content-Type: ' . ($mime_types[$file_extension] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
header('ETag: "' . $etag . '"');

// Compression handling for CSS and JS
if (in_array($file_extension, ['css', 'js'], true)) {
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




function getCurrentUrl(): string
{
    // Determine protocol
    $protocol = 'http://';
    $serverPort = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 0;
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $serverPort === 443
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) {
        $protocol = 'https://';
    }
    
    // Get host
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    
    // Get full path with query string
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($host === '' || $requestUri === '') {
        return '';
    }
    
    // Build full URL
    $currentUrl = $protocol . $host . $requestUri;
    
    return $currentUrl;
}


function getFilePathFromUrl(): string|false
{
    $fullUrl = getCurrentUrl();
    if ($fullUrl === '') {
        return false;
    }
   
    $urlPath = (string) strtok($fullUrl, '?');
    // Normalize path (remove leading duplicated slashes)
    $urlPath = ltrim($urlPath, '/');
    $baseUrl = defined('BASE_URL') ? (string) constant('BASE_URL') : '';

    // Build physical file path
    $searchPath = str_replace(
        [$baseUrl, rtrim(MILK_DIR, '/') . '//'],
        [rtrim(MILK_DIR, '/') . '/', rtrim(MILK_DIR, '/') . '/'],
        $urlPath
    );
    
   
    // Resolve candidate file path
    $realPath = realpath($searchPath);
    $milkRoot = realpath(MILK_DIR);
    // Security check: file must be inside MILK_DIR
    if (is_string($realPath) && is_string($milkRoot) && strpos($realPath, $milkRoot) === 0) {
        return $realPath;
    } else {
        $searchPath2 = str_replace(
            [$baseUrl, rtrim(LOCAL_DIR, '/') . '//'],
            [rtrim(LOCAL_DIR, '/') . '/', rtrim(LOCAL_DIR, '/') . '/'],
            $urlPath
        );
        $realPath2 = realpath($searchPath2);
        $localRoot = realpath(LOCAL_DIR);
        if (is_string($realPath2) && is_string($localRoot) && strpos($realPath2, $localRoot) === 0) {
            return $realPath2;
        }
    }
    
    return false;
}


function validateSecurePath(string|false $path): string|false
{
    if (!is_string($path) || $path === '') {
        return false;
    }

    // Use MILK_DIR as default base
    $baseDir = MILK_DIR;
    $baseDir2 = LOCAL_DIR;
    // 1) Normalize base paths
    $baseDir = rtrim($baseDir, '/') . '/';
    $baseDirReal = realpath($baseDir);
    $baseDir2 = rtrim($baseDir2, '/') . '/';
    $baseDirReal2 = realpath($baseDir2);

    if (!is_string($baseDirReal) && !is_string($baseDirReal2)) {
        return false;
    }

    // SECURITY FIX: add trailing slash to prevent prefix-collision attacks
    $baseDirReal = is_string($baseDirReal) ? rtrim($baseDirReal, '/\\') . '/' : null;
    $baseDirReal2 = is_string($baseDirReal2) ? rtrim($baseDirReal2, '/\\') . '/' : null;

    // 2) Block common directory traversal patterns
    $dangerousPatterns = [
        '../',
        '..\\',
        '/..',
        '\\..',
        '%2e%2e%2f',  // URL-encoded ../
        '%2e%2e/',
        '..%2f',
        '%2e%2e\\',
        '..%5c',
        '%2e%2e%5c',
        '..;',
        '..%00',      // Null byte
        '..%0d%0a',   // CRLF
    ];

    $pathLower = strtolower($path);
    foreach ($dangerousPatterns as $pattern) {
        if (stripos($pathLower, $pattern) !== false) {
           // error_log("SECURITY WARNING: Directory traversal attempt detected: $path");
            return false;
        }
    }

    // Explicit null-byte check
    if (strpos($pathLower, chr(0)) !== false) {
        return false;
    }
  
    $resolvedPath = realpath($path);

    if (!$resolvedPath) {
        return false;
    }

    // SECURITY FIX: add trailing slash to resolved path before prefix match
    $resolvedPathWithSlash = rtrim($resolvedPath, '/') . '/';

    $insideMilkDir = $baseDirReal !== null && strpos($resolvedPathWithSlash, $baseDirReal) === 0;
    $insideLocalDir = $baseDirReal2 !== null && strpos($resolvedPathWithSlash, $baseDirReal2) === 0;

    if (!$insideMilkDir && !$insideLocalDir) {
       // error_log("SECURITY WARNING: Path escape attempt - Resolved: $resolvedPath | Base: $baseDirReal");
        return false;
    }
    return $resolvedPath;
}
