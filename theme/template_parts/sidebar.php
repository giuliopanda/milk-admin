<?php
namespace Theme;
use MilkCore\Theme;
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
* The sidebar with the left menus.
* To populate it use Theme::set('sidebar.links', ['url' => '', 'title' => ''])
*
 */
?>
<div class="sidebar" id="sidebar">
    <h2 class="sidebar-title d-flex">
        <?php Template::get_logo( 'logo-small',  THEME_URL.'/assets/logo-white.webp'); ?>
        <a href="<?php echo Route::url(); ?>"><?php echo substr(Theme::get('site.title', 'MILK ADMIN'),0, 30); ?></a>
    </h2>
    <div class="sidebar-scroll">
    <?php  
    if (Theme::has('sidebar.links')) : 
        ?> <ul class="nav flex-column"> <?php
        Theme::multiarray_order('sidebar.links', 'order');
        foreach (Theme::for('sidebar.links') as $link) {
            if (!Theme::check($link, ['url', 'title'])) {
                // error!
            ?><p class="alert alert-danger"><?php _pt('The link variable was not set correctly.
It should have been an array with keys [\'url\', \'title\']'); ?></p><?php
            continue;
        }
        ?><li class="nav-item">
            <?php $selected = Route::compare_page_url($link['url'], [], $link['strict_check'] ?? false) ? 'sidebar-nav-link-selected' : ''; ?>
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