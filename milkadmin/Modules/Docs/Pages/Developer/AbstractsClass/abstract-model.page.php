<?php
namespace Modules\Docs\Pages;

use App\Route;

/**
 * @title Abstract Model
 * @guide developer
 * @order 50
 * @tags AbstractModel, model, database, api, compatibility, documentation
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract Model</h1>
    <p class="text-muted">Revision: 2026/03/15</p>
    <p class="lead">This route is kept for backward compatibility. The canonical operational documentation for the Model API now lives in the <code>Developer/Model</code> section, while the abstract-base overview remains in <code>Developer/AbstractsClass</code>.</p>

    <div class="alert alert-info">
        <strong>Use these pages:</strong>
        <ul class="mb-0">
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/AbstractsClass/abstract-model-overview'); ?>">Abstract Model - Overview</a> for architecture, responsibilities, and how the abstract base class fits into the framework</li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model'); ?>">Abstract Model</a> for the full public API reference</li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-save-flow'); ?>">Model Save Flow</a> for the persistence lifecycle and record state</li>
        </ul>
    </div>

    <div class="alert alert-secondary">
        <strong>Why this split exists:</strong> <code>AbstractModel</code> is the base class, but the methods developers use every day form the broader Model API. Keeping the detailed reference in one canonical section avoids divergence between duplicate pages.
    </div>
</div>
