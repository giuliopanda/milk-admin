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
    private $id = '';
    private $title = '';
    private $title_is_html = false;
    private $title_class = '';
    private $container_class = '';
    private $buttons = [];
    private $small_buttons = false;
    private $description = '';
    private $description_is_html = false;
    private $search_html = '';
    private $search_filter_id = '';
    private $right_content = '';
    private $bottom_content = '';
    private $include_messages = true;
    private $page = '';
    private $heading_size = 'h2';
    private $left_col = 6;
    private $right_col = 6;

    /**
     * Constructor - Initialize TitleBuilder
     * 
     * @param string $title Optional initial title text
     */
    public function __construct(string $title = '') {
        $this->page = $_REQUEST['page'] ?? '';
        $this->title = $title;
        $this->id = 'title_' . bin2hex(random_bytes(4));
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
        $this->title_is_html = false;
        return $this;
    }

    /**
     * Set title HTML (rendered without escaping).
     *
     * Use only with trusted HTML.
     *
     * @param string $titleHtml HTML content for the title
     * @return self For method chaining
     */
    public function titleHtml(string $titleHtml): self {
        $this->title = $titleHtml;
        $this->title_is_html = true;
        return $this;
    }

    /**
     * Set custom CSS classes for the heading element.
     *
     * @param string $class Space-separated CSS classes
     * @return self For method chaining
     */
    public function titleClass(string $class): self {
        $this->title_class = trim($class);
        return $this;
    }

    /**
     * Set custom CSS classes for the title wrapper container.
     *
     * @param string $class Space-separated CSS classes
     * @return self For method chaining
     */
    public function containerClass(string $class): self {
        $this->container_class = trim($class);
        return $this;
    }

    /**
     * Render all title buttons as small buttons.
     *
     * @param bool $small Enable/disable small buttons
     * @return self For method chaining
     */
    public function smallButtons(bool $small = true): self {
        $this->small_buttons = $small;
        return $this;
    }

    /**
     * Set the heading tag size (h2, h3, h4, h5)
     *
     * @param string $size Heading level (h2, h3, h4, h5)
     * @return self For method chaining
     *
     * @example ->headingSize('h3')
     */
    public function headingSize(string $size): self {
        $allowed = ['h2', 'h3', 'h4', 'h5'];
        if (!in_array($size, $allowed)) {
            throw new \InvalidArgumentException("Heading size must be one of: " . implode(', ', $allowed));
        }
        $this->heading_size = $size;
        return $this;
    }

    /**
     * Set a custom ID for the title container (for AJAX updates)
     *
     * @param string $id The unique ID
     * @return self For method chaining
     */
    public function setId(string $id): self {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the title container ID
     *
     * @return string The ID
     */
    public function getId(): string {
        return $this->id;
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
    public function addButton(
        string $title,
        string $link,
        string $color = 'primary',
        string $class = '',
        ?string $fetch = null,
        string $target = '',
        bool $small = false
    ): self {
        $this->buttons[] = [
            'title' => $title,
            'title_is_html' => false,
            'link' => Route::replaceUrlPlaceholders($link, ['page' => $this->page, 'title' => strip_tags($title)]),
            'color' => $color,
            'fetch' => ($fetch !== null && in_array($fetch, ['get', 'post'])) ? $fetch : false,
            'class' => $class,
            'target' => $target,
            'small' => $small
        ];
        return $this;
    }

    /**
     * Add a button with HTML label (rendered without escaping).
     *
     * Use only with trusted HTML.
     *
     * @param string $titleHtml Button HTML label
     * @param string $link Button URL
     * @param string $color Bootstrap color class
     * @param string $class Additional CSS classes
     * @param string|null $fetch Fetch type
     * @param string $target Link target
     * @return self For method chaining
     */
    public function addButtonHtml(
        string $titleHtml,
        string $link,
        string $color = 'primary',
        string $class = '',
        ?string $fetch = null,
        string $target = '',
        bool $small = false
    ): self {
        $this->buttons[] = [
            'title' => $titleHtml,
            'title_is_html' => true,
            'link' => Route::replaceUrlPlaceholders($link, ['page' => $this->page, 'title' => strip_tags($titleHtml)]),
            'color' => $color,
            'fetch' => ($fetch !== null && in_array($fetch, ['get', 'post'])) ? $fetch : false,
            'class' => $class,
            'target' => $target,
            'small' => $small
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
    public function addClickButton(
        string $title,
        string $onclick,
        string $color = 'primary',
        string $class = '',
        bool $small = false
    ): self {
        $this->buttons[] = [
            'title' => $title,
            'title_is_html' => false,
            'click' => $onclick,
            'color' => $color,
            'class' => $class,
            'small' => $small
        ];
        return $this;
    }

    /**
     * Add a clickable button with HTML label (rendered without escaping).
     *
     * Use only with trusted HTML.
     *
     * @param string $titleHtml Button HTML label
     * @param string $onclick JavaScript function call
     * @param string $color Bootstrap color class
     * @param string $class Additional CSS classes
     * @return self For method chaining
     */
    public function addClickButtonHtml(
        string $titleHtml,
        string $onclick,
        string $color = 'primary',
        string $class = '',
        bool $small = false
    ): self {
        $this->buttons[] = [
            'title' => $titleHtml,
            'title_is_html' => true,
            'click' => $onclick,
            'color' => $color,
            'class' => $class,
            'small' => $small
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
            $title = (string) ($button['title'] ?? '');
            $titleHtml = (string) ($button['title_html'] ?? '');
            $hasHtmlTitle = $titleHtml !== '' || !empty($button['title_is_html']);
            $small = $this->normalizeBool($button['small'] ?? false);
            if (!$small && isset($button['size']) && strtolower(trim((string) $button['size'])) === 'sm') {
                $small = true;
            }
            if (isset($button['link'])) {
                if ($hasHtmlTitle) {
                    $this->addButtonHtml(
                        $titleHtml !== '' ? $titleHtml : $title,
                        $button['link'],
                        $button['color'] ?? 'primary',
                        $button['class'] ?? '',
                        $button['fetch'] ?? null,
                        $button['target'] ?? '',
                        $small
                    );
                } else {
                    $this->addButton(
                        $title,
                        $button['link'],
                        $button['color'] ?? 'primary',
                        $button['class'] ?? '',
                        $button['fetch'] ?? null,
                        $button['target'] ?? '',
                        $small
                    );
                }
            } elseif (isset($button['click'])) {
                if ($hasHtmlTitle) {
                    $this->addClickButtonHtml(
                        $titleHtml !== '' ? $titleHtml : $title,
                        $button['click'],
                        $button['color'] ?? 'primary',
                        $button['class'] ?? '',
                        $small
                    );
                } else {
                    $this->addClickButton(
                        $title,
                        $button['click'],
                        $button['color'] ?? 'primary',
                        $button['class'] ?? '',
                        $small
                    );
                }
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
        $this->description_is_html = false;
        return $this;
    }

    /**
     * Set description HTML (rendered without escaping).
     *
     * Use only with trusted HTML.
     *
     * @param string $descriptionHtml HTML content below the title
     * @return self For method chaining
     */
    public function descriptionHtml(string $descriptionHtml): self {
        $this->description = $descriptionHtml;
        $this->description_is_html = true;
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
        $wrapperClass = trim('module-title ' . $this->container_class);
        ?>
        <div class="<?php _p($wrapperClass); ?>" id="<?php _p($this->id); ?>">
            <?php echo $this->renderInner(); ?>
        </div>
        <?php

        // Include messages if enabled
        if ($this->include_messages) {
            MessagesHandler::displayMessages();
        }

        return ob_get_clean();
    }

    /**
     * Render only the inner HTML of the title (without the wrapper div).
     * Useful for AJAX title updates via the jsonAction title command.
     *
     * @return string Inner HTML content
     */
    public function renderInner(): string {
        ob_start();
        $titleClass = trim('mb-0 me-3 ' . $this->title_class);
        ?>
            <!-- Title and buttons row -->
            <div class="row">
                <div class="col-12 col-lg-<?php _p((!empty($this->search_html) || !empty($this->right_content)) ? $this->left_col : 12); ?>">
                    <div class="py-2">
                        <div class="d-flex align-items-center flex-wrap">
                            <<?php _p($this->heading_size); ?> class="<?php _p($titleClass); ?>"><?php
                                if ($this->title_is_html) {
                                    _ph($this->title);
                                } else {
                                    _pt($this->title);
                                }
                            ?></<?php _p($this->heading_size); ?>>
                            <div class="d-flex flex-wrap">
                                <?php
                                foreach ($this->buttons as $btn) {
                                    $isSmall = !empty($btn['small']) || $this->small_buttons;
                                    $buttonClass = trim(
                                        'btn btn-' . ($btn['color'] ?? 'primary')
                                        . ' me-2 mb-2 '
                                        . ($isSmall ? 'btn-sm ' : '')
                                        . ($btn['class'] ?? '')
                                    );
                                    if (isset($btn['link'])) {
                                        $target = $btn['target'] ?? '';
                                        $rel = $target === '_blank' ? ' rel="noopener"' : '';
                                       ?><a class="<?php _p($buttonClass); ?>" href="<?php _p($btn['link']); ?>"<?php _ph(($btn['fetch'] ?? false) ? ' data-fetch="'.$btn['fetch'].'"' : ''); ?><?php _ph(($target !== '') ? ' target="'._r($target).'"' : ''); ?><?php _ph($rel); ?>><?php
                                            if (!empty($btn['title_is_html'])) {
                                                _ph((string) ($btn['title'] ?? ''));
                                            } else {
                                                _pt((string) ($btn['title'] ?? ''));
                                            }
                                        ?></a><?php
                                    } else if (isset($btn['click'])) {
                                        ?><span class="<?php _p($buttonClass); ?>" onclick="<?php _p($btn['click']); ?>"><?php
                                            if (!empty($btn['title_is_html'])) {
                                                _ph((string) ($btn['title'] ?? ''));
                                            } else {
                                                _pt((string) ($btn['title'] ?? ''));
                                            }
                                        ?></span><?php
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
            <div class="text-body-secondary mb-3"><?php
                if ($this->description_is_html) {
                    _ph($this->description);
                } else {
                    _pt($this->description);
                }
            ?></div>
            <?php endif; ?>

            <?php if (!empty($this->bottom_content)): ?>
            <!-- Bottom content row -->
            <div class="row">
                <div class="col-12">
                    <?php _ph($this->bottom_content); ?>
                </div>
            </div>
            <?php endif; ?>
        <?php

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

    protected function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'on'],
            true
        );
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
