<?php
namespace Theme\Plugins\Chart;

use App\Get;

!defined('MILK_DIR') && die(); // Avoid direct access

$type = $type ?? 'bar';
$id = $id ?? uniqid('chart_', true);
$labels = $labels ?? [];
$data = $data ?? [];
$options = $options ?? [];

?>

<div class="chart-container" id="<?php _p($id); ?>_container">
    <?php echo Get::themePlugin('loading'); ?>
    <canvas id="<?php _p($id); ?>"></canvas>
<script>document.addEventListener('DOMContentLoaded', function() {  itoCharts.draw('<?php _p($id); ?>', '<?php _p($type); ?>', <?php _pjs($data); ?>,  <?php _pjs($options); ?>) })</script>
</div>