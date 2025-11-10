<?php
namespace Theme\Plugins\UploadFiles;

use App\{Form, Hooks, Token};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Upload files
 */
//input type="file"  max-size="1000000" accept="image/*"

/* QUESTO NON PUO' ESSERE RANDOM!!!! */

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

// Debug: temporarily log the max size
// error_log("Upload max-size for $upload_name: $max_size");

// File type acceptance
if (array_key_exists('accept', $upload_options)) { 
    $accept = $upload_options['accept'];
} else {
    $accept = $upload_options['accept'] ?? '';
}
$accept = Hooks::run("upload_accept_".$upload_name, $accept);
if ($accept != '') {
    $upload_options['accept'] = $accept;
}

// Max number of files
$max_files = $upload_options['max-files'] ?? 0;

// Upload directory
$upload_dir = $upload_options['upload-dir'] ?? 'media/';
// $value = indexed array: [1 => ['url' => 'media/file.jpg', 'name' => 'original.jpg'], ...]

?>

<div class="js-file-uploader">
    <label><?php _p($label); ?></label>
    <div class=" mb-2">
        <ul class="list-group js-file-uploader__list">
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
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start js-group-item">
                        <div class="me-2 w-100">
                            <div class="my-2"><?php echo substr($file_data['name'], 0, 100). ((strlen($file_data['name']) > 100) ? '...' : ''); ?></div>
                            <input type="hidden" class="js-file-name" name="<?php echo $new_file_name; ?>[<?php echo $file_index; ?>][url]" value="<?php echo htmlspecialchars($file_data['url']); ?>">
                            <input type="hidden" name="<?php echo $new_file_name; ?>[<?php echo $file_index; ?>][name]" value="<?php echo htmlspecialchars($file_data['name']); ?>">
                            <input type="hidden" name="<?php echo $new_file_name; ?>[<?php echo $file_index; ?>][existing]" value="1">
                        </div>
                        <div class="my-2 ms-1">
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
        <?php
    }
    ?>
</div>
