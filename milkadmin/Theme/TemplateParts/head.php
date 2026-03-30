<?php
namespace Theme\TemplateParts;

use App\{Config, Route, Token, Get, Theme};
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access

$user = Get::user();
?>
<!doctype html>
<html lang="<?php echo Theme::get('header.lang','en'); ?>">
  <head>
    <meta charset="<?php echo Theme::get('header.charset','utf-8'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="generator" content="Milk Admin - https://milkadmin.org">
    <meta name="csrf-token" content="<?php echo Token::get(); ?>">
    <meta name="csrf-token-name" content="<?php echo Token::getTokenName(); ?>">
    <title><?php echo Theme::get('header.title', Config::get('site-title', '')); ?></title>
    <?php Template::getCss(); ?>
    <link rel="icon" href="<?php echo THEME_URL; ?>/Assets/favicon.ico" type="image/x-icon">
    <script>
      var milk_url = "<?php _p(Route::url()); ?>";
      var max_file_size_mb = <?php echo Template::getMaxUploadSizeMB(); ?>;
      window.MilkAdmin=window.MilkAdmin||{};window.MilkAdmin.user_id=<?php echo (int)($user->id ?? 0); ?>;
    </script>
    <?php echo Theme::get('header.custom'); ?>
  </head>
  <body>
