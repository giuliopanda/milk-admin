<?php
namespace Builders;

use App\{Form, MessagesHandler, Route};

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * TitleBuilder - Fluent interface for creating page titles with buttons, search, and description
 * 
 * Provides a simplified API for creating consistent page headers with title, buttons,
 * search functionality, and optional descriptions using method chaining.
 * 
 * @package Builders
 * @author MilkAdmin
 */
class TitleBuilder {
    private $title = '';
    private $buttons = [];
    private $description = '';
    private $search_html = '';
    private $search_filter_id = '';
    private $right_content = '';
    private $bottom_content = '';
    private $include_messages = true;
    private $page = '';
    private $left_col = 7;
    private $right_col = 5;

    /**
     * Constructor - Initialize TitleBuilder
     * 
     * @param string $title Optional initial title text
     */
    public function __construct(string $title = '') {
        $this->page = $_REQUEST['page'] ?? '';
        $this->title = $title;
    }

    /**
     * Set the title text
     * 
     * @param string $title The title text to display
     * @return self For method chaining
     * 
     * @example ->title('Posts Management')
     */
    public function title(string $title): self {
        $this->title = $title;
        return $this;
    }

    /**
    * Set the page name for action links
    * 
    * @param string $page_name Page name to use in action links
    * @return self For method chaining
    * 
    * @example ->setPage('users')
    */
   public function setPage(string $page_name): self {
       $this->page = $page_name;
       return $this;
   }

    /**
     * Add a button to the title area
     * 
     * @param string $title Button text
     * @param string $link Button URL
     * @param string $color Bootstrap color class (primary, secondary, success, etc.)
     * @param string $class Additional CSS classes
     * @param string $fetch Fetch type (get, post) Trasform the link in a fetch request
     * @return self For method chaining
     * 
     * @example ->addButton('Add New', '?page=modellist&action=edit', 'primary')
     */
    public function addButton(string $title, string $link, string $color = 'primary', string $class = '', ?string $fetch = null): self {
        $this->buttons[] = [
            'title' => $title,
            'link' => Route::replaceUrlPlaceholders($link, ['page' => $this->page, 'title' => $title]),
            'color' => $color,
            'fetch' => ($fetch !== null && in_array($fetch, ['get', 'post'])) ? $fetch : false,
            'class' => $class
        ];
        return $this;
    }

    /**
     * Add a clickable button with JavaScript onclick
     * 
     * @param string $title Button text
     * @param string $onclick JavaScript function call
     * @param string $color Bootstrap color class (primary, secondary, success, etc.)
     * @param string $class Additional CSS classes
     * @return self For method chaining
     * 
     * @example ->addClickButton('Create New', 'createNew()', 'success')
     */
    public function addClickButton(string $title, string $onclick, string $color = 'primary', string $class = ''): self {
        $this->buttons[] = [
            'title' => $title,
            'click' => $onclick,
            'color' => $color,
            'class' => $class
        ];
        return $this;
    }

    /**
     * Add multiple buttons at once
     * 
     * @param array $buttons Array of button configurations
     * @return self For method chaining
     * 
     * @example ->addButtons([
     *     ['title' => 'Add New', 'link' => '?page=modellist&action=edit', 'color' => 'primary'],
     *     ['title' => 'Export', 'click' => 'exportData()', 'color' => 'secondary']
     * ])
     */
    public function addButtons(array $buttons): self {
        foreach ($buttons as $button) {
            if (isset($button['link'])) {
                $this->addButton(
                    $button['title'] ?? '',
                    $button['link'],
                    $button['color'] ?? 'primary',
                    $button['class'] ?? '',
                    $button['fetch'] ?? null
                );
            } elseif (isset($button['click'])) {
                $this->addClickButton(
                    $button['title'] ?? '',
                    $button['click'],
                    $button['color'] ?? 'primary',
                    $button['class'] ?? ''
                );
            }
        }
        return $this;
    }

    /**
     * Set description text below the title
     * 
     * @param string $description Description text
     * @return self For method chaining
     * 
     * @example ->description('Manage your blog posts and articles')
     */
    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Add search functionality with default input
     * 
     * @param string $filter_id The data-filter-id for table filtering
     * @param string $placeholder Search input placeholder text
     * @param string $label Search input label
     * @return self For method chaining
     * 
     * @example ->addSearch('table_posts', 'Search posts...', 'Search')
     */
    public function addSearch(string $filter_id, string $placeholder = 'Search...', string $label = 'Search'): self {
        $this->search_filter_id = $filter_id;
        $this->search_html = \App\Form::input('text', 'search', $label, '', [
            'placeholder' => $placeholder,
            'floating' => false, 
            'class' => 'js-milk-filter-onchange',
            'data-filter-id' => $filter_id,
            'data-filter-type' => 'search',
            'label-attrs-class' => 'p-0 pt-2 me-2'
        ], true);
        $this->left_col = 9;
        $this->right_col = 3;
        return $this;
    }

    /**
     * Add custom HTML content to the search/right area
     *
     * @param string $html Custom HTML content
     * @return self For method chaining
     *
     * @example ->addRightContent('<button class="btn btn-info">Custom Button</button>')
     */
    public function addRightContent(string $html): self {
        $this->right_content = $html;
        return $this;
    }

    /**
     * Add custom HTML content below the title section in a full-width row
     *
     * @param string $html Custom HTML content
     * @return self For method chaining
     *
     * @example ->addBottomContent('<div class="alert alert-info">Information message</div>')
     */
    public function addBottomContent(string $html): self {
        $this->bottom_content = $html;
        return $this;
    }

    /**
     * Set custom search HTML (overrides add_search)
     * 
     * @param string $html Custom search HTML
     * @return self For method chaining
     * 
     * @example ->setSearchHtml('<div class="search-wrapper">Custom search</div>')
     */
    public function setSearchHtml(string $html): self {
        $this->search_html = $html;
        return $this;
    }

    /**
     * Control whether to include MessagesHandler::displayMessages()
     * 
     * @param bool $include Whether to include messages
     * @return self For method chaining
     * 
     * @example ->includeMessages(false)
     */
    public function includeMessages(bool $include = true): self {
        $this->include_messages = $include;
        return $this;
    }

    /**
     * Remove all buttons
     * 
     * @return self For method chaining
     */
    public function clearButtons(): self {
        $this->buttons = [];
        return $this;
    }

    /**
     * Remove search functionality
     * 
     * @return self For method chaining
     */
    public function clearSearch(): self {
        $this->search_html = '';
        $this->search_filter_id = '';
        return $this;
    }

    /**
     * Remove right content
     *
     * @return self For method chaining
     */
    public function clearRight(): self {
        $this->right_content = '';
        return $this;
    }

    /**
     * Remove bottom content
     *
     * @return self For method chaining
     */
    public function clearBottom(): self {
        $this->bottom_content = '';
        return $this;
    }

    public function setCols($left): self {
        if ($left < 1 || $left > 12) {
            throw new \Exception('Left column must be between 1 and 12');
        }
        $this->left_col = $left;
        $this->right_col = 12 - $left;
        return $this;
    }

    /**
     * Generate and return the complete HTML
     * 
     * @return string Complete HTML for the title section
     */
    public function render(): string {
        ob_start();
        
        // Generate custom responsive HTML instead of using the title plugin
        ?>
        <div class="module-title">
            <!-- Title and buttons row -->
            <div class="row">
                <div class="col-12 col-lg-<?php _p((!empty($this->search_html) || !empty($this->right_content)) ? $this->left_col : 12); ?>">
                    <div class="py-2">
                        <div class="d-flex align-items-center flex-wrap">
                            <h2 class="mb-0 me-3"><?php _pt($this->title); ?></h2>
                            <div class="d-flex flex-wrap">
                                <?php 
                                foreach ($this->buttons as $btn) {
                                    if (isset($btn['link'])) {
                                       ?><a class="btn btn-<?php _p($btn['color'] ?? 'primary'); ?> me-2 mb-2 <?php _p($btn['class'] ?? ''); ?>" href="<?php _p($btn['link']); ?>"<?php _ph(($btn['fetch'] ?? false) ? ' data-fetch="'.$btn['fetch'].'"' : ''); ?>><?php _pt($btn['title']); ?></a><?php
                                    } else if (isset($btn['click'])) {
                                        ?><span class="btn btn-<?php _p($btn['color'] ?? 'primary'); ?> me-2 mb-2 <?php _p($btn['class'] ?? ''); ?>" onclick="<?php _p($btn['click']); ?>"><?php _pt($btn['title']); ?></span><?php
                                    }
                                } 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($this->search_html) || !empty($this->right_content)): ?>
                <div class="col-12 col-lg-<?php _p($this->right_col); ?>">
                    <div class="py-2">
                        <?php if (!empty($this->search_html)): ?>
                            <div class="d-flex justify-content-lg-end mb-2">
                                <?php _ph($this->search_html); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($this->right_content)): ?>
                            <div class="d-flex justify-content-lg-end">
                                <?php _ph($this->right_content); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($this->description)): ?>
            <div class="text-body-secondary mb-3"><?php _pt($this->description); ?></div>
            <?php endif; ?>

            <?php if (!empty($this->bottom_content)): ?>
            <!-- Bottom content row -->
            <div class="row">
                <div class="col-12">
                    <?php _ph($this->bottom_content); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php

        // Include messages if enabled
        if ($this->include_messages) {
            MessagesHandler::displayMessages();
        }

        return ob_get_clean();
    }

    /**
     * Get the HTML (alias for render)
     * 
     * @return string Complete HTML for the title section
     */
    public function getHtml(): string {
        return $this->render();
    }

    /**
     * Factory method to create TitleBuilder instance
     * 
     * @param string $title Optional initial title
     * @return self New TitleBuilder instance
     * 
     * @example TitleBuilder::create('Posts Management')
     */
    public static function create(string $title = ''): self {
        return new self($title);
    }

    /**
     * Magic method to output HTML when object is used as string
     * 
     * @return string Complete HTML for the title section
     */
    public function __toString(): string {
        return $this->render();
    }
}