<?php
namespace Modules\Home;
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="card-header">
    <h2 class="mb-0">
        <i class="fas fa-newspaper me-2"></i>
        Welcome to Milk Admin
    </h2>
</div>
<div class="card-body p-4">
    <div class="row">
        <p>You managed to install it, thanks for your patience, <b>now let's see how you can go on!</b></br> At the bottom of the sidebar you will find the Posts module. Try going into the modules folder and rename the posts folder by putting a dot before it (.posts). Once done, reload this page. You will see that the posts menu item has disappeared... This will disable the modules.</p><p>Good, now try to follow the getting started that you find in the <a href="<?php echo Route::url('?page=docs'); ?>">documentation</a>. They should help you to start getting familiar with this project.</p>
    </div>
</div>