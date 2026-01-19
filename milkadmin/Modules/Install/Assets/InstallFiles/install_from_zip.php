<?php
/**
 * Script to extract the first ZIP file found in the current directory
 * Self-deletes after extraction and redirects to /public_html
 */

// Function to display error and stop execution
function showError($message) {
    http_response_code(500);
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Error</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .error-box { background: white; border-left: 4px solid #d32f2f; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin-top: 0; font-size: 24px; }
        p { line-height: 1.6; color: #333; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>⚠ Installation Error</h1>
        <p>' . htmlspecialchars($message) . '</p>
    </div>
</body>
</html>';
    exit();
}

// Get current directory
$currentDir = __DIR__;

// Check if directory is writable
if (!is_writable($currentDir)) {
    showError('Error: The current directory is not writable. Please check directory permissions and ensure the web server has write access.');
}

// Check if ZipArchive class is available
if (!class_exists('ZipArchive')) {
    showError('Error: PHP ZipArchive extension is not installed or enabled. Please install php-zip extension and restart your web server.');
}

// Search for ZIP files in the directory
$zipFiles = glob($currentDir . '/*.zip');

if (empty($zipFiles)) {
    showError('Error: No ZIP file found in the current directory. Please ensure the ZIP archive is uploaded to the same directory as this script.');
}

// Get the first ZIP file found
$zipFile = $zipFiles[0];

// Verify the ZIP file is readable
if (!is_readable($zipFile)) {
    showError('Error: The ZIP file exists but is not readable. Please check file permissions.');
}

// Initialize ZipArchive
$zip = new ZipArchive();

// Attempt to open the ZIP file
$openResult = $zip->open($zipFile);

if ($openResult !== TRUE) {
    // Decode ZipArchive error codes
    $errorMessages = [
        ZipArchive::ER_EXISTS => 'File already exists',
        ZipArchive::ER_INCONS => 'ZIP archive is inconsistent or corrupted',
        ZipArchive::ER_INVAL => 'Invalid argument',
        ZipArchive::ER_MEMORY => 'Memory allocation failure',
        ZipArchive::ER_NOENT => 'No such file',
        ZipArchive::ER_NOZIP => 'Not a valid ZIP archive',
        ZipArchive::ER_OPEN => 'Cannot open file',
        ZipArchive::ER_READ => 'Read error',
        ZipArchive::ER_SEEK => 'Seek error',
    ];

    $errorMsg = isset($errorMessages[$openResult]) ? $errorMessages[$openResult] : 'Unknown error (code: ' . $openResult . ')';
    showError('Error: Cannot open ZIP file. ' . $errorMsg . '. Please verify the ZIP archive is not corrupted and try uploading it again.');
}

// Extract all files to the current directory
if (!$zip->extractTo($currentDir)) {
    $zip->close();
    showError('Error: Failed to extract ZIP archive. Please check directory permissions and ensure there is enough disk space.');
}

$zip->close();

// Delete the ZIP file after extraction
@unlink($zipFile);

// Build redirect URL with proxy support
$scheme = 'http';
if (
    (!empty($_SERVER['REQUEST_SCHEME']) && strtolower($_SERVER['REQUEST_SCHEME']) === 'https') ||
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] === 'on')
) {
    $scheme = 'https';
}
// Proxy/load balancer header (AWS ELB, Cloudflare, Nginx, etc.)
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
}
// Cloudflare specific header
if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
    $visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
    if (isset($visitor->scheme)) {
        $scheme = strtolower($visitor->scheme);
    }
}
$protocol = $scheme . '://';
$host = $_SERVER['HTTP_HOST'];
$currentUrl = $protocol . $host . $_SERVER['REQUEST_URI'];
$scriptName = basename(__FILE__);
$redirectUrl = str_replace($scriptName, 'public_html', $currentUrl);

// Self-delete this script
@unlink(__FILE__);

// Redirect to installation
header('Location: ' . $redirectUrl);
exit();