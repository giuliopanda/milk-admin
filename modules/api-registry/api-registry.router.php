<?php
namespace Modules\ApiRegistry;

use MilkCore\AbstractRouter;
use MilkCore\Get;
use MilkCore\ModelList;
use MilkCore\API    ;


!defined('MILK_DIR') && die(); // Prevent direct access

class ApiRegistryRouter extends AbstractRouter {
    public $controller;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Default action when no specific action is provided in the URL.
     * Also handles ?action=list
     */
    protected function action_home() {
        
        // Check if it's an AJAX request for the logs table
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            $logs_table_html = $this->generate_logs_table();
            Get::response_json([
                'success' => true,
                'html' => $logs_table_html
            ]);
            return;
        }
        
        // Generate the logs table for normal rendering
        $logs_table_html = $this->generate_logs_table();
        
        // Get distinct API names for the filter dropdown
        $api_names = $this->model->get_distinct_api_names();
        
        $apis = API::list_endpoints();
        //var_dump ($api);
        //die;
        
        Get::theme_page('default', __DIR__ . '/views/list.page.php', [
            'title' => $this->title . ' - List', 
            'logs_table_html' => $logs_table_html,
            'api_names' => $api_names,
            'apis' => $apis
        ]);
    }

    /**
     * Alias for action_home to explicitly handle ?action=list
     */
    protected function action_list() {
        $this->action_home();
    }

    /**
     * Generate the API logs table
     */
    private function generate_logs_table() {
        $table_id = 'logs-table'; // Updated table ID to match JS and view
        
        // Create ModelList instance
        $model_list = new ModelList($this->model->get_table_name(), $table_id);

        // Add filter for API name
        $model_list->add_filter('api', function($query, $apiName) {
            if (!empty($apiName)) {
                $query->where('api_name = ?', [$apiName]);
            }
        });

        // Add filter for status
        $model_list->add_filter('status', function($query, $status) {
            if (!empty($status)) {
                $query->where('response_status = ?', [$status]);
            }
        });
        
        // Get query from request (this will now apply the registered filters)
        $query = $model_list->query_from_request();
        
        // Execute query
        $logs = $this->model->get(...$query->get());
        $total = $this->model->total();
        
        // Get page info
        $page_info = $model_list->get_page_info($total);
        $page_info->set_id($table_id); // Ensure this uses the correct ID
        
        // Get list structure
        $list_structure = $model_list->get_list_structure();
        
        // Set column types and attributes
        $list_structure->set_column('id', 'ID', 'text')
            ->set_column('api_name', 'API Name', 'html')
            ->set_column('caller_ip', 'Caller IP', 'text')
            ->set_column('execution_time', 'Execution Time', 'html')
            ->set_column('duration', 'Duration', 'html')
            ->set_column('response_status', 'Status', 'html');
        $list_structure->delete_columns(['request_data', 'response_data','started_at', 'completed_at']);
        
        // Format status cells
        if ($logs) {
            foreach ($logs as &$log) {
                // Rendi il nome API cliccabile per mostrare i dettagli
                $log->api_name = '<span class="js-show-info link-action" data-id="' . $log->id . '">' . 
                                 htmlspecialchars($log->api_name) . '</span>';
                
                $log->response_status = $this->format_status($log->response_status);
                $log->duration = $this->format_duration($log->completed_at, $log->started_at);
                $log->execution_time = $this->format_execution_time($log);
                if ($log->user_id == 0) {
                    $log->user_id = '-';
                }
            }
        }
       
        // Generate table HTML using theme plugin
        return Get::theme_plugin('table', [
            'info' => $list_structure,
            'rows' => $logs,
            'page_info' => $page_info
        ]);

    }

    /**
     * Get log details for a specific API execution
     */
    protected function action_get_log_details() {
        if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
            Get::response_json(['success' => false, 'msg' => 'No ID provided']);
            return;
        }

        $id = $_REQUEST['id'];
        $log = $this->model->get_by_id($id);

        if (!$log) {
            Get::response_json(['success' => false, 'msg' => 'Log not found']);
            return;
        }

        // Calcola la durata se disponibile
        $duration = '-';
        if (is_a($log->started_at, 'DateTime') && is_a($log->completed_at, 'DateTime')) {
            $duration_seconds = $log->completed_at->getTimestamp() - $log->started_at->getTimestamp();
            $duration = $this->format_duration($log->completed_at, $log->started_at);
        }

        // Formatta i dati di richiesta e risposta
        $request_data_formatted = $this->format_json_data($log->request_data);
        $response_data_formatted = $this->format_json_data($log->response_data);

        // Formatta i dati per la visualizzazione
        $data = [
            'title' => 'API Log Details: ' . htmlspecialchars($log->api_name),
            'html' => '<div class="p-3">' .
                    '<div class="row mb-3">' .
                        '<div class="col-md-6"><strong>API Name:</strong> ' . htmlspecialchars($log->api_name) . '</div>' .
                        '<div class="col-md-6"><strong>Status:</strong> ' . $this->format_status($log->response_status) . '</div>' .
                    '</div>' .
                    '<div class="row mb-3">' .
                        '<div class="col-md-6"><strong>Caller IP:</strong> ' . htmlspecialchars($log->caller_ip) . '</div>' .
                        '<div class="col-md-6"><strong>User ID:</strong> ' . ($log->user_id > 0 ? $log->user_id : 'Anonymous') . '</div>' .
                    '</div>' .
                    '<div class="row mb-3">' .
                        '<div class="col-md-6"><strong>Started:</strong> ' . ($log->started_at ? Get::format_date($log->started_at, 'datetime') : 'Not started') . '</div>' .
                        '<div class="col-md-6"><strong>Completed:</strong> ' . ($log->completed_at ? Get::format_date($log->completed_at, 'datetime') : 'Not completed') . '</div>' .
                    '</div>' .
                    '<div class="mb-3"><strong>Duration:</strong> ' . $duration . '</div>' .
                    '<hr>' .
                    '<div class="mb-3">' .
                        '<strong>Request Data:</strong>' .
                        '<div class="mt-2 p-3 bg-light rounded">' . $request_data_formatted . '</div>' .
                    '</div>' .
                    '<div class="mb-3">' .
                        '<strong>Response Data:</strong>' .
                        '<div class="mt-2 p-3 bg-light rounded">' . $response_data_formatted . '</div>' .
                    '</div>' .
                    '</div>',
            'success' => true
        ];

        Get::response_json($data);
    }

    /**
     * Format JSON data for display
     */
    private function format_json_data($data) {
        if (empty($data)) {
            return '<span class="text-muted">No data</span>';
        }
        
        // Se è già un array/oggetto, convertilo in JSON
        if (is_array($data) || is_object($data)) {
            $json_string = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            // Se è una stringa, prova a decodificarla e ricodificarla per formattarla
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json_string = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                $json_string = $data;
            }
        }
        
        return '<pre class="mb-0 text-sm">' . htmlspecialchars($json_string) . '</pre>';
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
     * Format duration
     */
    private function format_duration($completed_at, $started_at) {
        if (empty($completed_at) || empty($started_at) || !is_a($completed_at, 'DateTime') || !is_a($started_at, 'DateTime')) {
            return '-';
        }
      
        $duration = $completed_at->getTimestamp() - $started_at->getTimestamp();
        if ($duration < 1) {
            return '< 1 sec';
        } elseif ($duration < 60) {
            return round($duration, 2) . ' sec';
        } elseif ($duration < 3600) {
            return round($duration / 60, 2) . ' min';
        } else {
            return round($duration / 3600, 2) . ' hours';
        }
    }
    
}
