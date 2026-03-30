<?php
namespace Extensions\Projects\Classes\Module;

use App\Abstracts\AbstractModule;
use App\Get;
use App\Response;
use App\Settings;
use App\Token;

!defined('MILK_DIR') && die();

class ProjectDownloadService
{
    protected AbstractModule $module;

    public function __construct(AbstractModule $module)
    {
        $this->module = $module;
    }

    public function renderDownloadFilePage(): void
    {
        $modulePage = trim((string) $this->module->getPage());
        $requestFilename = (string) ($_REQUEST['filename'] ?? '');
        $tokenValue = (string) ($_REQUEST['token'] ?? '');

        $normalizedFilename = self::normalizeDownloadFilename($requestFilename);
        if ($normalizedFilename === '') {
            $this->respondDownloadError('Missing or invalid filename.', 400);
            return;
        }
        if ($tokenValue === '') {
            $this->respondDownloadError('Missing download token.', 400);
            return;
        }

        $tokenName = self::buildDownloadTokenName($modulePage, $normalizedFilename);
        if (!Token::checkValue($tokenValue, $tokenName)) {
            $tokenError = Token::$last_error !== '' ? Token::$last_error : 'invalid_token';
            $this->respondDownloadError('Invalid or expired download token (' . $tokenError . ').', 403);
            return;
        }

        $filePath = $this->resolveDownloadFilePath($normalizedFilename);
        if ($filePath === null) {
            $this->respondDownloadError('File not found.', 404);
            return;
        }

        $downloadName = basename($normalizedFilename);
        if ($downloadName === '' || $downloadName === '.' || $downloadName === '..') {
            $downloadName = basename($filePath);
        }
        $downloadName = str_replace(["\r", "\n", '"'], '', $downloadName);
        if ($downloadName === '') {
            $downloadName = 'download.bin';
        }

        $mimeType = $this->detectMimeType($filePath);
        $fileSize = @filesize($filePath);
        if ($fileSize === false) {
            $fileSize = 0;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code(200);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string) $fileSize);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($filePath);
        Settings::save();
        Get::closeConnections();
        exit;
    }

    public static function normalizeDownloadFilename(string $filename): string
    {
        $normalized = rawurldecode(trim($filename));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace("\0", '', $normalized);
        $normalized = str_replace('\\', '/', $normalized);
        $normalized = preg_replace('/\/+/', '/', $normalized) ?? '';
        $normalized = ltrim($normalized, '/');

        $parts = explode('/', $normalized);
        $safeParts = [];
        foreach ($parts as $part) {
            $trimmedPart = trim($part);
            if ($trimmedPart === '' || $trimmedPart === '.') {
                continue;
            }
            if ($trimmedPart === '..') {
                return '';
            }
            if (preg_match('/[\x00-\x1F\x7F]/', $trimmedPart) === 1) {
                return '';
            }
            $safeParts[] = $trimmedPart;
        }

        return implode('/', $safeParts);
    }

    public static function buildDownloadTokenName(string $modulePage, string $filename): string
    {
        $safeModulePage = strtolower(trim($modulePage));
        $safeModulePage = preg_replace('/[^a-z0-9_-]/', '', $safeModulePage) ?? '';
        if ($safeModulePage === '') {
            $safeModulePage = 'module';
        }

        $normalizedFilename = self::normalizeDownloadFilename($filename);
        if ($normalizedFilename === '') {
            $normalizedFilename = 'file';
        }

        return 'projects_download_' . $safeModulePage . '_' . md5($normalizedFilename);
    }

    protected function resolveDownloadFilePath(string $normalizedFilename): ?string
    {
        $safeFilename = self::normalizeDownloadFilename($normalizedFilename);
        if ($safeFilename === '') {
            return null;
        }

        $candidateRelPaths = [$safeFilename];
        if (strpos($safeFilename, 'media/') !== 0) {
            $candidateRelPaths[] = 'media/' . $safeFilename;
        }
        if (strpos($safeFilename, 'temp/') !== 0) {
            $candidateRelPaths[] = 'temp/' . $safeFilename;
        }

        foreach (array_values(array_unique($candidateRelPaths)) as $relativePath) {
            $fullPath = rtrim((string) LOCAL_DIR, '/\\') . '/' . ltrim($relativePath, '/');
            $realPath = realpath($fullPath);
            if ($realPath === false) {
                continue;
            }
            if (!$this->isLocalPath($realPath)) {
                continue;
            }
            if (!is_file($realPath) || !is_readable($realPath)) {
                continue;
            }

            return $realPath;
        }

        return null;
    }

    protected function isLocalPath(string $path): bool
    {
        $localDir = realpath((string) LOCAL_DIR);
        if ($localDir === false) {
            return false;
        }

        $normalizedLocalDir = rtrim(str_replace('\\', '/', $localDir), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $path);

        return str_starts_with($normalizedPath, $normalizedLocalDir);
    }

    protected function detectMimeType(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return 'application/octet-stream';
    }

    protected function respondDownloadError(string $message, int $statusCode): void
    {
        if (Response::isJson()) {
            http_response_code($statusCode);
            Response::json([
                'success' => false,
                'msg' => $message,
                'status' => $statusCode,
            ]);
            return;
        }

        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        Settings::save();
        Get::closeConnections();
        exit;
    }
}
