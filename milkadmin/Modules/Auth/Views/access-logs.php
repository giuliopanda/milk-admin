<?php
namespace Modules\Auth\Views;

!defined('MILK_DIR') && die(); // Avoid direct access

// Load active sessions partial view
require __DIR__ . '/active-sessions-partial.php';
?>

<!-- Access Logs Table Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <?php echo $title_html ?? ''; ?>
            </div>
            <div class="card-body">
                <!-- Access Logs Table (rendered by TableBuilder) -->
                <div class="table-responsive">
                    <?php echo $html ?? ''; ?>
                </div>
            </div>

            <div class="card-footer">
                <div class="row align-items-center">
                    <div class="col">
                        <small class="text-body-secondary">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php _pt('Sessions are automatically tracked when users log in and out of the system'); ?>
                        </small>
                    </div>
                    <div class="col-auto">
                        <small class="text-body-secondary">
                            <?php _pt('Last updated:'); ?> <?php echo date('Y-m-d H:i:s') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
