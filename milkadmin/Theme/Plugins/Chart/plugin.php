<?php
namespace Theme\Plugins\Chart;

use App\Get;

!defined('MILK_DIR') && die(); // Avoid direct access

$type = $type ?? 'bar';
$id = $id ?? uniqid('chart_', true);
$labels = $labels ?? [];
$data = $data ?? [];
$options = $options ?? [];
if (!is_array($options)) {
    $options = [];
}
$debug = isset($_GET['debug_charts']) && $_GET['debug_charts'] !== '0';
$height = $options['height'] ?? null;
if ($height === null || $height === '') {
    $height = '260px';
} elseif (is_numeric($height)) {
    $height = $height . 'px';
}
if ($options === []) {
    $options = (object) [];
}

?>

<div class="chart-container" id="<?php _p($id); ?>_container">
    <?php if ($debug): ?>
        <pre class="bg-light p-2 mb-2" style="overflow:auto; max-height: 260px;">
<?php
echo "chart debug\n";
echo "id: " . $id . "\n";
echo "type: " . $type . "\n";
echo "height: " . $height . "\n";
echo "data:\n";
var_dump($data);
echo "options:\n";
var_dump($options);
?>
        </pre>
    <?php endif; ?>
    <?php echo Get::themePlugin('loading'); ?>
    <div class="chart-body" style="height: <?php _p($height); ?>;">
        <canvas id="<?php _p($id); ?>" style="height: 100%; width: 100%;"></canvas>
    </div>
</div>
