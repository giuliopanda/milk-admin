<?php
namespace Theme\TemplateParts;

use App\{Route, Theme};
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
* The sidebar with the left menus.
* To populate it use Theme::set('sidebar.links', ['url' => '', 'title' => ''])
*
* To manually select a menu item, bypassing automatic selection:
* Theme::set('sidebar.selected', '?page=mypage'); // Select by URL
* or
* Theme::set('sidebar.selected', 'My Menu Title'); // Select by title
*
 */
?>
<div class="sidebar" id="sidebar">
    <h2 class="sidebar-title d-flex">
        <?php Template::getLogo( 'logo-small',  THEME_URL.'/Assets/logo-white.webp'); ?>
        <a href="<?php echo Route::url(); ?>"><?php echo substr(Theme::get('site.title', 'MILK ADMIN'),0, 30); ?></a>
    </h2>
    <div class="sidebar-scroll">
    <?php
    if (Theme::has('sidebar.links')) :
        ?> <ul class="nav flex-column"> <?php
        Theme::multiarrayOrder('sidebar.links', 'order');

        // Check if manual selection is set
        $manual_selected = Theme::get('sidebar.selected', null);

        foreach (Theme::for('sidebar.links') as $link) {
            if (!Theme::check($link, ['url', 'title'])) {
                // error!
            ?><p class="alert alert-danger"><?php _pt('The link variable was not set correctly.
It should have been an array with keys [\'url\', \'title\']'); ?></p><?php
            continue;
        }
        ?><li class="nav-item">
            <?php
            // Check if this menu should be selected manually
            $selected = '';
            if ($manual_selected !== null) {
                // Manual selection: check by URL or title
                if ($link['url'] === $manual_selected || $link['title'] === $manual_selected) {
                    $selected = 'sidebar-nav-link-selected';
                }
            } else {
                // Automatic selection based on current page
                $selected = Route::comparePageUrl($link['url'], [], $link['strict_check'] ?? false) ? 'sidebar-nav-link-selected' : '';
            }
            ?>
            <a href="<?php _p($link['url']); ?>" class="sidebar-nav-link nav-link d-flex <?php echo  $selected ; ?>" aria-current="page">   
                <i class="<?php _p($link['icon'] ?? ''); ?>"></i>
                <div class="sidebar-link-title"><?php _p($link['title']); ?></div>
                
                <!-- Dynamic Bootstrap badges -->
                <?php if (isset($link['badge']) && !empty($link['badge'])): ?>
                    <?php 
                    $badge_class = 'bg-primary'; // default
                    if (isset($link['badge_color'])) {
                        $badge_class = 'bg-' . $link['badge_color'];
                    }
                    ?>
                    <span class="badge <?php echo $badge_class; ?> sidebar-badge"><?php _p($link['badge']); ?></span>
                <?php elseif (isset($link['badge_dot']) && $link['badge_dot']): ?>
                    <!-- Dot badge -->
                    <span class="sidebar-badge-dot"></span>
                <?php endif; ?>
            </a>
        </li><?php
        }
        ?> </ul> <?php
    endif;
    ?>

    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>