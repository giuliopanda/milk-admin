<?php
namespace Modules\Docs;

use App\{Theme};
use App\Abstracts\AbstractModule;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Documentation Module
 *
 * Provides comprehensive documentation divided into three guides:
 * - Developer Guide: Getting started, abstracts, models, builders
 * - Framework Guide: Core framework, theme, forms, dynamic tables
 * - User Guide: Module usage and system administration
 */
class DocsModule extends AbstractModule
{
    static $first = true;
    protected function configure($rule): void
    {
        $rule->page('docs')
             ->title('Documentation')
             ->access('admin')
             ->menu('Documentation', 'guide=developer', 'bi bi-book', 90)
             ->headerTitle('Milk Admin Documentation')
             ->headerDescription('Comprehensive framework documentation')
             ->setJs('Assets/colorcode.js')
             ->setCss('Assets/colorcode.css')
             ->isCoreModule()
             ->version(251005);
    }

    public function init() {
        ob_start();
        require_once(MILK_DIR . '/Modules/Docs/views/partial-guide-navigation.php');
        $header_links = ob_get_clean();

        Theme::set('header.top-left', $header_links);
    }
}


