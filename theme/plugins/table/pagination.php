<?php
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * $page_info['total_record'] 
 * $page_info['limit_start']
 * $page_info['limit']
 * $page_info['pag-goto-show'] (optional)
 * $page_info['pag-number-show'] (optional)
 * $page_info['pag-elperpage-show'] (optional)
 * $page_info['pagination-limit'] (optional)
 */
$total = $page_info['total_record'];
$pag_limit = $page_info['pagination-limit'] ?? 14;
$pages = ceil($page_info['total_record'] / $page_info['limit']);
$actual_page = ceil($page_info['limit_start'] / $page_info['limit']) + 1;

$number_of_links = ceil(($pag_limit) / 2);

$first_page = (($actual_page - ceil($number_of_links/2)) < 1) ? 1 : $actual_page - ceil($number_of_links/2);
if ($first_page + $number_of_links+1 > $pages) {
    $first_page = $pages - ($number_of_links+1);
}
if ($first_page < 1) {
    $first_page = 1;
}
if ($first_page < $number_of_links*2) {
    $number_of_links = $pag_limit - $first_page + 1;
}
if ($first_page > $total - $number_of_links) {
    $number_of_links = $pag_limit - ($total - $first_page);
}

?>
<nav aria-label="Page navigation">
        <?php if ($pages > 1) : ?>
            <?php if ($page_info['pag-total-show'] ?? true) : ?>
                <div class="d-inline-block">
                    <span class="me-2"><?php printf(_rh('Total: <b>%d</b>'), $total); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($page_info['pag-number-show'] ?? true) : ?>  
                <div class="d-inline-block">
                    <ul class="pagination">
                        <?php if ($actual_page > 1) : ?>
                            <li class="page-item">
                                <?php 
                                // Calculate previous page (go back 20 pages, but not below 1)
                                $prev_page = max(1, $actual_page - 20);
                                ?>
                                <span class="page-link table-pagination-click js-pagination-click" data-table-page="<?php echo $prev_page; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </span>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = $first_page; ($i <= $pages && $i <= $actual_page + $number_of_links); $i++) : ?>
                            <li class="page-item <?php echo $i == $actual_page ? 'active' : ''; ?>">
                                <span class="page-link table-pagination-click js-pagination-click" data-table-page="<?php echo $i; ?>"><?php echo $i; ?></span>
                            </li>
                        <?php endfor; ?>
                        <?php if ($pages > $actual_page) : ?>
                            <li class="page-item">
                                <?php 
                                // Calculate next page (go forward 20 pages, but not above total pages)
                                $next_page = min($pages, $actual_page + 20);
                                ?>
                                <span class="page-link table-pagination-click js-pagination-click" data-table-page="<?php echo $next_page; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </span>
                            </li>
                        <?php endif; ?>
                    
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (($page_info['pag-goto-show'] ?? true) && $pages > 9) : ?>
                <div class="d-inline-block ms-3">
                    <span class="me-2"><?php _pt('Go to page:'); ?> </span>
                </div>
                <div class="d-inline-block">
                    <select class="form-select js-pagination-select" data-table-page="select">
                        <?php
                        // Optimize the select options when there are too many pages
                        if ($pages <= 200) {
                            // If we have 200 or fewer pages, show all of them
                            for ($i = 1; $i <= $pages; $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $actual_page ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor;
                        } else {
                            // We have more than 200 pages, so we need to optimize
                            // Track which pages we've already added to avoid duplicates
                            $added_pages = [];
                            
                            // Function to add a page option and track it
                            $add_page = function($page_num, $is_selected = false) use (&$added_pages) {
                                if (!isset($added_pages[$page_num])) {
                                    $added_pages[$page_num] = true;
                                    $selected = $is_selected ? ' selected' : '';
                                    echo "<option value=\"$page_num\"$selected>$page_num</option>\n";
                                }
                            };
                            
                            // Always show first 20 pages
                            $first_range_end = min(20, $pages);
                            for ($i = 1; $i <= $first_range_end; $i++) {
                                $add_page($i, $i == $actual_page);
                            }
                            
                            // Calculate ranges around current page
                            $current_range_start = max($first_range_end + 1, $actual_page - 20);
                            $current_range_end = min($pages - 20, $actual_page + 20);
                            
                            // If there's a gap between first range and current range, add interval pages
                            if ($current_range_start > $first_range_end + 1) {
                                // Calculate approximately 10 evenly spaced interval pages
                                $gap_size = $current_range_start - $first_range_end - 1;
                                $interval_count = min(10, $gap_size);
                                
                                if ($interval_count > 0) {
                                    $interval = max(1, floor($gap_size / ($interval_count + 1)));
                                    for ($i = $first_range_end + $interval; $i < $current_range_start; $i += $interval) {
                                        $add_page($i);
                                    }
                                }
                            }
                            
                            // Show pages around current page
                            for ($i = $current_range_start; $i <= $current_range_end; $i++) {
                                $add_page($i, $i == $actual_page);
                            }
                            
                            // Calculate last range
                            $last_range_start = max($current_range_end + 1, $pages - 19);
                            
                            // If there's a gap between current range and last range, add interval pages
                            if ($last_range_start > $current_range_end + 1) {
                                // Calculate approximately 10 evenly spaced interval pages
                                $gap_size = $last_range_start - $current_range_end - 1;
                                $interval_count = min(10, $gap_size);
                                
                                if ($interval_count > 0) {
                                    $interval = max(1, floor($gap_size / ($interval_count + 1)));
                                    for ($i = $current_range_end + $interval; $i < $last_range_start; $i += $interval) {
                                        $add_page($i);
                                    }
                                }
                            }
                            
                            // Always show last 20 pages
                            for ($i = $last_range_start; $i <= $pages; $i++) {
                                $add_page($i, $i == $actual_page);
                            }
                        }
                        ?>
                    </select>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($page_info['pag-elperpage-show'] ?? true) : ?>
            <?php if ($page_info['pag-total-show'] ?? true) : ?>
                <div class="d-inline-block">
                    <span class="me-2"><?php printf(_rh('Total: <b>%d</b>'), $total); ?></span>
                </div>
            <?php endif; ?>
            <div class="d-inline-block ms-3">
                <span class="me-2"><?php _pt('Elements per page:'); ?> </span>
            </div>
            <div class="d-inline-block">
                <select class="form-select js-pagination-el-per-page" data-table-page="limit">
                    <?php foreach ([5, 10, 15, 20, 25, 30, 40, 50, 100] as $limit) : ?>
                        <option value="<?php echo $limit; ?>" <?php echo $limit == $page_info['limit'] ? 'selected' : ''; ?>><?php echo $limit; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
  
</nav>