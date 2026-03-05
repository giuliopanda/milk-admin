<?php
!defined('MILK_DIR') && die();
?>
<div class="modal fade" id="containerBuilderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="containerBuilderModalTitle">
                    <i class="bi bi-layout-three-columns me-2 text-primary"></i>
                    Column Stack Builder
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="containerBuilderForm" novalidate>
                <div class="modal-body pt-2">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="csId">
                                Container ID <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="csId"
                                   name="container_id"
                                   maxlength="64"
                                   pattern="^[A-Za-z][A-Za-z0-9_-]*$"
                                   required>
                            <div class="invalid-feedback">
                                Use letters, numbers, underscore or dash. Must start with a letter.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="csTitle">
                                Title <small class="text-muted fw-normal">(optional)</small>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="csTitle"
                                   name="container_title">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="section-label">Available fields</span>
                            <small class="text-muted">Drag fields into columns</small>
                        </div>
                        <div class="fields-pool" id="fieldsPool"></div>
                        <div id="containerFieldsEmpty" class="alert alert-warning py-2 mb-0 d-none mt-2">
                            No available fields.
                        </div>
                    </div>

                    <div class="mb-2 d-flex align-items-center gap-2">
                        <span class="section-label">Columns layout</span>
                        <span class="width-total" id="widthTotal">12/12</span>
                        <button type="button"
                                class="btn btn-outline-primary btn-sm btn-add-col ms-auto"
                                id="addColBtn">
                            <i class="bi bi-plus-lg me-1"></i>
                            Add column
                        </button>
                    </div>

                    <div class="canvas-wrapper">
                        <div id="layoutCanvas"></div>
                    </div>
                    <div class="text-danger mt-1" id="canvasError" style="font-size:.8rem;display:none"></div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="containerBuilderSubmitBtn">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <span id="containerBuilderSubmitLabel">Create container</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
