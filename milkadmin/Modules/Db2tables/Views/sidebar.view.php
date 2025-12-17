<?php
// Ensure this file is not accessed directly
!defined('MILK_DIR') && die();

// Extract variables passed from the router
if (!isset($tables)) $tables = [];
if (!isset($views)) $views = [];
if (!isset($active_table)) $active_table = '';

// Count tables and views for display
$tables_count = count($tables);
$views_count = count($views);
?>

<div class="db2-sidebar">
    <div class="sidebar-header">
        <!-- Database Selector -->
        <div class="mb-2">
            <select id="dbSelector" class="form-select form-select-sm">
                <option value="db2" <?php echo (isset($_SESSION['db2tables_db_selection']) && $_SESSION['db2tables_db_selection'] === 'db2') || !isset($_SESSION['db2tables_db_selection']) ? 'selected' : ''; ?>>DB2</option>
                <option value="db1" <?php echo (isset($_SESSION['db2tables_db_selection']) && $_SESSION['db2tables_db_selection'] === 'db1') ? 'selected' : ''; ?>>DB1</option>
            </select>
        </div>

        <div class="input-group  input-group-sm">
            <input type="text" id="tableSearchInput" class="form-control" placeholder="Search table or field..." aria-label="Search tables">
            <span class="input-group-text" id="clearSearchBtn"><i class="bi bi-x"></i></span>
        </div>
    </div>
    
    <div class="sidebar-content" id="tablesList">
        <!-- TABLES SECTION -->
        <div class="sidebar-section">
            <div class="sidebar-section-header db2-tables-header">
                <i class="bi bi-table me-2"></i>
                <span>Tables</span>
                <?php 
                // Filter out views from tables
                $filtered_tables = [];
                $view_names = array_column($views, 'name');
                
                foreach ($tables as $table) {
                    if (!in_array($table['name'], $view_names)) {
                        $filtered_tables[] = $table;
                    }
                }
                $tables_count = count($filtered_tables);
                ?>
                <span class="db2-badge"><?php echo $tables_count; ?></span>
            </div>
            
            <div class="sidebar-section-content">
                <?php if (!empty($filtered_tables)): ?>
                    <ul class="sidebar-menu">
                        <?php foreach ($filtered_tables as $table): ?>
                            <?php 
                            $is_active = ($active_table == $table['name']);
                            $item_class = $is_active ? 'sidebar-menu-item active' : 'sidebar-menu-item';
                            $url = \App\Route::url('?page=db2tables&action=view-table&table=' . urlencode($table['name']));
                            ?>
                            <li class="<?php echo $item_class; ?>">
                                <a href="<?php echo $url; ?>">
                                    <span class="item-text"><?php _p($table['name']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-message">No tables available</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- VIEWS SECTION -->
        <div class="sidebar-section">
            <div class="sidebar-section-header db2-views-header">
                <i class="bi bi-eye me-2"></i>
                <span>Views</span>
                <span class="db2-badge"><?php echo $views_count; ?></span>
            </div>
            
            <div class="sidebar-section-content">
                <?php if (!empty($views)): ?>
                    <ul class="sidebar-menu">
                        <?php foreach ($views as $view): ?>
                            <?php 
                            $is_active = ($active_table == $view['name']);
                            $item_class = $is_active ? 'sidebar-menu-item active' : 'sidebar-menu-item';
                            $url = \App\Route::url('?page=db2tables&action=view-table&table=' . urlencode($view['name']));
                            ?>
                            <li class="<?php echo $item_class; ?>">
                                <a href="<?php echo $url; ?>">
                                    <span class="item-text"><?php _p($view['name']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-message">No views available</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Database selector functionality
document.addEventListener('DOMContentLoaded', function() {
    const dbSelector = document.getElementById('dbSelector');

    // Handle database selection change
    if (dbSelector) {
        dbSelector.addEventListener('change', function() {
            const selectedDb = this.value;

            // Send AJAX request to update session
            fetch('?page=db2tables&action=change-database', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ database: selectedDb })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to refresh with new database
                    window.location.reload();
                } else {
                    alert('Error changing database: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error changing database');
            });
        });
    }

    const searchInput = document.getElementById('tableSearchInput');
    // Function to filter items based on search term
    function filterItems(searchTerm) {
        const menuItems = document.querySelectorAll('.sidebar-menu-item');
        searchTerm = searchTerm.toLowerCase();
        
        menuItems.forEach(function(item) {
            const text = item.querySelector('.item-text').textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Search input event
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterItems(this.value);
        });
        
        // Handle Enter key
        searchInput.addEventListener('keyup', function(event) {
            if (event.key === 'Enter') {
                filterItems(this.value);
            }
        });
    }
    
    // Clear search button
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterItems('');
            searchInput.focus();
        });
    }

});
</script>
