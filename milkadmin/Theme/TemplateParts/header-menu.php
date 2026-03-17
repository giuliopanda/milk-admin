<?php
namespace Theme\TemplateParts;

use App\{Config, Theme};

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<header>
    <div class="header-container d-md-none">
            <div class="header-left d-flex align-items-center my-1 text-decoration-none">
                <div class="d-flex align-items-center">
                    <div class="d-lg-none fw-bold">
                        <?php echo Config::get('site-title', 'Milk Admin'); ?>
                    </div>
                </div>
            
            </div>

            <div class="d-inline-block d-lg-none header-btn-margin js-burger-menu m-2 me-0" >
                    <div class="btn btn-primary header-btn-sm" id="sidebar-toggler">
                    <i class="bi bi-list"></i>
                    </div>
            </div>
    </div>
    <div class="header-container">
        <div class="header-left d-flex align-items-center my-1 text-decoration-none">
            <!-- Breadcrumbs for desktop -->
            <div class="d-block ms-2">
                <?php if (Theme::has('header.top-left')) : ?>
                    <?php echo Theme::get('header.top-left'); ?>
                <?php endif; ?>  
            </div>
        </div>

        <!-- Navigation links for desktop -->
        <div class="d-flex align-items-center">
            <?php if (Theme::has('header.top-right')) : ?>
                <?php echo Theme::get('header.top-right'); ?>
            <?php endif; ?>
        </div>

    </div>

</header>