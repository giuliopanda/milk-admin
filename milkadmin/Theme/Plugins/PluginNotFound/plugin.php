<?php
namespace Theme\Plugins\PluginNotFound;
!defined('MILK_DIR') && die(); // Avoid direct access
$module = isset($module) ? (string) $module : '';
?>
<div class="alert alert-danger">The plugin '<b><?php echo $module; ?></b>' was not found</div>
