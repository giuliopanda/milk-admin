<?php
namespace Theme\TemplateParts;

use App\{Config, Route, Token, Permissions, Theme};
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access

$version = Config::get('version');
?>
<!doctype html>
<html lang="<?php echo Theme::get('header.lang','en'); ?>">
  <head>
    <meta charset="<?php echo Theme::get('header.charset','utf-8'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="generator" content="Milk Admin - https://milkadmin.org">
    <?php if (!Permissions::check('_user.is_guest')): ?>
      <meta name="csrf-token" content="<?php echo Token::get(session_id()); ?>">
      <meta name="csrf-token-name" content="<?php echo Token::getTokenName(session_id()); ?>">
    <?php endif; ?>
    <title><?php echo Theme::get('header.title', Config::get('site-title', '')); ?></title>
    <link href="<?php echo THEME_URL; ?>/AssetsExtensions/Bootstrap/Css/bootstrap.min.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <?php Template::getCss(); ?>
    <link href="<?php echo THEME_URL; ?>/AssetsExtensions/Bootstrap/Icons/Font/bootstrap-icons.min.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <link href="<?php echo THEME_URL; ?>/AssetsExtensions/Prism/prism.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <link href="<?php echo THEME_URL; ?>/AssetsExtensions/TrixEditor/trix.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <link rel="icon" href="<?php echo THEME_URL; ?>/Assets/favicon.ico" type="image/x-icon">
    <script>
      var milk_url = "<?php _p(Route::url()); ?>";
      var max_file_size_mb = <?php echo Template::getMaxUploadSizeMB(); ?>;
    </script>
    <?php echo Theme::get('header.custom'); ?>
  </head>
  <body>