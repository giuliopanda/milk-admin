<?php
namespace Modules\Auth;

use App\{Get, Permissions, Route, Config};
use Builders\{TableBuilder, SearchBuilder, TitleBuilder};

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
 * @version     1.0.0
 */
class AccessLogsService
{
    /**
     * Get configured TableBuilder for access logs
     *
     * @return TableBuilder Configured table builder instance
     */
    public static function getAccessLogsTableBuilder() {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }

        $model = new AccessLogModel();
        $table_id = 'access_logs_table';

        // Create table with Builders
        $tableBuilder = TableBuilder::create($model, $table_id)
            // Select columns
            ->reorderColumns(['username', 'ip_address', 'login_time', 'logout_time', 'last_activity', 'session_duration','pages_activity'])
            ->orderBy('login_time', 'desc') ->limit(20)
            ->filter('start_date', function($query, $value) {
                $query->where('DATE(login_time) >= ?', [$value]);
            })
            ->filter('end_date', function($query, $value) {
                $query->where('DATE(login_time) <= ?', [$value]);
            })
            ->filter('user_id', function($query, $value) {
                $query->where('user_id = ?', [$value]);  
            })

            // Column customization
            ->hideColumns(['id','user_id', 'session_id', 'user_agent' ])
            ->setType('logout_time', 'html')
            ->column('pages_activity', 'Pages Visited', 'html', [], function($row) {
                $pages_data = $row->pages_activity ?? [];

                if (empty($pages_data) ) {
                    return '<span class="text-body-secondary">No pages tracked</span>';
                }

                $pages_count = count($pages_data);
                return '<a href="' . Route::url('?page=auth&action=format-page-activity&id=' . $row->id) . '" class="btn btn-sm btn-primary" data-fetch="post"> ' . $pages_count . ' pages visited </a>';
            })
            ->setType('session_duration', 'html')
            ->setFn('session_duration', function($row) {
                $end = (empty($row->logout_time)) ? $row->last_activity : $row->logout_time;
                if (empty($end) || empty($row->login_time) ) {
                    return '<span class="text-body-secondary">Active</span>';
                }

                return AccessLogsService::formatSessionDuration($row->login_time, $end);
            });

        return $tableBuilder;
    }

    /**
     * Format session duration between login and end time
     * 
     * @param \DateTimeInterface $loginTime Login time
     * @param \DateTimeInterface $endTime End time
     * @return string Formatted session duration
     */
    static public function formatSessionDuration(\DateTimeInterface $loginTime, \DateTimeInterface $endTime): string
    {
        // Calcolo durata in secondi in modo semplice e prevedibile
        $duration = $endTime->getTimestamp() - $loginTime->getTimestamp(); // [web:34]

        // Gestione durata negativa (date invertite o input errati)
        if ($duration < 0) {
            return '<span class="text-danger">Invalid</span>'; // [web:31]
        }

        if ($duration < 60) {
            return $duration . 's'; // [web:29]
        }

        if ($duration < 3600) {
            $minutes = intdiv($duration, 60);
            $seconds = $duration % 60;
            return $minutes . 'm ' . $seconds . 's'; // [web:29]
        }

        $hours = intdiv($duration, 3600);
        $minutes = intdiv($duration % 3600, 60);
        return $hours . 'h ' . $minutes . 'm'; // [web:29]
    }

    /**
     * Get configured SearchBuilder for access logs filters
     *
     * @param string $table_id Table identifier
     * @param array $users_options User options for dropdown
     * @return SearchBuilder Configured search builder instance
     */
    public static function getSearchBuilder($table_id, $users_options) {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }

        return SearchBuilder::create($table_id)
            ->addInput('date', 'start_date', 'Start Date', $_REQUEST['start_date'] ?? '')
            ->addInput('date', 'end_date', 'End Date', $_REQUEST['end_date'] ?? '')
            ->addSelect('user_id', 'Filter by User', $users_options, $_REQUEST['user_id'] ?? '')
            ->setSearchMode('submit')
            ->setWrapperClass('d-flex align-items-end gap-3 flex-wrap mb-3');
    }

    /**
     * Get users options for filter dropdown
     *
     * @return array Array of user options [id => username]
     */
    public static function getUsersOptions() {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }

        $users = Get::db()->getResults('SELECT id, username FROM `#__users` WHERE status = 1 ORDER BY username');
        $users_options = ['' => 'All Users'];
        foreach ($users as $user) {
            $users_options[$user->id] = $user->username;
        }
        return $users_options;
    }

    /**
     * Get configured TitleBuilder for access logs page
     *
     * @param string $search_html HTML content for search/filters area
     * @return TitleBuilder Configured title builder instance
     */
    public static function getTitleBuilder($search_html) {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }

        return TitleBuilder::create('Access Logs')
            ->description('Monitor user login activity and session tracking')
            ->addRightContent($search_html)
            ->includeMessages(true);
    }


    /**
     * Get active users data (sessions and remember me tokens)
     *
     * @return array Array of active users with session and token counts
     */
    public static function getActiveUsersData() {
        if (!Permissions::check('auth.manage')) {
            return [];
        }

        // Get session expiry time from config
        $session_expiry_minutes = Config::get('auth_expires_session', 120);
        $session_cutoff = date('Y-m-d H:i:s', time() - ($session_expiry_minutes * 60));

        // Get current time for remember me token check
        $now = date('Y-m-d H:i:s');

        // Query to get active sessions grouped by user (exclude guest sessions with user_id = 0)
        $sessions_query = "
            SELECT
                s.user_id,
                COUNT(DISTINCT s.id) as sessions_count,
                MAX(s.session_date) as last_session_activity
            FROM `#__sessions` s
            WHERE s.session_date > ?
            AND s.user_id > 0
            GROUP BY s.user_id
        ";
        $active_sessions = Get::db()->getResults($sessions_query, [$session_cutoff]);

        // Query to get active remember me tokens grouped by user (exclude user_id = 0)
        $tokens_query = "
            SELECT
                rt.user_id,
                COUNT(DISTINCT rt.id) as tokens_count,
                MAX(COALESCE(rt.last_used_at, rt.created_at)) as last_token_activity
            FROM `#__remember_tokens` rt
            WHERE rt.expires_at > ?
            AND rt.is_revoked = 0
            AND rt.user_id > 0
            GROUP BY rt.user_id
        ";
        $active_tokens = Get::db()->getResults($tokens_query, [$now]);

        // Combine data by user_id
        $users_data = [];

        // Add sessions data
        foreach ($active_sessions as $session) {
            $user_id = $session->user_id;
            if (!isset($users_data[$user_id])) {
                $users_data[$user_id] = [
                    'user_id' => $user_id,
                    'sessions_count' => 0,
                    'tokens_count' => 0,
                    'last_activity' => null
                ];
            }
            $users_data[$user_id]['sessions_count'] = $session->sessions_count;
            $users_data[$user_id]['last_activity'] = $session->last_session_activity;
        }

        // Add tokens data
        foreach ($active_tokens as $token) {
            $user_id = $token->user_id;
            if (!isset($users_data[$user_id])) {
                $users_data[$user_id] = [
                    'user_id' => $user_id,
                    'sessions_count' => 0,
                    'tokens_count' => 0,
                    'last_activity' => null
                ];
            }
            $users_data[$user_id]['tokens_count'] = $token->tokens_count;

            // Update last_activity if token activity is more recent
            if (empty($users_data[$user_id]['last_activity']) ||
                $token->last_token_activity > $users_data[$user_id]['last_activity']) {
                $users_data[$user_id]['last_activity'] = $token->last_token_activity;
            }
        }

        // Get user details for all active users
        if (!empty($users_data)) {
            $user_ids = array_keys($users_data);
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));

            $users_query = "
                SELECT id, username, email
                FROM `#__users`
                WHERE id IN ($placeholders)
            ";
            $users = Get::db()->getResults($users_query, $user_ids);

            // Merge user details
            foreach ($users as $user) {
                if (isset($users_data[$user->id])) {
                    $users_data[$user->id]['username'] = $user->username;
                    $users_data[$user->id]['email'] = $user->email;
                }
            }
        }

        // Convert to indexed array and sort by last activity (most recent first)
        $result = array_values($users_data);
        usort($result, function($a, $b) {
            return strcmp($b['last_activity'], $a['last_activity']);
        });

        return $result;
    }

    /**
     * Format page activity data for display in offcanvas
     *
     * @param string $pages_data JSON string containing page activity data
     * @return array Response with success status and HTML content or error
     */
    public static function formatPageActivity($pages_activity) {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['success' => false, 'error' => 'No page data provided']);
            return;
        }
        if (!is_array($pages_activity)) {
            return ['success' => false, 'error' => 'No page data provided'];
        }

        try {
            $html_content = '<div class="container-fluid">';
            $html_content .= '<div class="row mb-3">';
            $html_content .= '<div class="col-12">';
            $html_content .= '<h6 class="text-body-secondary mb-3"><i class="bi bi-clock-history me-2"></i>Session Page Activity Details</h6>';
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
                    $html_content .= '<small class="text-body-secondary"><i class="bi bi-eye me-1"></i>Visited ' . $visit_count . ' time' . ($visit_count > 1 ? 's' : '') . '</small>';
                    $html_content .= '</div>';
                    $html_content .= '</div>';

                    // Date row
                    if (!empty($page_data['first_access'])) {
                        $html_content .= '<div class="border-top pt-2">';
                        $html_content .= '<small class="text-body-secondary"><i class="bi bi-clock me-1"></i>First: ' . Get::formatDate($page_data['first_access'], 'datetime');

                        if (!empty($page_data['last_access']) && $page_data['last_access'] !== $page_data['first_access']) {
                            $html_content .= '  <i class="bi bi-clock-fill me-1"></i>Last: ' . Get::formatDate($page_data['last_access'], 'datetime');
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