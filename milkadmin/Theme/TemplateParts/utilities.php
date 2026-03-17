<?php 
namespace Theme\TemplateParts;

use App\Get;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Qui vengono definiti gli elementi aggiuntivi della pagina richiamabili tramite javascript
 * offcanvas, toast, modal, ecc.
 * Qui essendo elementi singoli posso usare gli ID
 */
?>
<div class="offcanvas offcanvas-size-ito offcanvas-end " tabindex="-1" id="offCanvasEnd" aria-labelledby="offcanvasTopLabel">
  <?php echo Get::themePlugin('Loading'); ?> 
  <div class="offcanvas-header" id="offcanvasHeader">
    <h5 class="offcanvas-title" id="offCanvasTitle"></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body" id="offCanvasBody">
   
  </div>
</div>

<div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 m-4">
  <div id="toastUp" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true"> 
      <div class="toast-body" id="toastBody">
        <div class="d-flex">
          <div id="toastBodyTxt"></div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
  
  </div>
</div>

<div class="modal fade" id="itoModal"  tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content js-modal-content">
        <div class="modal-header js-modal-header">
          <h5 class="modal-title js-modal-title"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body js-modal-body">
        
        </div>
        <div class="modal-footer js-modal-footer">
        
        </div>
    </div>
  </div>
</div>