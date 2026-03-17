<?php

declare(strict_types=1);

use App\Sanitize;
use PHPUnit\Framework\TestCase;

final class SanitizeStringableValue
{
    public function __toString(): string
    {
        return 'stringable-value';
    }
}

final class SanitizeTest extends TestCase
{
    public function testInputDispatchesToExpectedStrategies(): void
    {
        $this->assertSame('a&amp;b', Sanitize::input('a&b', 'string'));
        $this->assertSame('a&amp;b', Sanitize::input('a&b', 'text'));
        $this->assertSame('x&quot;y', Sanitize::input('x"y', 'attr'));
        $this->assertSame('user@example.com', Sanitize::input(' user<>@example.com ', 'email'));
        $this->assertSame('https://example.com/path', Sanitize::input('https://example.com/path', 'url'));
        $this->assertSame('-42', Sanitize::input('a-42b', 'int'));
        $this->assertSame('3.14', Sanitize::input('x3.14y', 'float'));
        $this->assertSame('id_123abc', Sanitize::input('123-abc', 'identifier'));
        $this->assertSame('"ok"', Sanitize::input('ok', 'js'));
        $this->assertSame('a&amp;b', Sanitize::input('a&b', 'unknown'));
    }

    public function testTextAndAttrEscapeHtmlSpecialCharacters(): void
    {
        $this->assertSame('&lt;b&gt;Milk&lt;/b&gt;', Sanitize::text('<b>Milk</b>'));
        $this->assertSame('x&quot;y&#039;z', Sanitize::attr('x"y\'z'));
    }

    public function testEmailUrlIntAndFloatSanitizers(): void
    {
        $this->assertSame('john@example.com', Sanitize::email(' john<>@example.com '));
        $this->assertSame('https://example.com/path?q=1', Sanitize::url('https://example.com/path?q=1'));
        $this->assertSame('-123', Sanitize::int('a-123b'));
        $this->assertSame('12.34', Sanitize::float('x12.34y'));
    }

    public function testHtmlRemovesScriptsIframesEventHandlersJavascriptUrisAndPhpTags(): void
    {
        $dirty = '<p onclick="alert(1)">Hello</p>'
            . '<script>alert(2)</script>'
            . '<a href="javascript:alert(3)">link</a>'
            . '<iframe src="https://x.test"></iframe>'
            . '<?php echo "bad"; ?>';

        $clean = Sanitize::html($dirty);

        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringContainsString('<a>link</a>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('onclick=', $clean);
        $this->assertStringNotContainsString('javascript:', strtolower($clean));
        $this->assertStringNotContainsString('<?php', $clean);
    }

    public function testSafeHtmlAppliesWhitelistAndDangerousAttributeRemoval(): void
    {
        $dirty = '<!-- comment --><p style="color:red" onmouseover="x()">Hi <strong>there</strong></p>'
            . '<style>body{}</style><meta charset="utf-8">'
            . '<a href="javascript:alert(1)">bad</a>'
            . '<img src="https://example.com/logo.png">';

        $clean = Sanitize::safeHtml($dirty);

        $this->assertStringContainsString('<p>Hi <strong>there</strong></p>', $clean);
        $this->assertStringContainsString('<a>bad</a>', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('onmouseover=', $clean);
        $this->assertStringNotContainsString('<meta', $clean);
        $this->assertStringNotContainsString('<style', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('javascript:', strtolower($clean));
    }

    public function testJsReturnsJsonLiteralAndEscapesTags(): void
    {
        $encoded = Sanitize::js('<tag>');
        $this->assertStringStartsWith('"', $encoded);
        $this->assertStringEndsWith('"', $encoded);
        $this->assertStringContainsString('\u003Ctag\u003E', $encoded);
    }

    public function testIdentifierRulesAreApplied(): void
    {
        $this->assertSame('fieldname1', Sanitize::identifier('field-name 1'));
        $this->assertSame('id_123abc', Sanitize::identifier('123abc'));
        $this->assertSame('prefix_', Sanitize::identifier('!@#', 'prefix_'));
    }

    public function testStringConvertsSupportedValuesAndRejectsComplexValues(): void
    {
        $this->assertSame('', Sanitize::string(null));
        $this->assertSame('10', Sanitize::string(10));
        $this->assertSame('1', Sanitize::string(true));
        $this->assertSame('stringable-value', Sanitize::string(new SanitizeStringableValue()));
        $this->assertSame('', Sanitize::string(['x' => 1]));
        $this->assertSame('', Sanitize::string((object) ['x' => 1]));
    }

    public function testGetStringIsAliasOfString(): void
    {
        $this->assertSame('abc', Sanitize::getString('abc'));
        $this->assertSame('', Sanitize::getString(['nope']));
    }
}
