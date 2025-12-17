<?php
// Basic Module Configuration

// Simple module with single menu
$rule->page('posts')
     ->title('Posts')
     ->menu('Posts', '', 'bi bi-file-earmark-post-fill', 10)
     ->access('authorized')
     ->permissions(['access' => 'Access'])
     ->version(251101);

// Public access module with JS
$rule->page('events')
     ->title('Events')
     ->menu('Events', '', 'bi bi-calendar-event', 50)
     ->setJs('assets/events.js')
     ->access('public')
     ->version(251113);

// Module with multiple models
$rule->page('linksdata')
     ->title('Links Data')
     ->menu('Links', '', 'bi bi-link-45deg', 10)
     ->access('authorized')
     ->permissions(['access' => 'Access'])
     ->addModels([
         'LinkCategory' =>  LinkCategoryModel::class,
     ])
     ->version(251130);
