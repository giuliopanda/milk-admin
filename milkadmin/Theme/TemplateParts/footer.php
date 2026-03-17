<?php
namespace Theme\TemplateParts;

use App\{Hooks, Theme, Lang};

!defined('MILK_DIR') && die(); // Avoid direct access
Hooks::run('footer');
echo Theme::get('footer.first');

$translations_page = _r($_REQUEST['page'] ?? '');
$translations_js = Lang::generateJs($translations_page, true);
// Prevent accidental </script> termination inside translation strings.
$translations_js = str_replace('</', '<\/', $translations_js);
?>
<script><?php echo $translations_js; ?></script>
<div class="border-top">
    <div class="container-fluid bg-white">
    <footer class="py-1 text-center text-body-white">
        <!-- Please do not remove this link, it is a small advertisement for the work I have done. -->
        <a href="https://milkadmin.org" target="_blank" class="text-body-white"><?php echo Theme::get('footer.text', '© '.date('Y').'Project by Milk Admin'); ?></a>
    </footer>
    </div>
</div>
