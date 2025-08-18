<?php
namespace Modules\Install;
use MilkCore\Route;
use MilkCore\MessagesHandler;
use MilkCore\Get;
use MilkCore\Config;

if (!defined('MILK_DIR')) die();
?>

<div class="bg-white p-4">
    <h1><?php _pt('System Update'); ?></h1>
    <?php MessagesHandler::display_messages(); ?>
    
    <div class="mb-4">
        <?php _ph($html); ?>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?php _pt('Upload update'); ?></h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                <?php _pt('Upload a ZIP file containing the new system version.'); ?><br>
                <?php _pt('Current version:'); ?> <strong><?php echo Config::get('version'); ?></strong>
            </p>
            
            <form action="<?php echo Route::url(['page' => 'install', 'action' => 'upload-update']); ?>" 
                  method="post" 
                  enctype="multipart/form-data" 
                  id="upload-form">
                
                <div class="mb-3">
                    <label for="update_file" class="form-label"><?php _pt('Update file'); ?></label>
                    <input type="file" 
                           class="form-control" 
                           name="update_file" 
                           id="update_file" 
                           accept=".zip"
                           required>
                    <div class="form-text">
                        <?php _pt('Accepted format: ZIP'); ?>
                    </div>
                </div>
                
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong><?php _pt('Warning:'); ?></strong>
                    <?php _pt('Before proceeding with the update, make sure to:'); ?>
                    <ul class="mb-0 mt-2">
                        <li><?php _pt('Have performed a complete system backup'); ?></li>
                        <li><?php _pt('Have verified the compatibility of the new version'); ?></li>
                    </ul>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" 
                           type="checkbox" 
                           value="1" 
                           id="confirm_backup" 
                           name="confirm_backup"
                           required>
                    <label class="form-check-label" for="confirm_backup">
                        <?php _pt('I confirm I have performed a complete system backup'); ?>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <i class="bi bi-upload"></i> <?php _pt('Upload update'); ?>
                </button>
                <a href="<?php echo Route::url(['page' => 'home']); ?>" class="btn btn-secondary">
                    <?php _pt('Cancel'); ?>
                </a>
            </form>
        </div>
    </div>
    
    <?php 
    // Show temporary directory information if in debug mode
    if (Config::get('debug')): ?>
    <div class="mt-3">
        <small class="text-muted">
            <?php _pt('Temporary directory:'); ?> <?php echo Get::temp_dir(); ?>
        </small>
    </div>
    <?php endif; ?>
</div>

