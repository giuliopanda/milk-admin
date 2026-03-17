<?php
namespace Modules\Docs\Pages;

use App\Route;

/**
 * @title CRUD Operations
 * @guide developer
 * @order 52
 * @tags model, CRUD, compatibility, create, read, update, delete
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>CRUD Operations</h1>
    <p class="text-muted">Revision: 2026/03/15</p>
    <p class="lead">This page is a compatibility entry point. The maintained CRUD documentation for models is the canonical page in <code>Developer/Model</code>.</p>

    <div class="alert alert-info">
        <strong>Canonical documentation:</strong>
        <ul class="mb-0">
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-crud'); ?>">Model CRUD Operations</a></li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-save-flow'); ?>">Model Save Flow</a></li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/AbstractsClass/abstract-model-overview'); ?>">Abstract Model - Overview</a></li>
        </ul>
    </div>

    <p>The canonical CRUD page documents the current behavior of methods such as <code>getById()</code>, <code>getByIds()</code>, <code>getEmpty()</code>, <code>getByIdAndUpdate()</code>, <code>save()</code>, <code>store()</code>, and delete flows.</p>
</div>
