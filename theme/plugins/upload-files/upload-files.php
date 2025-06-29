<?php
namespace Theme\Plugins;
use Theme\Ito;
use MilkCore\Form;
use MilkCore\Token;
use MilkCore\Hooks;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Upload files
 */
//input type="file"  max-size="1000000" accept="image/*"

/* QUESTO NON PUO' ESSERE RANDOM!!!! */


if (array_key_exists('multiple', $options)) {
    $options['multiple'] = 'multiple';
}

if (array_key_exists('max_size', $options)) { 
    $max_size = $options['max-size'];
} else {
    $max_size = 0;
}
$max_size = Hooks::run("upload_maxsize_".$upload_name, $max_size);
if ($max_size > 0) {
    $options['max-size'] = $max_size;
}
//accept="image/jpg, image/gif"
if (array_key_exists('accept', $options)) { 
    $accept = $options['accept'];
} else {
    $accept = '';
}
$accept = Hooks::run("upload_accept_".$upload_name, $accept);
if ($accept != '') {
    $options['accept'] = $accept;
}
// $value = ['file_name' => ['file_name', 'file_name' ...], 'file_original_name' => ['original_name', 'original_name' ...]]

?>

<div class="js-file-uploader">
    <label><?php _p($label); ?></label>
    <div class=" mb-2">
        <ul class="list-group js-file-uploader__list">
            <?php
            if (isset($value['file_name'])) {
                foreach ($value['file_name'] as $key => $file_name) {
                    if (!isset($value['file_original_name'][$key])) {
                        $value['file_original_name'][$key] = $file_name;
                    }
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start js-group-item">
                        <div class="me-2 w-100">
                            <div class="my-2"><?php echo substr($value['file_original_name'][$key], 0, 100). ((strlen($value['file_original_name'][$key]) > 100) ? '...' : ''); ?></div>
                            <input type="hidden" class="js-file-name" name="<?php echo $name; ?>_file_name[]" value="<?php echo $file_name; ?>">
                            <input type="hidden" name="<?php echo $name; ?>_file_original_name[]" value="<?php echo $value['file_original_name'][$key]; ?>">
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
    <?php Form::input('file', $name, '', '', $options); ?>
    <?php 
    if (!isset($upload_name)) {
        // il nome è importante per poter controllare i permessi, il max file size, e il tipo di file accettato
        ?><div class="alert alert-danger"><?php _pt('$upload_name not set! Name is important to control upload, check permissions, max file size, and accepted file type'); ?></div><?php
    } else {
        ?>
        <input type="hidden" class="js-file-token" value="<?php echo Token::get($upload_name); ?>">
        <input type="hidden" class="js-file-uploader-name" value="<?php echo $upload_name; ?>">
        <?php
    }
    ?>
</div>
