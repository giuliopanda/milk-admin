<?php
use MilkCore\Get;
!defined('MILK_DIR') && die();
?>

<div class="container-fluid">
    <!-- Registered APIs Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Registered APIs</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Method</th>
                            <th>Auth Required</th>
                            <th>Permissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($apis)): ?>
                            <?php foreach ($apis as $api): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($api['page']); ?></code></td>
                                    <td><?php echo htmlspecialchars($api['method']); ?></td>
                                    <td><?php echo $api['auth'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                    <td><?php echo $api['permissions'] ? htmlspecialchars($api['permissions']) : '<span class="text-muted">None</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No APIs registered</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- API Logs Section -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">API Logs</h5>
            <div class="filters">
                <select class="form-select form-select-sm d-inline-block w-auto me-2" id="api-filter">
                    <option value="">All APIs</option>
                    <?php foreach ($api_names as $name): ?>
                        <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm d-inline-block w-auto" id="status-filter">
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>
        <div class="card-body">
         
                <?php echo $logs_table_html; ?>
         
        </div>
    </div>
</div>
