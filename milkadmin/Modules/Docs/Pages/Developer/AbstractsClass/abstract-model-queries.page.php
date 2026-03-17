<?php
namespace Modules\Docs\Pages;

use App\Route;

/**
 * @title Query Builder Methods
 * @guide developer
 * @order 51
 * @tags model, query, compatibility, where, whereIn, whereHas, order, limit, select
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Query Builder Methods</h1>
    <p class="text-muted">Revision: 2026/03/15</p>
    <p class="lead">This page is retained as a stable route, but the maintained Query Builder documentation is in <code>Developer/Model</code>.</p>

    <div class="alert alert-info">
        <strong>Canonical documentation:</strong>
        <ul class="mb-0">
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-queries'); ?>">Query Builder Methods</a></li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/Model/abstract-model-relationships'); ?>">Model Relationships</a></li>
            <li><a href="<?php echo Route::url('?page=docs&action=Developer/AbstractsClass/abstract-model-overview'); ?>">Abstract Model - Overview</a></li>
        </ul>
    </div>

    <p>Use the canonical Query Builder page for the current behavior of <code>query()</code>, <code>where()</code>, <code>whereIn()</code>, <code>whereHas()</code>, <code>order()</code>, <code>select()</code>, <code>limit()</code>, scope handling, and execution methods.</p>
</div>
