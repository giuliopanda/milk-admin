<?php
namespace Modules\Docs\Pages;

use App\Route;

/**
 * @title RuleBuilder - Schema Configuration
 * @guide developer
 * @order 51
 * @tags RuleBuilder, schema, model, compatibility, configuration
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>RuleBuilder - Schema Configuration</h1>
    <p class="text-muted">Revision: 2026/03/15</p>
    <p class="lead">This route remains available for compatibility, but the maintained RuleBuilder documentation is now the canonical page in <code>Developer/Model</code>.</p>

    <div class="alert alert-info">
        <strong>Canonical documentation:</strong>
        <ul class="mb-0">
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-rulebuilder'); ?>">RuleBuilder - Schema Configuration</a></li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-relationships'); ?>">Model Relationships</a></li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-crud'); ?>">CRUD Operations</a></li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/AbstractsClass/abstract-model-overview'); ?>">Abstract Model - Overview</a></li>
        </ul>
    </div>

    <p>Use the canonical RuleBuilder page for field types, modifiers, schema evolution, relationship declarations, and related examples.</p>
</div>
