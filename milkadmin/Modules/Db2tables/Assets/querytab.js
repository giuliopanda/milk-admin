/**
 * QUERY TAB
 */
document.getElementById('executeQuery')?.addEventListener('click', async function() {
    const query = document.getElementById('sqlQuery').value;
    const resultsDiv = document.getElementById('queryResults');
    
    try {
        const response = await fetch('?page=db2tables&action=execute-query', {
            method: 'POST',
            credentials: 'same-origin',
            body: JSON.stringify({
                query: query,
                table: '<?php echo htmlspecialchars($table_name); ?>'
            })
        });

        const data = await response.json();
        
        if (data.error) {
            resultsDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            return;
        }

        // Clear previous results
        resultsDiv.innerHTML = '';
        
        // Check if we have multiple queries
        if (data.queryResults) {
            // Process each query result
            data.queryResults.forEach((queryResult, index) => {
                // Create a container for each query result
                const resultContainer = document.createElement('div');
                resultContainer.className = 'mb-4';
                
                // Add query text as a header only if there are multiple queries
                if (data.queryResults.length > 1) {
                    const queryHeader = document.createElement('div');
                    queryHeader.className = 'alert alert-secondary';
                    queryHeader.innerHTML = `<strong>Query ${index + 1}:</strong> <code>${queryResult.query}</code>`;
                    resultContainer.appendChild(queryHeader);
                }
                
                // Check if there's an error for this query
                if (queryResult.error) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.textContent = queryResult.error;
                    resultContainer.appendChild(errorDiv);
                }
                // Check if it's a SELECT query with results
                else if (queryResult.isSelect && queryResult.results) {
                    // Create a unique ID for this result table
                    const tableId = `query-result-${index}`;
                    const paginationId = `pagination-${index}`;
                    const queryId = `query-${index}`;
                    
                    // Create container for the table and pagination
                    let tableContainer = document.createElement('div');
                    tableContainer.className = 'query-result-container';
                    tableContainer.id = queryId;
                    tableContainer.dataset.tableId = tableId;
                    tableContainer.dataset.paginationId = paginationId;
                    tableContainer.dataset.queryIndex = index;
                    tableContainer.dataset.query = queryResult.query;
                    
                    // Store the full result set in a data attribute
                    tableContainer.dataset.fullResults = JSON.stringify(queryResult.results);
                    
                    // Create table for SELECT results
                    let tableHtml = `<div class="table-responsive"><table id="${tableId}" class="table table-striped table-hover"><thead><tr>`;
                    
                    // Headers
                    const headers = Object.keys(queryResult.results[0] || {});
                    headers.forEach(header => {
                        tableHtml += `<th>${header}</th>`;
                    });
                    tableHtml += '</tr></thead><tbody>';
                    
                    // We'll add the rows dynamically with pagination
                    tableHtml += '</tbody></table></div>';
                    
                    // Add pagination controls
                    tableHtml += `
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="text-body-secondary small">
                            ${queryResult.results.length} rows returned
                            ${queryResult.totalCount ? ` (${queryResult.totalCount} total records)` : ''}
                        </div>
                        <nav aria-label="Table pagination">
                            <ul id="${paginationId}" class="pagination pagination-sm mb-0"></ul>
                        </nav>
                    </div>
                    <div class="d-flex align-items-center mt-2">
                        <label for="rows-per-page-${index}" class="form-label me-2 mb-0 small">Rows per page:</label>
                        <select id="rows-per-page-${index}" class="form-select form-select-sm" style="width: auto;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                        </select>
                    </div>`;
                    
                    // Add the table to the result container
                    tableContainer.innerHTML = tableHtml;
                    resultContainer.appendChild(tableContainer);
                    
                    // Initialize pagination after the container is added to the DOM
                    setTimeout(() => {
                        // Get the current limit from the server response or default to 10
                        const currentLimit = queryResult.currentLimit || queryResult.rowsPerPage || 10;
                        
                        // Set the selected option in the dropdown
                        const rowsPerPageSelect = document.getElementById(`rows-per-page-${index}`);
                        if (rowsPerPageSelect) {
                            // Find the option that matches the current limit
                            const option = Array.from(rowsPerPageSelect.options).find(opt => parseInt(opt.value) === currentLimit);
                            
                            // If found, select it
                            if (option) {
                                rowsPerPageSelect.value = option.value;
                            } else if (currentLimit > 0) {
                                // If not found but we have a valid limit, add a new option
                                const newOption = document.createElement('option');
                                newOption.value = currentLimit;
                                newOption.textContent = currentLimit;
                                rowsPerPageSelect.appendChild(newOption);
                                rowsPerPageSelect.value = currentLimit;
                            }
                        }
                        
                        // Initialize with server-side pagination
                        initTablePagination(
                            tableContainer, 
                            queryResult.results, 
                            headers, 
                            1, // Start at page 1
                            currentLimit, // Use the limit from the server
                            queryResult.totalCount // Total records for pagination
                        );
                        
                        // Add event listener for rows per page change
                        rowsPerPageSelect.addEventListener('change', async function() {
                            const rowsPerPage = parseInt(this.value);
                            const queryContainer = document.getElementById(queryId);
                            
                            // Always fetch new data from server when changing rows per page
                            await fetchPageData(queryContainer, 1, rowsPerPage);
                        });
                    }, 0);
                }
                // Empty SELECT result
                else if (queryResult.isSelect && (!queryResult.results || queryResult.results.length === 0)) {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.className = 'alert alert-info';
                    emptyDiv.textContent = 'Query executed successfully. No results returned.';
                    resultContainer.appendChild(emptyDiv);
                }
                // Non-SELECT query
                else {
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success';
                    successDiv.textContent = 'Query executed successfully[2].';
                    resultContainer.appendChild(successDiv);
                }
                
                // Add the result container to the results div
                resultsDiv.appendChild(resultContainer);
            });
        }
         else {
            // Show affected rows for non-SELECT queries
            resultsDiv.innerHTML = `<div class="alert alert-success">Query executed successfully[3].</div>`;
        }
    } catch (error) {
        resultsDiv.innerHTML = `<div class="alert alert-danger">Error executing query: ${error.message}</div>`;
    }
});

/**
 * Initialize table pagination for a query result table
 * @param {HTMLElement} tableContainer - The container element for the table and pagination
 * @param {Array} results - The full result set
 * @param {Array} headers - The table headers
 * @param {number} currentPage - The current page number (1-based)
 * @param {number} rowsPerPage - Number of rows per page
 */
/**
 * Initialize table pagination with server-side data fetching
 * @param {HTMLElement} tableContainer - The container element for the table
 * @param {Array} results - The current page results
 * @param {Array} headers - The column headers
 * @param {number} currentPage - The current page number (0-based for server, 1-based for display)
 * @param {number} rowsPerPage - Number of rows per page
 * @param {number} totalCount - Total number of records in the database
 */
async function initTablePagination(tableContainer, results, headers, currentPage, rowsPerPage, totalCount) {
    // Get table and pagination elements
    const tableId = tableContainer.dataset.tableId;
    const paginationId = tableContainer.dataset.paginationId;
    const queryIndex = tableContainer.dataset.queryIndex;
    const query = tableContainer.dataset.query;
    const tableBody = document.querySelector(`#${tableId} tbody`);
    const paginationElement = document.getElementById(paginationId);
    
    if (!tableBody || !paginationElement) return;
    
    // If totalCount is not provided, use the length of results
    const totalRows = totalCount || results.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    
    // Ensure current page is valid (1-based for display)
    const displayPage = Math.min(Math.max(1, currentPage), Math.max(1, totalPages));
    
    // Clear existing content
    tableBody.innerHTML = '';
    paginationElement.innerHTML = '';
    
    // Update the current page in the container's dataset
    tableContainer.dataset.currentPage = displayPage;
    tableContainer.dataset.rowsPerPage = rowsPerPage;
    
    // Populate table with rows for the current page
    if (results && results.length > 0) {
        results.forEach(row => {
            const tr = document.createElement('tr');
            
            headers.forEach(header => {
                const td = document.createElement('td');
                td.innerHTML = row[header] === null ? '<em>NULL</em>' : row[header];
                tr.appendChild(td);
            });
            
            tableBody.appendChild(tr);
        });
    } else {
        // No results
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = headers.length;
        td.className = 'text-center';
        td.textContent = 'No results found';
        tr.appendChild(td);
        tableBody.appendChild(tr);
    }
    
    // Update the row count display
    const rowCountDiv = tableContainer.querySelector('.text-body-secondary.small');
    if (rowCountDiv) {
        rowCountDiv.innerHTML = `
            ${results.length} rows displayed
            ${totalCount ? ` (${totalCount} total records)` : ''}
            - Page ${displayPage} of ${totalPages || 1}
        `;
    }
    
    // Create pagination controls if there are multiple pages
    if (totalPages > 1) {
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${displayPage === 1 ? 'disabled' : ''}`;
        const prevLink = document.createElement('a');
        prevLink.className = 'page-link';
        prevLink.href = '#';
        prevLink.setAttribute('aria-label', 'Previous');
        prevLink.innerHTML = '<span aria-hidden="true">&laquo;</span>';
        prevLink.addEventListener('click', async function(e) {
            e.preventDefault();
            if (displayPage > 1) {
                await fetchPageData(tableContainer, displayPage - 1, rowsPerPage);
            }
        });
        prevLi.appendChild(prevLink);
        paginationElement.appendChild(prevLi);
        
        // Page number buttons
        // Determine which page numbers to show - we want to show 9 pages
        let startPage = Math.max(1, displayPage - 4);
        let endPage = Math.min(totalPages, startPage + 8);
        
        // Adjust if we're near the end to always show 9 pages if possible
        if (endPage - startPage < 8) {
            startPage = Math.max(1, endPage - 8);
        }
        
        // Add "first page" button if not at the beginning
        if (startPage > 1) {
            const firstPageLi = document.createElement('li');
            firstPageLi.className = 'page-item';
            const firstPageLink = document.createElement('a');
            firstPageLink.className = 'page-link';
            firstPageLink.href = '#';
            firstPageLink.innerHTML = '1...';
            firstPageLink.setAttribute('title', 'Go to first page');
            firstPageLink.addEventListener('click', async function(e) {
                e.preventDefault();
                await fetchPageData(tableContainer, 1, rowsPerPage);
            });
            firstPageLi.appendChild(firstPageLink);
            paginationElement.appendChild(firstPageLi);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === displayPage ? 'active' : ''}`;
            const pageLink = document.createElement('a');
            pageLink.className = 'page-link';
            pageLink.href = '#';
            pageLink.textContent = i;
            pageLink.addEventListener('click', async function(e) {
                e.preventDefault();
                await fetchPageData(tableContainer, i, rowsPerPage);
            });
            pageLi.appendChild(pageLink);
            paginationElement.appendChild(pageLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${displayPage === totalPages ? 'disabled' : ''}`;
        const nextLink = document.createElement('a');
        nextLink.className = 'page-link';
        nextLink.href = '#';
        nextLink.setAttribute('aria-label', 'Next');
        nextLink.innerHTML = '<span aria-hidden="true">&raquo;</span>';
        nextLink.addEventListener('click', async function(e) {
            e.preventDefault();
            if (displayPage < totalPages) {
                await fetchPageData(tableContainer, displayPage + 1, rowsPerPage);
            }
        });
        nextLi.appendChild(nextLink);
        paginationElement.appendChild(nextLi);
    }
}

/**
 * Fetch data for a specific page from the server
 * @param {HTMLElement} tableContainer - The container element for the table
 * @param {number} page - The page number to fetch (1-based)
 * @param {number} rowsPerPage - Number of rows per page
 */
async function fetchPageData(tableContainer, page, rowsPerPage) {
    try {
        // Show loading indicator
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'text-center my-3 loading-indicator';
        loadingDiv.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
        
        // Remove any existing loading indicator
        const existingLoader = tableContainer.querySelector('.loading-indicator');
        if (existingLoader) {
            existingLoader.remove();
        }
        
        tableContainer.appendChild(loadingDiv);
        
        // Get the original query and query index
        const originalQuery = tableContainer.dataset.query;
        const queryIndex = tableContainer.dataset.queryIndex;
        
        // Convert to 0-based page for the server (server expects page 0 for the first page)
        const serverPage = page - 1;
        
        // Execute only this query with pagination parameters
        const response = await fetch('?page=db2tables&action=execute-query', {
            method: 'POST',
            credentials: 'same-origin',
            body: JSON.stringify({
                query: originalQuery,
                rowsPerPage: rowsPerPage,
                page: serverPage,
                queryId: parseInt(queryIndex)
            })
        });
        
        const data = await response.json();
        
        // Remove loading indicator
        loadingDiv.remove();
        
        if (data.error) {
            console.error('Error executing query:', data.error);
            return;
        }
        
        // If we have query results, update the table
        if (data.queryResults && data.queryResults.length > 0) {
            const queryResult = data.queryResults[0];
            if (queryResult.isSelect && queryResult.results) {
                // Get the headers from the first result
                const headers = Object.keys(queryResult.results[0] || {});
                
                // Update the pagination
                initTablePagination(
                    tableContainer, 
                    queryResult.results, 
                    headers, 
                    page, 
                    rowsPerPage, 
                    queryResult.totalCount
                );
            }
        }
    } catch (error) {
        console.error('Error fetching page data:', error);
        
        // Remove loading indicator if it exists
        const loadingDiv = tableContainer.querySelector('.loading-indicator');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }
}

// Handle Export CSV button click
document.getElementById('exportCsvBtn')?.addEventListener('click', function(e) {
    e.preventDefault(); // Prevent default link behavior
    
    // Get the query from the textarea
    const query = document.getElementById('sqlQuery').value;
    
    if (!query) {
        // If no query, use the default export (entire table)
        window.location.href = this.href;
        return;
    }
    
    // Check if there's an active query result
    const resultsDiv = document.getElementById('queryResults');
    const queryResultContainer = resultsDiv.querySelector('.query-result-container');
    
    // If there's a result container, use that query instead of the textarea
    const finalQuery = queryResultContainer ? queryResultContainer.dataset.query : query;
    
    // Redirect to export-csv with the query parameter
    window.location.href = this.href + '&query=' + encodeURIComponent(finalQuery);
});

// Handle Export SQL button click
document.getElementById('exportSqlBtn')?.addEventListener('click', async function() {
    const query = document.getElementById('sqlQuery').value;
    const exportBtn = this; // Reference to the export button
    
    if (!query) {
        window.toasts.show('Please enter a SQL query to export', 'warning');
        return;
    }
    
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Get the token from the execute button
    const token = document.getElementById('executeQuery')?.getAttribute('data-token');
    
    // Get the table name from the URL or default to 'query'
    const tableName = urlParams.get('table') || 'query';
    
    // Disable button and show downloading message
    document.getElementById('exportBtns').style.display = 'none';
    document.getElementById('exportBtnsDownloading').style.display = 'block';
    
    try {
        // Use fetch to send the query to the server
        const response = await fetch('?page=db2tables&action=export-sql', {
            method: 'POST',
            credentials: 'same-origin',
            body: JSON.stringify({
                query: query,
                table: tableName
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            // Show error message
            window.toasts.show(data.error || 'Error generating SQL export', 'danger');
            return;
        }
        
        // Create a Blob with the SQL content
        const blob = new Blob([data.sql], { type: 'text/plain' });
        
        // Create a URL for the blob
        const url = URL.createObjectURL(blob);
        
        // Create a temporary link element
        const a = document.createElement('a');
        a.href = url;
        
        // Set the filename
        a.download = data.filename || `${tableName}_export_${new Date().toISOString().slice(0, 10)}.sql`;
        
        // Append the link to the body
        document.body.appendChild(a);
        
        // Click the link
        a.click();
        
        // Remove the link
        document.body.removeChild(a);
        
        // Release the URL object
        URL.revokeObjectURL(url);
    } catch (error) {
        // Show error message if something went wrong
        console.error('Error generating SQL export:', error);
        window.toasts.show('Error generating SQL export: ' + error.message, 'danger');
    } finally {
        // Re-enable button and restore original text regardless of success or failure
        document.getElementById('exportBtns').style.display = 'flex';
        document.getElementById('exportBtnsDownloading').style.display = 'none';
    }
});

// Handle Create View button click
document.getElementById('createViewBtn')?.addEventListener('click', function() {
    const query = document.getElementById('sqlQuery').value;
    
    if (!query || !query.toLowerCase().includes('select')) {
        window.toasts.show('Please enter a valid SELECT query to create a view.', 'warning');
        return;
    }
    
    // Show a modal to enter view name
    const viewName = prompt('Enter a name for the new view:');
    
    if (!viewName) return; // User cancelled
    
    // Create a CREATE VIEW statement
    const createViewQuery = `CREATE VIEW ${viewName} AS ${query}`;
    
    // Execute the CREATE VIEW query
    fetch('?page=db2tables&action=execute-query', {
        method: 'POST',
        credentials: 'same-origin',
        body: JSON.stringify({
            query: createViewQuery
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            window.toasts.show('Error creating view: ' + data.error, 'danger');
        } else {
            window.toasts.show('View created successfully!', 'success');
            
            // Reload the page to update the sidebar with the new view
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        window.toasts.show('Error creating view: ' + error.message, 'danger');
    });
});


window.addEventListener('load', function() {
    const mysqlInstructions = [
        'SELECT', 'FROM', 'WHERE', 'JOIN', 'INSERT', 'UPDATE', 'DELETE',
        'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT',
        'AND', 'OR', 'NOT', 'IN', 'LIKE', 'BETWEEN',
        'COUNT', 'SUM', 'AVG', 'MAX', 'MIN',
        'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'CROSS JOIN',
        'CONCAT', 'UPPER', 'LOWER', 'SUBSTRING', 'TRIM',
        'NOW', 'CURDATE', 'DATE_FORMAT', 'DATEDIFF', 'MONTH', 'YEAR', 'DAY',
        'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE', 'TRUNCATE TABLE',
        'CREATE DATABASE', 'DROP DATABASE', 'USE',
        'ROUND', 'ABS', 'FLOOR', 'CEILING',
        'CASE', 'IF', 'IFNULL', 'COALESCE', 'NULLIF',
        'START TRANSACTION', 'COMMIT', 'ROLLBACK',
        'GRANT', 'REVOKE', 'CREATE USER',
        'SHOW TABLES', 'SHOW DATABASES', 'EXPLAIN',
        'UNION', 'UNION ALL', 'EXISTS', 'IS NULL', 'IS NOT NULL'
      ]
      
      if (typeof window.allTables === 'undefined' || 
        typeof window.allFields === 'undefined' || 
        !document.getElementById('sqlQuery')) {
            console.log('allTables', window.allTables);
            console.log('allFields', window.allFields);
            console.log('sqlQuery', document.getElementById('sqlQuery'));
        // Exit early if any required element or variable is missing
        console.log('SQL suggestion system not initialized: missing required variables or elements');
        return;
    } else{
        const suggestions = {
        keywords: mysqlInstructions,
        tables: window.allTables,
        fields: window.allFields
        };
        console.log(suggestions);

        // Inizializza il sistema di suggerimenti
        const textarea = document.getElementById('sqlQuery');
        const options = {
            maxHeight: 200,
            addQuotes: true
        };
        
        const suggestionSystem = new SuggestionSystem(textarea, suggestions, options);
    }
});
