<?php
use MilkCore\MessagesHandler;
use MilkCore\Token;

!defined('MILK_DIR') && die(); // Prevent direct access
?>
<div class="container-fluid">
    <!-- Jobs List Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?php echo htmlspecialchars($title); ?></h5>
            <div>
                <div class="btn btn-outline-primary btn-sm jsfn-runalljobs">
                    <i class="bi bi-play-circle-fill"></i> Run all due jobs
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php MessagesHandler::display_messages(); ?>

            <?php if (empty($jobs_tasks)): ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle me-2"></i> No jobs registered.
                    <?php if (defined('MILK_DEBUG') && constant('MILK_DEBUG')): ?>
                        <hr>
                        <p><strong>Developer Tip:</strong></p>
                        <p>To register a job, use the <code>JobsContract</code> class. Example:</p>
                        <pre><code class="language-php"><?php echo htmlspecialchars(
"JobsContract::register(
    'backup_settimanale', 
    function() { 
        // Logica per eseguire il backup
        return true; 
    },
    '0 8 * * 1',
    'Performs a weekly database backup',
    true
);"); ?></code></pre>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover js-table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Schedule (Greenwich Mean Time)</th>
                            <th scope="col">Last Run</th>
                            <th scope="col">Next Run</th>
                            <th scope="col">Duration</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs_tasks as $task): 
                        
                            // Aggiungi classe di riga in base allo stato
                            $row_class = '';
                            if ($task['status_type'] === 'blocked') {
                                $row_class = 'table-danger';
                            } elseif ($task['status_type'] === 'running') {
                                $row_class = 'table-primary';
                            } elseif ($task['status_type'] === 'due') {
                                $row_class = 'table-warning';
                            }
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                            
                                <td>
                                    <b><?php _pt($task['name']); ?></b>
                                    <br><small class="text-muted"><?php _ph($task['description']); ?></small>
                                </td>
                                <td>
                                    <code class="<?php echo (stripos($task['schedule_description'], 'Error parsing') !== false) ? 'text-danger' : 'text-secondary'; ?>"><?php _p($task['schedule_description']); ?></code>
                                </td>
                                <td>
                                    <span class="fs-6">
                                        <?php _ph($task['last_run']); ?>
                                    </span>
                                </td>
                                <td class="fs-6">
                                    <?php _ph($task['next_execution_status']); ?>
                                </td>
                                <td class="fs-6"><?php echo isset($task['duration']) ? _ph($task['duration']) : '-'; ?></td>
                                <td class="fs-6">
                                    <?php if ($task['has_validation_error']) { ?>
                                        <span class="text-danger">Invalid schedule</span>
                                    <?php } else { ?>
                                        <?php _ph($task['actions']); ?>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Job Executions Section -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>Job Execution History</h5>
            <div class="filters">
                <select class="form-select form-select-sm d-inline-block w-auto me-2" id="jobNameFilter" onchange="filterByJobName()">
                    <option value="all">All Jobs</option>
                    <?php if (isset($jobs_names) && !empty($jobs_names)): ?>
                        <?php foreach ($jobs_names as $job_name): ?>
                            <option value="<?php echo htmlspecialchars($job_name); ?>">
                                <?php echo htmlspecialchars($job_name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearJobFilter()">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </button>
            </div>
        </div>
    
        <div class="card-body">
            <?php if (isset($executions_table_html)): ?>
                <?php echo $executions_table_html; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> No executions found in the database.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
