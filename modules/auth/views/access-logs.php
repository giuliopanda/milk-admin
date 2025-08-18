<?php
namespace Modules\Auth;
use MilkCore\Form;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-shield-lock-fill me-2"></i>
                    Access Logs
                </h3>
                <div class="card-tools">
                    <small class="text-muted">Monitor user login activity and session tracking</small>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filters Section -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                         <?php Form::input('date', 'start_date', 'Start Date', $_REQUEST['start_date'] ?? '', ['id'=>'sdf', 'floating'=>false, 'data-filter-id'=>$table_id, 'data-filter-type'=>'start_date', 'class'=>'js-milk-filter']); ?>
                    </div>
                    <div class="col-md-3">
                        <?php Form::input('date', 'end_date', 'End Date', $_REQUEST['end_date'] ?? '', ['id'=>'sdf', 'floating'=>false, 'data-filter-id'=>$table_id, 'data-filter-type'=>'end_date', 'class'=>'js-milk-filter']); ?>   
                    </div>
                    <div class="col-md-3">
                        <!-- User Filter -->
                        <?php 
                        Form::select('filter_user_id', 'Filter by User', $users_options, $_REQUEST['user_id'] ?? '', [
                            'floating' => false,
                            'data-filter-id' => $table_id,
                            'data-filter-type' => 'user_id',
                            'class' => 'js-milk-filter'
                        ]); 
                        ?>
                    </div>
                    <div class="col-md-3">
                    <label>&nbsp;</label>
                        <!-- Filter Button -->
                        <button type="button" 
                                class="btn btn-primary js-milk-filter-onclick w-100" 
                                data-filter-id="<?php echo $table_id ?>">
                            <i class="bi bi-search me-1"></i>
                            Filter
                        </button>
                    </div>
                </div>
                
              
                <!-- Access Logs Table -->
                <div class="table-responsive">
                    <?php echo $table_html  ?>
                </div>
                
            </div>
            
            <div class="card-footer">
                <div class="row align-items-center">
                    <div class="col">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Sessions are automatically tracked when users log in and out of the system
                        </small>
                    </div>
                    <div class="col-auto">
                        <small class="text-muted">
                            Last updated: <?php echo date('Y-m-d H:i:s') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
