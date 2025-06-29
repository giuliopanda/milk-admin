<?php
namespace Theme;
use MilkCore\Logs;
use MilkCore\Theme;

!defined('MILK_DIR') && die(); // Avoid direct access

echo Theme::get('footer.first'); 
?>
<div class="border-top">
    <div class="container-fluid bg-white">
    <footer class="py-1 text-center text-body-white">
        <!-- Please do not remove this link, it is a small advertisement for the work I have done. -->
        <a href="https://milkadmin.org" target="_blank" class="text-body-white"><?php echo Theme::get('footer.text', '© '.date('Y').'Project by Milk Admin'); ?></a>
    </footer>
    </div>
</div>

