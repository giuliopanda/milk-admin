<?php
namespace Theme;
use MilkCore\Theme;
use MilkCore\Config;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<header>

    

    <div class="header-container">
        
        <div class="header-left d-flex align-items-center my-2 text-decoration-none">
            <div class="d-flex align-items-center">
                <div class="d-inline-block d-lg-none header-btn-margin js-burger-menu">
                    <div class="btn btn-primary header-btn-sm" id="sidebar-toggler">
                        <i class="bi bi-list"></i>
                    </div>
                </div>
                <!-- Site title for mobile -->
                <div class="d-lg-none fw-bold">
                    <?php echo Config::get('site-title', 'Milk Admin'); ?>
                </div>
            </div>
            <!-- Breadcrumbs for desktop -->
            <div class="d-none d-lg-block ms-2">
                <?php if (Theme::has('header.breadcrumbs')) : ?>
                    <?php echo Theme::get('header.breadcrumbs'); ?>
                <?php endif; ?>  
            </div>
        </div>

        <!-- Navigation links for desktop -->
        <ul class="nav my-2 justify-content-center my-md-0 text-small d-none d-lg-flex">
        <?php if (Theme::has('header.links')) : ?>
            <?php Theme::multiarray_order('header.links', 'order'); ?>
            <?php foreach (Theme::for('header.links') as $menu) : ?>
                <li>
                    <?php if ($menu['url'] ?? '' != '') { ?>
                        <a href="<?php _p($menu['url']); ?>" class="nav-link">
                            <i class="<?php _p($menu['icon'] ?? ''); ?>"></i>
                            <?php _ph($menu['title']); ?>
                        </a>
                    <?php } else { ?>
                        <span class="nav-link text-black">
                            <i class="<?php _p($menu['icon'] ?? ''); ?>"></i>
                            <?php _ph($menu['title']); ?>
                        </span>
                    <?php } ?>
                </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        
    </div>

    
</header>