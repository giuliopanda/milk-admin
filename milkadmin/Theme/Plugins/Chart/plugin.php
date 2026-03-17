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

$to_bool = static function ($value, bool $default = false): bool {
    if ($value === null) {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) === 1;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
};

$height = $options['height'] ?? null;
if ($height === null || $height === '') {
    $height = '260px';
} elseif (is_numeric($height)) {
    $height = $height . 'px';
}

$wrap_chart_body = true;
if (array_key_exists('wrap_chart_body', $options)) {
    $wrap_chart_body = $to_bool($options['wrap_chart_body'], true);
} elseif (array_key_exists('remove_chart_body', $options)) {
    $wrap_chart_body = !$to_bool($options['remove_chart_body'], false);
}

if ($options === []) {
    $options = (object) [];
}

?>

<div class="chart-container" id="<?php _p($id); ?>_container">
    <?php echo Get::themePlugin('loading'); ?>
    <?php if ($wrap_chart_body): ?>
        <div class="chart-body" style="height: <?php _p($height); ?>; overflow:auto">
            <canvas id="<?php _p($id); ?>" style="height: 100%; width: 100%;"></canvas>
        </div>
    <?php else: ?>
        <canvas id="<?php _p($id); ?>" style="height: 100%; width: 100%;"></canvas>
    <?php endif; ?>
</div>
