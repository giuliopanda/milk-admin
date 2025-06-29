<?php
namespace Theme;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Avoid direct access
if (!isset($path) || $path == '') {
    $path = THEME_URL.'/assets/logo-big.webp';
}
if (!isset($custom_class) || $custom_class == '') {
    $custom_class = 'resized-logo';
}
?><img src="<?php echo Get::uri_path($path) ;?>" alt="MilkGraph logo"<?php echo ($custom_class ?? '') ? ' class="'.$custom_class.'"' : '';?>>