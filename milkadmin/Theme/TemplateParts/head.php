<?php
namespace Theme\TemplateParts;

use App\{Config, Route, Token, Permissions, Theme};
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access

$version = Config::get('version');
$current_user_id = 0;
try {
    $auth = \App\Get::make('Auth');
    if (is_object($auth) && method_exists($auth, 'getUser')) {
        $user = $auth->getUser();
        $current_user_id = (int)($user->id ?? 0);
    }
} catch (\Throwable) {
    $current_user_id = 0;
}
?>
<!doctype html>
<html lang="<?php echo Theme::get('header.lang','en'); ?>">
  <head>
    <meta charset="<?php echo Theme::get('header.charset','utf-8'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="generator" content="Milk Admin - https://milkadmin.org">
    <meta name="csrf-token" content="<?php echo Token::get(session_id()); ?>">
    <meta name="csrf-token-name" content="<?php echo Token::getTokenName(session_id()); ?>">
    <title><?php echo Theme::get('header.title', Config::get('site-title', '')); ?></title>
    <link href="<?php echo THEME_URL; ?>/AssetsExtensions/Bootstrap/Css/bootstrap.min.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <?php Template::getCss(); ?>
    <link href="<?php echo THEME_URL; ?>/AssetsExtensions/Bootstrap/Icons/Font/bootstrap-icons.min.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
     <link href="<?php echo THEME_URL; ?>/AssetsExtensions/TrixEditor/trix.css?v=<?php echo $version; ?>" rel="stylesheet" crossorigin="anonymous">
    <link rel="icon" href="<?php echo THEME_URL; ?>/Assets/favicon.ico" type="image/x-icon">
    <script>
      var milk_url = "<?php _p(Route::url()); ?>";
      var max_file_size_mb = <?php echo Template::getMaxUploadSizeMB(); ?>;
      window.MilkAdmin=window.MilkAdmin||{};window.MilkAdmin.user_id=<?php echo $current_user_id; ?>;
    </script>
    <?php echo Theme::get('header.custom'); ?>
  </head>
  <body>
