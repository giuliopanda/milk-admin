<?php 
namespace Modules\Auth\Views;

use App\{Get, MessagesHandler};

!defined('MILK_DIR') && die(); // Avoid direct access

echo Get::themePlugin('title', ['title_txt' => _rt("User"), 'btns' => [ ['title'=>_rt('Add new'), 'color'=>'primary', 'click'=>'create_new_user()']]]);  

// Display any system messages
MessagesHandler::displayMessages();

// Show an alert if the system is blocked
if (isset($system_block_status) && $system_block_status && $system_block_status['blocked']) {
    $remaining_minutes = $system_block_status['remaining_minutes'];
    $message = "<strong>" . _rt('WARNING:') . "</strong> " . _rt('The system is temporarily blocked due to too many failed login attempts.');
    if ($remaining_minutes > 0) {
        $message .= " " . _rt('The system will unlock automatically in') . " <strong>{$remaining_minutes} " . _rt('minutes') . "</strong>.";
    } else {
        $message .= " " . _rt('The system will unlock shortly.');
    }
    echo "<div class='alert alert-danger mb-3'>{$message}</div>";
}
?>

<div class="card">
    <div class="card-header">
        <?php _pt('Filter by status'); ?>: 
        <span class="link-action js-filter-action" data-filter="all" onclick="filterStatus('all'); setActiveFilter(this);"><?php _pt('All'); ?></span>
        <span class="link-action js-filter-action" data-filter="active" onclick="filterStatus('active'); setActiveFilter(this);"><?php _pt('Active'); ?></span>
        <span class="link-action js-filter-action" data-filter="suspended" onclick="filterStatus('suspended'); setActiveFilter(this);"><?php _pt('Suspended'); ?></span>
        <span class="link-action js-filter-action" data-filter="trash" onclick="filterStatus('trash'); setActiveFilter(this);"><?php _pt('Trash'); ?></span>
        <div class="input-group d-inline-flex ms-2" style="width: auto; vertical-align: middle;">
            <input class="form-control" type="search" placeholder="<?php _pt('Search'); ?>" aria-label="Search" spellcheck="false" data-ms-editor="true" id="searchUser" >
            <button class="btn btn-outline-secondary" type="button" id="clearSearch" onclick="document.getElementById('searchUser').value = ''; search();">
                <i class="bi bi-x-lg"></i>
            </button>
            <button class="btn btn-outline-primary" type="button" onclick="search()"><?php _pt('Search'); ?></button>
        </div>
  
    </div>
    <div class="card-body">
        <?php 
            echo Get::themePlugin('table', ['info' => $info, 'rows' => $rows, 'page_info' => $page_info]);
        ?>
    
    </div> 
</div>

<style>
.active-filter {
    font-weight: bold;
    color: #0d6efd !important;
    text-decoration: underline !important;
}
</style>