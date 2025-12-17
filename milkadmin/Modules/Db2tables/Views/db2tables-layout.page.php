<?php
namespace Modules\Db2tables\Views;

use Modules\Db2tables\Db2tablesStructureServices;


// Ensure this file is not accessed directly
!defined('MILK_DIR') && die();

// Extract variables passed from the router ($sidebarHtml, $pageContent)
if (!isset($sidebarHtml)) $sidebarHtml = '<p>Error: Sidebar not generated.</p>';
if (!isset($pageContent)) $pageContent = '<p>Error: Page content not loaded.</p>';
?>

<div class="container-fluid">
    <div class="row">
        
        <!-- Sidebar Column -->
        <div class="col-md-4 col-lg-3 p-0" id="db2tSidebar">
            <?php echo $sidebarHtml; // Output the generated sidebar HTML ?>
        </div>

        <!-- Content Column -->
        <div class="col-md-8 col-lg-9 pt-3 px-4 bg-white" id="db2tContent">
            <?php echo $pageContent; // Output the loaded page content ?>
        </div>
    </div>
</div>
<script>
// Database field types
const dbFieldTypes = <?php echo json_encode(Db2tablesStructureServices::getFieldTypes()); ?>;
</script>