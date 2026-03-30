<?php
namespace Modules\Home\Assets;
use App\{Route};
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
       <p><b>Milk Admin</b> is a system for managing complex administrative panels.
        If you're new to the system, try creating a complete module by clicking on <a href="<?php echo Route::url('?page=projects'); ?>">Projects</a> on the left,
        or go to the <a href="<?php echo Route::url('?page=docs'); ?>">documentation</a> to become a true expert.
        Alternatively, download the  <a href="https://www.milkadmin.org/download-modules/" target="_blank">additional modules</a> available on the website.<br><br>
        If you like the project, click "starred" <a href="https://github.com/giuliopanda/milk-admin" target="_blank">to the repository</a>
        </p>
    </div>
</div>