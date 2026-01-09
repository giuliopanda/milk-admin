<?php
namespace Theme\TemplateParts;

use App\Get;

!defined('MILK_DIR') && die(); // Avoid direct access
if (!isset($path) || $path == '') {
    $path = THEME_URL.'/Assets/logo-big.webp';
}
if (!isset($custom_class) || $custom_class == '') {
    $custom_class = 'resized-logo';
}
?><img src="<?php echo Get::uriPath($path) ;?>" alt="Milk Admin logo"<?php echo ($custom_class ?? '') ? ' class="'.$custom_class.'"' : '';?>>