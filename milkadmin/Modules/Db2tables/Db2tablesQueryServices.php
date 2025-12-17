<?php
namespace Modules\Db2tables;

use App\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Query execution services for DB2Tables module
 * Handles SQL query execution and result processing
 */
class Db2tablesQueryServices
{
    /**
     * Execute a custom SQL query
     * 
     * @param string $query The SQL query to execute
     * @param string $token The CSRF token for validation
     * @param int|null $queryId Optional specific query ID for pagination
     * @param int|null $rowsPerPage Optional number of rows per page
     * @param int $page Optional page number for pagination (default: 0)
     * @return array Response with query results or error message
     */
    public function executeQuery($query, $queryId = null, $rowsPerPage = null, $page = 0)
    {

        $db2 = Db2tablesServices::getDb();

        try {
            // Parse the query with SQLParser
            $parser = new SQLParser($query);
            
            // Check if parsing was successful
            if ($parser->getQueryCount() === 0) {
                return [
                    'error' => 'Invalid SQL query'
                ];
            }
            
            // Parse all queries and get Query objects or strings
            $parsed_queries = $parser->parse();
            $all_results = [];
            $all_are_select = true;
            
            // If we have a specific queryId, only execute that query
            if ($queryId !== null && is_numeric($queryId) && isset($parsed_queries[$queryId])) {
                $parsed_queries = [$queryId => $parsed_queries[$queryId]];
            }
            
            // Process each query
            foreach ($parsed_queries as $query_index => $parsed_query) {
                // Check if it's a Query object (SELECT) or a string (non-SELECT)
                $isSelect = $parsed_query instanceof \App\Database\Query;

                // Check if it's a PRAGMA query (returns results like SELECT)
                $isPragma = false;
                if (is_string($parsed_query) && preg_match('/^PRAGMA\s+/i', trim($parsed_query))) {
                    $isPragma = true;
                }
                
                // If it's not a SELECT query and not a PRAGMA, mark that not all queries are SELECT
                if (!$isSelect && !$isPragma) {
                    $all_are_select = false;
                }
                
                // Initialize limit for pagination
                $limit = $rowsPerPage ?: 100; // Default to 100 if not specified

                // Get the query string to execute
                if ($isSelect) {
                    // It's a Query object, we can use its methods
                    $offset = 0;

                    // Only modify the LIMIT if we have explicit pagination parameters
                    if ($queryId !== null || $rowsPerPage !== null || $page > 0) {
                        $offset = $page * $limit;
                    }
                    // Check if the query already has a LIMIT
                    if (!$parsed_query->hasLimit()) {
                        // No LIMIT clause, add LIMIT with offset and limit
                        $parsed_query->limit($offset, $limit);
                    } else {
                        // There's a LIMIT clause, replace it with our pagination values
                        $parsed_query->clean('limit');
                        $parsed_query->limit($offset, $limit);
                    }

                    // Get the SQL and parameters
                    list($query_string, $params) = $parsed_query->get();
                } else {
                    // It's a string (non-SELECT query or PRAGMA)
                    $query_string = $parsed_query;
                }
                
                // Execute the query
                $result = $db2->query($query_string);
                
                if ($result === false) {
                    // If one query fails, add error message and continue with next query
                    $all_results[] = [
                        'isSelect' => false,
                        'error' => 'Query execution failed: ' . $db2->last_error,
                        'query' => $query_string
                    ];
                    continue;
                }
                
                if ($isSelect || $isPragma) {
                    $rows = [];

                    while ($row = $result->fetch_array()) {
                            $rows[] = $row;
                    }

                    // Calculate total record count without LIMIT
                    $total_count = 0;
                    try {
                        // Since we're using $parsed_query which is already a Query object for SELECT queries
                        if ($isSelect) {
                            // Use getTotal() to get the count query
                            list($count_sql, $count_params) = $parsed_query->getTotal();
                            $count_result = $db2->query($count_sql);

                            if ($count_result && $count_row = $count_result->fetch_array()) {
                                $total_count = (int)$count_row;
                            }

                        } else {
                            // For PRAGMA and other queries that return results, use row count
                            $total_count = count($rows);
                        }
                    } catch (\Exception $e) {
                        // If count query fails, use the number of rows returned
                        $total_count = count($rows);
                    }

                    // Determina il limite corrente utilizzato nella query
                    $currentLimit = $limit;
                    if ($isSelect && $parsed_query->hasLimit()) {
                        $currentLimit = isset($parsed_query->limit[1]) ? (int)$parsed_query->limit[1] : 100;
                    }

                    $all_results[] = [
                        'isSelect' => true,
                        'results' => $rows,
                        'query' => $query_string,
                        'totalCount' => $total_count,
                        'page' => $page,
                        'rowsPerPage' => $currentLimit,
                        'currentLimit' => $currentLimit,
                        'queryIndex' => $query_index
                    ];
                } else {
                    $all_results[] = [
                        'isSelect' => false,
                        'query' => $query_string,
                        'queryIndex' => $query_index
                    ];
                }
            }
            
            // If we have a specific queryId, include it in the response
            if ($queryId !== null) {
                return [
                    'isSelect' => $all_are_select,
                    'multipleQueries' => count($all_results) > 1,
                    'queryResults' => $all_results,
                    'queryId' => $queryId
                ];
            } else {
                // Return all results
                return [
                    'isSelect' => $all_are_select,
                    'multipleQueries' => count($all_results) > 1,
                    'queryResults' => $all_results
                ];
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Query execution failed: ' . $db2->last_error
            ];
        }
    }
}
