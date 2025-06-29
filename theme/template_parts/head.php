<?php
namespace Theme;
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Config;
use MilkCore\Permissions;
use MilkCore\Token;

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
      <meta name="csrf-token-name" content="<?php echo Token::get_token_name(session_id()); ?>">
    <?php endif; ?>
    <title><?php echo Theme::get('header.title', Config::get('site-title', '')); ?></title>
    <link href="<?php echo THEME_URL; ?>/assets_extensions/bootstrap/css/bootstrap.min.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <?php Template::get_css(); ?>
    <link href="<?php echo THEME_URL; ?>/assets_extensions/bootstrap/icons/font/bootstrap-icons.min.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <link href="<?php echo THEME_URL; ?>/assets_extensions/prism/prism.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <link href="<?php echo THEME_URL; ?>/assets_extensions/trix-editor/trix.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <link rel="icon" href="<?php echo THEME_URL; ?>/assets/favicon.ico" type="image/x-icon">
    <script>
      var milk_url = "<?php _p(Route::url()); ?>";
    </script>
    <?php echo Theme::get('header.custom'); ?>
  </head>
  <body>