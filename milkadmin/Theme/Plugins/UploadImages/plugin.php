<?php
namespace Theme\Plugins\UploadImages;

use App\{Form, Hooks, Token};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Upload images with preview
 */

// Get upload options from field configuration
$upload_options = $options ?? [];

// Multiple files option
if (array_key_exists('multiple', $upload_options)) {
    $upload_options['multiple'] = 'multiple';
}

// Max file size
if (array_key_exists('max-size', $upload_options)) {
    $max_size = $upload_options['max-size'];
} else {
    $max_size = $upload_options['max-size'] ?? 0;
}
$max_size = Hooks::run("upload_maxsize_".$upload_name, $max_size);
if ($max_size > 0) {
    $upload_options['max-size'] = $max_size;
}

// File type acceptance - force only images
if (array_key_exists('accept', $upload_options)) {
    $accept = $upload_options['accept'];
} else {
    $accept = $upload_options['accept'] ?? 'image/*';
}
$accept = Hooks::run("upload_accept_".$upload_name, $accept);
if ($accept == '') {
    $accept = 'image/*'; // Force image types if not specified
}
$upload_options['accept'] = $accept;

// Max number of files
$max_files = $upload_options['max-files'] ?? 0;

// Upload directory
$upload_dir = $upload_options['upload-dir'] ?? 'media/';

// Sortable disabled by default, enable with options['sortable'] = true
$sortable_enabled = false;
if (array_key_exists('sortable', $upload_options)) {
    $sortable_enabled = filter_var($upload_options['sortable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $sortable_enabled = ($sortable_enabled === null) ? false : $sortable_enabled;
    unset($upload_options['sortable']);
}

// Download button disabled by default, enable with options['download-link'] = true.
// The plugin only renders links provided in value items as [download_url].
$download_link_enabled = false;
if (array_key_exists('download-link', $upload_options)) {
    $download_link_enabled = filter_var($upload_options['download-link'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $download_link_enabled = ($download_link_enabled === null) ? false : $download_link_enabled;
    unset($upload_options['download-link']);
}
// $value = indexed array: [1 => ['url' => 'media/file.jpg', 'name' => 'original.jpg'], ...]

// Preview size option
$preview_size = $upload_options['preview-size'] ?? 150;

?>

<div class="js-file-uploader js-image-uploader">
    <label><?php _p($label); ?></label>
    <div class="mb-2">
        <ul class="list-group js-file-uploader__list js-image-uploader__list">
            <?php
            if (is_array($value) && !empty($value)) {
                foreach ($value as $file_index => $file_data) {
                    if (!isset($file_data['url']) || !isset($file_data['name'])) {
                        continue;
                    }
                    if (strpos($name, '[') !== false) {
                        $new_file_name = str_replace(']', '_files]', $name);
                    } else {
                        $new_file_name = $name+"_files";
                    }
                    // Build full URL for image preview
                    $image_url = $file_data['url'];
                    $thumb_size = 50; // Fixed thumbnail size
                    $download_url = '';
                    if (
                        $download_link_enabled
                        && isset($file_data['download_url'])
                        && is_string($file_data['download_url'])
                    ) {
                        $download_url = trim($file_data['download_url']);
                    }
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center js-group-item js-image-item">
                        <div class="my-2 me-2 text-body-secondary js-upload-sort-handle<?php echo $sortable_enabled ? '' : ' d-none'; ?>" title="<?php _pt('Drag to reorder'); ?>" style="cursor: grab; user-select: none;">
                            <i class="bi bi-grip-vertical"></i>
                        </div>
                        <div style="flex-shrink: 0; margin-right: 1rem;">
                            <img src="<?php echo htmlspecialchars($image_url); ?>"
                                 alt="<?php echo htmlspecialchars($file_data['name']); ?>"
                                 style="width: <?php echo $thumb_size; ?>px; height: <?php echo $thumb_size; ?>px; object-fit: cover; border-radius: 4px;">
                        </div>
                        <div class="me-2 w-100">
                            <div class="my-2"><?php echo htmlspecialchars(substr($file_data['name'], 0, 100) . (strlen($file_data['name']) > 100 ? '...' : '')); ?></div>
                            <input type="hidden" class="js-file-name" name="<?php echo $new_file_name; ?>[<?php echo $file_index; ?>][url]" value="<?php echo htmlspecialchars($file_data['url']); ?>">
                            <input type="hidden" name="<?php echo $new_file_name; ?>[<?php echo $file_index; ?>][name]" value="<?php echo htmlspecialchars($file_data['name']); ?>">
                            <input type="hidden" name="<?php echo $new_file_name; ?>[<?php echo $file_index; ?>][existing]" value="1">
                        </div>
                        <div class="my-2 ms-1 d-flex">
                            <?php if ($download_url !== '') { ?>
                                <a href="<?php echo htmlspecialchars($download_url); ?>" class="btn btn-sm btn-outline-secondary me-2 js-upload-file-download-link" title="<?php _pt('Download'); ?>" aria-label="<?php _pt('Download'); ?>">
                                    <i class="bi bi-download"></i>
                                </a>
                            <?php } ?>
                            <button type="button" class="btn-close js-upload-file-remove-exist-value" aria-label="Close"></button>
                        </div>
                    </li>
                    <?php
                }
            }
            ?>
        </ul>
    </div>
    <?php Form::input('file', $name, '', '', $upload_options); ?>
    <?php
    if (!isset($upload_name)) {
        // il nome è importante per poter controllare i permessi, il max file size, e il tipo di file accettato
        ?><div class="alert alert-danger"><?php _pt('$upload_name not set! Name is important to control upload, check permissions, max file size, and accepted file type'); ?></div><?php
    } else {
        ?>
        <input type="hidden" class="js-file-token" value="<?php echo Token::get($upload_name); ?>">
        <input type="hidden" class="js-file-uploader-name" value="<?php echo $upload_name; ?>">
        <input type="hidden" class="js-max-files" value="<?php echo $max_files; ?>">
        <input type="hidden" class="js-upload-dir" value="<?php echo htmlspecialchars($upload_dir); ?>">
        <input type="hidden" class="js-preview-size" value="<?php echo $preview_size; ?>">
        <input type="hidden" class="js-sortable-enabled" value="<?php echo $sortable_enabled ? '1' : '0'; ?>">
        <input type="hidden" class="js-uploader-type" value="image">
        <?php
    }
    ?>
</div>
