<?php
namespace Theme;
use MilkCore\Config;

!defined('MILK_DIR') && die(); // Avoid direct access

$version = Config::get('version');
?>
<script src="<?php echo THEME_URL; ?>/assets_extensions/bootstrap/js/bootstrap.bundle.min.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/assets_extensions/chart.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/assets_extensions/chartjs-plugin-datalabels.min.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/assets_extensions/prism/prism.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/assets_extensions/tomselect.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/assets_extensions/trix-editor/trix.min.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>

<?php Template::get_js(); ?>
</body>
</html>