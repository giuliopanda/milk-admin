<?php
namespace Theme\Plugins\UploadImages;

use App\{Get, Hooks, Route, Token};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Upload images - Image-specific upload handler
 * Uses the same backend as UploadFiles but with image-specific validation
 * Reuses the upload-file-xhr route from UploadFiles plugin
 */

class UploadImages
{
    function __construct() {
        // Images use the same XHR endpoint as UploadFiles
        // No need to register a new route - reuse upload-file-xhr
    }
}

new UploadImages();
