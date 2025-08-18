<?php
namespace Modules\Auth;
use MilkCore\Get;
use MilkCore\Permissions;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Access Logs Service
 * Handles the business logic for access logs display and management
 * 
 * @package     Modules
 * @subpackage  Auth
 * 
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @link        https://github.com/giuliopanda/milk-core
 * @version     1.0.0
 */
class AccessLogsService
{
    /**
     * Get access logs data with filters and pagination
     * 
     * @return array Contains table_html, table_id, users_options
     */
    public static function get_access_logs_data() {

        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }
        // ModelList configuration for access logs
        $table_id = 'access_logs_table';
        
        // Create ModelList instance
        $model = new \MilkCore\ModelList('#__access_logs', $table_id);
        
        // Add start_date filter
        $model->add_filter('start_date', function($query, $start_date) {
            if (!empty($start_date)) {
                // Filter by login_time greater than or equal to start_date
                $query->where('DATE(login_time) >= ?', [$start_date]);
            }
        });
        
        // Add end_date filter
        $model->add_filter('end_date', function($query, $end_date) {
            if (!empty($end_date)) {
                // Filter by login_time less than or equal to end_date
                $query->where('DATE(login_time) <= ?', [$end_date]);
            }
        });
        
        // Add user_id filter
        $model->add_filter('user_id', function($query, $user_id) {
            if (!empty($user_id) && is_numeric($user_id)) {
                $query->where('user_id = ?', [$user_id]);
            }
        });
        
        // Configure table structure
        $model->set_limit(20);
        $model->set_order('login_time', 'desc');

        $query = $model->query_from_request();

        $query->select('id, user_id, username, login_time, logout_time, last_activity, session_duration, pages_activity');
        
        // Execute query with JOIN to get username
        $sql_parts = $query->get();
        $rows = Get::db()->get_results(...$sql_parts);
        if (!$rows) {
            $rows = [];
        }
        
        // Get total with JOIN
        $total_sql_parts = $query->get_total();
        $total = Get::db()->get_var(...$total_sql_parts);
        if (!$total) {
            $total = 0;
        }
        
        // Configure list structure
        $list_structure = $model->get_list_structure($rows, 'id');
        $list_structure->delete_column('user_id')
                       ->set_type('pages_activity', 'html');
              
        // Format data only if we have rows
        if (!empty($rows)) {
            $rows = self::format_access_logs_data($rows);
        }
        
        // Configure page info
        $page_info = $model->get_page_info($total);
        $page_info->set_id($table_id)->set_ajax(true);
        
        $table_html = Get::theme_plugin('table', [
            'info' => $list_structure,
            'rows' => $rows,
            'page_info' => $page_info
        ]);

        // Prepare users list for filter
        $users = Get::db()->get_results('SELECT id, username FROM `#__users` WHERE status = 1 ORDER BY username');
        $users_options = ['' => 'All Users'];
        foreach ($users as $user) {
            $users_options[$user->id] = $user->username;
        }
        
        return [
            'table_html' => $table_html,
            'table_id' => $table_id,
            'users_options' => $users_options
        ];
    }

    /**
     * Format access logs data for display
     * 
     * @param array $rows Raw data rows from database
     * @return array Formatted rows
     */
    private static function format_access_logs_data($rows) {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }
        return array_map(function($row) {
            $row->username = $row->username . " - id:" . $row->user_id;
            
            // Format pages_activity column
            if (!empty($row->pages_activity) && $row->pages_activity !== '{}') {
                $pages_count = count(json_decode($row->pages_activity, true));
                $row->pages_activity = "<button class='btn btn-sm btn-primary js-show-page-activity' data-log-id='{$row->id}' data-pages-data='" . htmlspecialchars($row->pages_activity, ENT_QUOTES) . "'>{$pages_count} pages visited</button>";
            } else {
                $row->pages_activity = '<span class="text-muted">No pages tracked</span>';
            }
            
            // Handle DateTime objects for login_time
            if ($row->login_time instanceof \DateTime) {
                $row->login_time = $row->login_time->format('Y-m-d H:i:s');
            }
            
            // Handle logout_time logic
            if ($row->logout_time instanceof \DateTime) {
                $row->logout_time = $row->logout_time->format('Y-m-d H:i:s');
            } elseif ($row->logout_time) {
                // logout_time is already set as string, keep it
            } else {
                // No logout_time set, check if session is inactive for 4+ hours
                if ($row->last_activity) {
                    $last_activity_time = $row->last_activity instanceof \DateTime 
                        ? $row->last_activity 
                        : new \DateTime($row->last_activity);
                    
                    $four_hours_ago = new \DateTime('-4 hours');
                    
                    if ($last_activity_time < $four_hours_ago) {
                        // Session inactive for 4+ hours, use last_activity as logout_time
                        $row->logout_time = $last_activity_time->format('Y-m-d H:i:s');
                    } else {
                        // Session still active or inactive for less than 4 hours
                        $row->logout_time = null;
                    }
                }
            }
            
            return $row;
        }, $rows);
    }

    /**
     * Format page activity data for display in offcanvas
     * 
     * @param string $pages_data JSON string containing page activity data
     * @return array Response with success status and HTML content or error
     */
    public static function format_page_activity($pages_data) {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['success' => false, 'error' => 'No page data provided']);
            return;
        }
        if (empty($pages_data)) {
            return ['success' => false, 'error' => 'No page data provided'];
        }
        
        try {
            $pages_activity = json_decode($pages_data, true);
            if (!$pages_activity) {
                throw new \Exception('Invalid JSON data');
            }
            
            $html_content = '<div class="container-fluid">';
            $html_content .= '<div class="row mb-3">';
            $html_content .= '<div class="col-12">';
            $html_content .= '<h6 class="text-muted mb-3"><i class="bi bi-clock-history me-2"></i>Session Page Activity Details</h6>';
            $html_content .= '</div>';
            $html_content .= '</div>';
            
            if (empty($pages_activity)) {
                $html_content .= '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No page activity recorded for this session.</div>';
            } else {
                $html_content .= '<div class="row g-3">';
                
                foreach ($pages_activity as $page_key => $page_data) {
                    $visit_count = $page_data['visit_count'] ?? 1;
                    $display_path = preg_replace('/\/+/', '/', $page_key); // Replace multiple slashes with single
                    
                    $html_content .= '<div class="col-12">';
                    $html_content .= '<div class="card border-0 shadow-sm">';
                    $html_content .= '<div class="card-body p-3">';
                    $html_content .= '<div class="d-flex flex-column">';
                    
                    // Main row with path and visit count
                    $html_content .= '<div class="d-flex align-items-center justify-content-between mb-2">';
                    $html_content .= '<div class="flex-grow-1">';
                    $html_content .= '<h6 class="card-title mb-1"><i class="bi bi-file-text me-2 text-primary"></i><code class="text-dark">' . htmlspecialchars($display_path) . '</code></h6>';
                    $html_content .= '<small class="text-muted"><i class="bi bi-eye me-1"></i>Visited ' . $visit_count . ' time' . ($visit_count > 1 ? 's' : '') . '</small>';
                    $html_content .= '</div>';
                    $html_content .= '</div>';
                    
                    // Date row
                    if (!empty($page_data['first_access'])) {
                        $html_content .= '<div class="border-top pt-2">';
                        $html_content .= '<small class="text-muted"><i class="bi bi-clock me-1"></i>First: ' . Get::format_date($page_data['first_access'], 'datetime');
                        
                        if (!empty($page_data['last_access']) && $page_data['last_access'] !== $page_data['first_access']) {
                            $html_content .= '  <i class="bi bi-clock-fill me-1"></i>Last: ' . Get::format_date($page_data['last_access'], 'datetime');
                        }
                        $html_content .= '</small>';
                        $html_content .= '</div>';
                    }
                    
                    $html_content .= '</div>';
                    $html_content .= '</div>';
                    $html_content .= '</div>';
                    $html_content .= '</div>';
                }
                
                $html_content .= '</div>';
            }
            
            $html_content .= '</div>';
            
            return ['success' => true, 'html' => $html_content];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error processing page activity data: ' . $e->getMessage()];
        }
    }
}