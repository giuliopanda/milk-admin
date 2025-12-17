<?php
namespace Theme\TemplateParts;

use App\Config;
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access

$version = Config::get('version');
?>
<script src="<?php echo THEME_URL; ?>/AssetsExtensions/Bootstrap/Js/bootstrap.bundle.min.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/AssetsExtensions/chart.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/AssetsExtensions/chartjs-plugin-datalabels.min.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/AssetsExtensions/tomselect.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>
<script src="<?php echo THEME_URL; ?>/AssetsExtensions/TrixEditor/trix.min.js?v=<?php echo $version; ?>"  crossorigin="anonymous"></script>

<?php Template::getJs(); ?>
</body>
</html>