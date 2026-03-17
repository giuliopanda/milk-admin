<?php
namespace Modules\Install\Views;

use Theme\Template;

if (!defined('MILK_DIR')) die();
?>
<div class="text-center m-3">
    <?php Template::getLogo(); ?>
</div>
<div class="bg-white p-4 text-center" style="width: 48rem; margin:2rem auto">
    <h1><?php _pt('Installing...'); ?></h1>

    <div class="my-5">
        <div class="spinner-grow text-primary" role="status" style="width: 5rem; height: 5rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <p class="text-muted"><?php _pt('Please wait while the installation is in progress. This may take a few moments.'); ?></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.location.href = '?page=install&action=install-execute';
    }, 500);
});
</script>