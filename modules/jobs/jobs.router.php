<?php
namespace Modules\Jobs;

use MilkCore\AbstractRouter;
use MilkCore\Route;
use MilkCore\Get;
use MilkCore\Token;
use MilkCore\Permissions;


!defined('MILK_DIR') && die(); // Prevent direct access

class JobsRouter extends AbstractRouter {
    public $controller; // Changed visibility to public

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(); // AbstractController will call set_handle_routes
    }

   /**
     * Default action when no specific action is provided in the URL.
     * Also handles ?action=list
     */
    protected function action_home() {
        $data = JobsServices::display_jobs_list();
        // data = ['jobs_tasks', 'sync_message', 'debug_info']
        
        // Check if it's an AJAX request for the executions table
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            $this->generate_executions_table_ajax();
            return;
        }
        
        // Generate the executions table for normal rendering
        $executions_table_html = $this->generate_executions_table();
        
        // Get distinct job names for the filter dropdown
        $jobs_names = $this->get_distinct_jobs_names();
        
        Get::theme_page('default', __DIR__ . '/views/list.page.php', [
            'title' => $this->title . ' - List', 
            'executions_table_html' => $executions_table_html,
            'jobs_names' => $jobs_names,
            ...$data
        ]);
    }

    /**
     * Alias for action_home to explicitly handle ?action=list
     */
    protected function action_list() {
        $this->action_home();
    }
    
    /**
     * Handle manual execution of a jobs
     * Accessed via ?action=run&name=jobs_name
     */
    protected function action_run() {
        Permissions::check_json('jobs.manage');
      
        if (!isset($_POST['name']) || empty($_POST['name'])) {
            // Redirect to list if no jobs name provided
            Get::response_json([
                'success' => false,
                'msg' => 'Jobs name not provided'
            ]);
        }
        
        $jobs_name = $_POST['name'];
        // This is used to reactivate blocked jobs // if it's running, the job must be blocked first
        $ris = $this->model->get_latest_executions($jobs_name);
        if ($ris && $ris->status == 'blocked') {
            $this->model->delete($ris->id);
        }
        
        JobsContract::run($jobs_name);
        
        // Set a message based on the result
        Get::response_json([
            'success' => true
        ]);

    }
    
    /**
     * Block a running job
     * Accessed via ?action=block&name=jobs_name
     */

    protected function action_block() {
        Permissions::check_json('jobs.manage');

        if (!isset($_POST['name']) || empty($_POST['name'])) {
            Get::response_json([
                'success' => false,
                'msg' => 'No jobs name provided'
            ]);
        }
        
        $jobs_name = $_POST['name'];
        
        // Find the ongoing execution
        $running_execution = $this->model->where('jobs_name = ? AND status = ?', [$jobs_name, 'running'])->first();
        
        if ($running_execution) {
            // Mark the current execution as failed
            $running_execution->mark_failed('Manually stopped by user');
            $running_execution->completed_at = Get::date_time_zone()->format('Y-m-d H:i:s');
            $to_save = $running_execution->to_mysql_array();
            $this->model->save($to_save);
            
            JobsContract::set_model($this->model);
            JobsContract::reconcile_jobs_in_database($jobs_name);
            
            Get::response_json([
                'success' => true,
                'msg' => 'Job "' . htmlspecialchars($jobs_name) . '" stopped successfully.'
            ]);
        } else {
            Get::response_json([
                'success' => false,
                'msg' => 'Job "' . htmlspecialchars($jobs_name) . '" is not running.'
            ]);
        }
    }
    
    
    /**
     * Block a pending job
     * Accessed via ?action=block_pending&name=jobs_name
     */
    /**
     * Block a pending job
     * Accessed via ?action=block_pending&name=jobs_name
     */
    protected function action_block_pending() {
        Permissions::check_json('jobs.manage');

        if (!isset($_POST['name']) || empty($_POST['name'])) {
            Get::response_json([
                'success' => false,
                'msg' => 'No jobs name provided'
            ]);
        }
        
        $jobs_name = $_POST['name'];
        
        // Find the pending execution
        $pending_execution = $this->model->get_not_yet_executed_by_jobs_name($jobs_name, false);
        
        if ($pending_execution && $pending_execution->status === 'pending') {
            // Set the status to blocked
            $pending_execution->status = 'blocked';
            $pending_execution->error = 'Manually blocked by user';
            
            $to_save = $pending_execution->to_mysql_array();
            $id = $pending_execution->id;
            unset($to_save['id']);
            
            $save_result = $this->model->save($to_save, $id);
            
            if ($save_result) {
                Get::response_json([
                    'success' => true,
                    'msg' => 'Pending job "' . htmlspecialchars($jobs_name) . '" blocked successfully.'
                ]);
            } else {
                Get::response_json([
                    'success' => false,
                    'msg' => 'Error while blocking the job.'
                ]);
            }
        } else {
            Get::response_json([
                'success' => false,
                'msg' => 'Pending job "' . htmlspecialchars($jobs_name) . '" not found or not in pending state.'
            ]);
        }
    }
    
    /**
     * Execute all due jobs
     * Accessed via ?action=run_all
     */
    /**
     * Execute all due jobs
     * Accessed via ?action=run_all
     */
    protected function action_run_all() {
        Permissions::check_json('jobs.manage');
        
        $model = new JobsExecutionModel();
        $pending_executions = $model->get_pending_executions();
        
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($pending_executions as $execution) {
            $jobs_name = $execution->jobs_name;
            
            // Execute the job using JobsContract::run()
            $result = JobsContract::run($jobs_name);
            
            if ($result !== false) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }
        
        // Set appropriate response
        if (empty($pending_executions)) {
            Get::response_json([
                'success' => true,
                'msg' => 'No due jobs to execute.'
            ]);
        } else {
            $success = $failed_count === 0;
            $msg = '';
            
            if ($success_count > 0) {
                $msg .= $success_count . ' jobs executed successfully.';
            }
            
            if ($failed_count > 0) {
                if ($msg) $msg .= ' ';
                $msg .= $failed_count . ' jobs failed.';
            }
            
            Get::response_json([
                'success' => $success,
                'msg' => $msg
            ]);
        }
    }

    /**
     * Get distinct job names from executions table
     */
    private function get_distinct_jobs_names() {
        $model = new \MilkCore\ModelList('#__jobs_executions');
        $query = $model->query_from_request();
        $query->select('DISTINCT jobs_name')
              ->where('status != ?', ['pending'])
              ->order('jobs_name', 'asc');
        
        $results = Get::db()->get_results(...$query->get());
        
        $jobs_names = [];
        foreach ($results as $row) {
            $jobs_names[] = $row->jobs_name;
        }
        
        return $jobs_names;
    }

    /**
     * Generate the job executions table
     */
    private function generate_executions_table() {
        $table_id = 'jobs-executions-table';
        // Initialize ModelList for the executions table
        $model = new \MilkCore\ModelList('#__jobs_executions', $table_id);
        
        // Add custom filter for job name
        $model->add_filter('jobs_name', function($query, $jobs_name) {
            if (!empty($jobs_name) && $jobs_name !== 'all') {
                $query->where('jobs_name = ?', [$jobs_name]);
            }
        });
        
        // Customize the table structure
        $list_structure = $model->get_list_structure();
        
        // Configure columns
        $list_structure
            ->set_column('checkbox', 'checkbox', 'hidden')
            ->set_column('id', 'ID', 'text')
            ->set_column('jobs_name', 'Job Name', 'html')
            ->set_column('status', 'Status', 'html')
            ->delete_column('scheduled_at')  // Rimuovi scheduled_at
            ->set_column('execution_time', 'Execution Time', 'html')
            ->set_column('duration', 'Duration', 'html')
            ->delete_columns(['started_at', 'completed_at', 'output', 'error', 'metadata', 'result']);    // Hide metadata to avoid cluttering the view
        
        // Set default sorting by ID DESC
        if (!isset($_REQUEST[$table_id]['order_field'])) {
            $_REQUEST[$table_id]['order_field'] = 'id';
            $_REQUEST[$table_id]['order_dir'] = 'desc';
        }
        if ($_REQUEST[$table_id]['order_field'] == 'execution_time' ) {
            $_REQUEST[$table_id]['order_field'] = 'completed_at';
        }
        
        // Build the query based on request parameters
        $query = $model->query_from_request();
        $query->where('status != ?', ['pending']);
        // Retrieve data - Processa i dati prima di mostrarli
        $rows = Get::db()->get_results(...$query->get());
        
        if ($_REQUEST[$table_id]['order_field'] == 'completed_at' ) {
            $model->order_field = 'execution_time';
        }
        // Processa le righe per formattare i dati
        if ($rows) {
            foreach ($rows as &$row) {
                // Formatta lo status con colori
                $status_html = $this->format_status($row->status);
                $row->status = $status_html;
            
                $row->jobs_name = '<span class="js-show-info link-action" data-id="' . $row->id . '"> ' . $row->jobs_name . '</span>';
                // Unifica started_at e completed_at in una singola colonna
                if (!is_null($row->completed_at) && !is_null($row->started_at)) {
                    $row->completed_at = new \DateTime($row->completed_at);
                    $row->started_at = new \DateTime($row->started_at);
                    $row->duration = $row->completed_at->getTimestamp() - $row->started_at->getTimestamp();
                }
                if (isset($row->duration) && $row->duration >= 0) {
                    $row->duration = JobsServices::format_duration($row->duration);
                } else {
                    $row->duration = '-';
                }
                
                // Mostra error se presente, altrimenti output
                if (!empty($row->error)) {
                    $row->result = '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ' . 
                                htmlspecialchars(substr($row->error, 0, 100)) . 
                                (strlen($row->error) > 100 ? '...' : '') . '</span>';
                } elseif (!empty($row->output)) {
                    $row->result = '<span class="text-muted">' . 
                                htmlspecialchars(substr($row->output, 0, 100)) . 
                                (strlen($row->output) > 100 ? '...' : '') . '</span>';
                } else {
                    $row->result = '-';
                }
                $row->execution_time = $this->format_execution_time($row);
            
            }
        }
        
        // Count total records
        $total = Get::db()->get_var(...$query->get_total());
        
        // Configure pagination information
        $page_info = $model->get_page_info($total);
        $page_info->set_pagination(true);
        $page_info->set_ajax(true);
       
        
        // Generate table HTML
        return Get::theme_plugin('table', [
            'info' => $list_structure,
            'rows' => $rows,
            'page_info' => $page_info
        ]);
    }

    /**
     * Handle AJAX requests for the executions table
     */
    private function generate_executions_table_ajax() {
        $table_html = $this->generate_executions_table();
        
        Get::theme_page('json', '', json_encode([
            'html' => $table_html,
            'success' => 'true',
            'msg' => ''
        ]));
    }
    
    /**
     * Format status with colors
     */
    private function format_status($status) {
        $status_classes = [
            'pending' => ['class' => 'badge bg-secondary', 'icon' => 'bi-clock'],
            'running' => ['class' => 'badge bg-primary', 'icon' => 'bi-play-circle-fill'],
            'completed' => ['class' => 'badge bg-success', 'icon' => 'bi-check-circle-fill'],
            'failed' => ['class' => 'badge bg-danger', 'icon' => 'bi-x-circle-fill'],
            'blocked' => ['class' => 'badge bg-warning text-dark', 'icon' => 'bi-slash-circle']
        ];
        
        $config = $status_classes[$status] ?? ['class' => 'badge bg-secondary', 'icon' => 'bi-question-circle'];
        
        return '<span class="' . $config['class'] . '"><i class="bi ' . $config['icon'] . ' me-1"></i>' . 
               ucfirst($status) . '</span>';
    }
    
    /**
     * Format execution time combining started_at and completed_at
     */
    private function format_execution_time($row) {
        $html = '<div class="small">';
        
        if (!empty($row->started_at)) {
            $html .= '<div><strong>Start:</strong> ' . Get::format_date($row->started_at, 'datetime') . '</div>';
        }
        
        if (!empty($row->completed_at)) {
            $html .= '<div><strong>End:</strong> ' . Get::format_date($row->completed_at, 'datetime') . '</div>';
        }
        
        if (empty($row->started_at) && empty($row->completed_at)) {
            $html .= '<span class="text-muted">Not executed</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    
    /**
     * Get log details for a specific execution
     */
    protected function action_get_log_details() {
        if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
            Get::response_json(['success' => false, 'msg' => 'No ID provided']);
            return;
        }

        $id = $_REQUEST['id'];
        $execution = $this->model->get_by_id($id);

        if (!$execution) {
            Get::response_json(['success' => false, 'msg' => 'Log not found']);
            return;
        }
       

        if (is_a($execution->started_at, 'DateTime') && is_a($execution->completed_at, 'DateTime')) {
            $duration = $execution->completed_at->getTimestamp() - $execution->started_at->getTimestamp();
            $duration = JobsServices::format_duration($duration);
        } else {
            $duration = '-';
        }

        // Format the data for display
        $data = [
            'title' => 'Job Log Details: ' . htmlspecialchars($execution->jobs_name),
            'html' => '<div class="p-3">' .
                      '<div class="mb-3"><strong>Status:</strong> ' . $this->format_status($execution->status) . '</div>' .
                      '<div class="mb-3"><strong>Started:</strong> ' . ($execution->started_at ? Get::format_date($execution->started_at, 'datetime') : 'Not started') . '</div>' .
                      '<div class="mb-3"><strong>Completed:</strong> ' . ($execution->completed_at ? Get::format_date($execution->completed_at, 'datetime') : 'Not completed') . '</div>' .
                      '<div class="mb-3"><strong>Duration:</strong> ' . $duration . '</div>' .
                      (!empty($execution->error) ? 
                          '<div class="mb-3"><strong>Error:</strong><div class="text-danger">' . nl2br(htmlspecialchars($execution->error)) . '</div></div>' : '') .
                      (!empty($execution->output) ? 
                          '<div class="mb-3"><strong>Output:</strong><div class="text-muted">' . nl2br(htmlspecialchars($execution->output)) . '</div></div>' : '') .
                      (!empty($execution->metadata) ? 
                          '<div class="mb-3"><strong>Metadata:</strong><pre class="text-muted">' . htmlspecialchars(json_encode($execution->metadata, JSON_PRETTY_PRINT)) . '</pre></div>' : '') .
                      '</div>',
            'success' => true
        ];

        Get::response_json($data);
    }
}