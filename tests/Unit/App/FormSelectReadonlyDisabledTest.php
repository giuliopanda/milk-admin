<?php
declare(strict_types=1);

if (!defined('MILK_TEST_CONTEXT')) {
    define('MILK_TEST_CONTEXT', true);
}
if (!defined('MILK_API_CONTEXT')) {
    define('MILK_API_CONTEXT', true);
}

require_once dirname(__DIR__, 3) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use App\Form;
use PHPUnit\Framework\TestCase;

final class FormSelectReadonlyDisabledTest extends TestCase
{
    public function testReadonlySelectDoesNotRenderDisabledOrHiddenFallback(): void
    {
        $html = Form::select(
            'data[category]',
            'Category',
            [
                'tech' => 'Technology',
                'news' => 'News',
            ],
            'news',
            ['readonly' => true],
            true
        );

        $this->assertStringContainsString(' readonly', $html);
        $this->assertStringNotContainsString(' disabled', $html);
        $this->assertStringNotContainsString('type="hidden" name="data[category]"', $html);
    }

    public function testDisabledSelectRendersHiddenBeforeSelect(): void
    {
        $html = Form::select(
            'data[category]',
            'Category',
            [
                'tech' => 'Technology',
                'news' => 'News',
            ],
            'news',
            ['disabled' => true],
            true
        );

        $hiddenPosition = strpos($html, '<input type="hidden" name="data[category]" value="news">');
        $selectPosition = strpos($html, '<select name="data[category]"');

        $this->assertNotFalse($hiddenPosition);
        $this->assertNotFalse($selectPosition);
        $this->assertLessThan($selectPosition, $hiddenPosition);
    }

    public function testDisabledSelectUsesFirstRenderedOptionWhenSelectedMissing(): void
    {
        $html = Form::select(
            'data[category]',
            'Category',
            [
                'tech' => 'Technology',
                'news' => 'News',
            ],
            '',
            ['disabled' => true],
            true
        );

        $this->assertStringContainsString('<input type="hidden" name="data[category]" value="tech">', $html);
    }

    public function testDisabledSelectUsesFirstRenderedOptionWithOptgroups(): void
    {
        $html = Form::select(
            'data[category]',
            'Category',
            [
                'Group A' => [
                    'tech' => 'Technology',
                    'news' => 'News',
                ],
                'Group B' => [
                    'life' => 'Lifestyle',
                ],
            ],
            '',
            ['disabled' => true],
            true
        );

        $this->assertStringContainsString('<input type="hidden" name="data[category]" value="tech">', $html);
    }

    public function testDisabledSelectKeepsZeroValue(): void
    {
        $html = Form::select(
            'data[status]',
            'Status',
            [
                '0' => 'Inactive',
                '1' => 'Active',
            ],
            '0',
            ['disabled' => true],
            true
        );

        $this->assertStringContainsString('<input type="hidden" name="data[status]" value="0">', $html);
    }
}

