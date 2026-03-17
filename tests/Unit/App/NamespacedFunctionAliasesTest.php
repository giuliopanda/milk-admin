<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NamespacedFunctionAliasesTest extends TestCase
{
    public function testAppNamespaceAliasesDelegateCorrectly(): void
    {
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', \App\_r('<b>x</b>'));
        $this->assertSame('<i>y</i>', \App\_rh('<i>y</i>'));
        $this->assertSame(7, \App\_absint(-7));
        $this->assertSame('abc123', \App\_raz('abc-123'));
    }

    public function testSubNamespaceAliasesAreAvailable(): void
    {
        $this->assertSame(9, \App\Abstracts\_absint(-9));
        $this->assertSame(5, \App\Database\_absint(-5));
        $this->assertSame(4, \App\Modellist\_absint(-4));
        $this->assertSame('fieldname', \App\Modellist\_raz('field-name'));
        $this->assertSame('ok', \App\Abstracts\Traits\_r('ok'));
    }

    public function testPtAliasPrintsOutput(): void
    {
        ob_start();
        \App\_pt('Hello');
        $out = (string) ob_get_clean();

        $this->assertSame('Hello', $out);
    }
}
