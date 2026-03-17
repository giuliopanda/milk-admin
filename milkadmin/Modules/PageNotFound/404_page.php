<?php
namespace Modules\PageNotFound;
use App\{Route, Response, Theme};
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * 404 page
 * 
 * @package     Modules
 * @subpackage  fourohfour
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */
?>
<div style="margin:0 auto; text-align:center">
    <h2>404 - Page not found</h2>
    <p>The page you are looking for does not exist.<br>Please check the address and try again.</p>
    <a href="<?php echo Route::url(); ?>"><i class="bi bi-skip-backward-circle-fill"></i> Back to home</a>
</div>