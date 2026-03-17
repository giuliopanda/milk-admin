<?php

declare(strict_types=1);

use App\Config;
use App\ObjectToForm;
use PHPUnit\Framework\TestCase;

final class ObjectToFormTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];
    /** @var array<string, mixed> */
    private array $originalRequest = [];
    /** @var array<string, mixed> */
    private array $originalServer = [];

    protected function setUp(): void
    {
        $this->originalConfig = Config::getAll();
        $this->originalRequest = $_REQUEST;
        $this->originalServer = $_SERVER;

        Config::setAll(['base_url' => 'https://example.test/admin']);
        $_REQUEST = [];
        $_SERVER = [
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/admin/index.php?page=users',
        ];
    }

    protected function tearDown(): void
    {
        Config::setAll($this->originalConfig);
        $_REQUEST = $this->originalRequest;
        $_SERVER = $this->originalServer;
    }

    public function testGetTokenNameAndFormStartDefaults(): void
    {
        $this->assertSame('token_users', ObjectToForm::getTokenName('users'));

        $html = ObjectToForm::start('users');

        $this->assertStringContainsString('<form method="post"', $html);
        $this->assertStringContainsString('action="https://example.test/admin/"', $html);
        $this->assertStringContainsString('id="formusers"', $html);
        $this->assertStringContainsString('name="page" value="users"', $html);
        $this->assertStringContainsString('name="action" value="save"', $html);
        $this->assertStringContainsString('needs-validation js-needs-validation', $html);
    }

    public function testFormStartSupportsJsonCustomDataAndAttributes(): void
    {
        $html = ObjectToForm::start(
            'products',
            'store',
            ['id' => 'custom-form', 'class' => 'my-form', 'enctype' => 'multipart/form-data'],
            true,
            ['id' => 99, 'mode' => 'quick']
        );

        $this->assertStringContainsString('id="custom-form"', $html);
        $this->assertStringContainsString('class="my-form needs-validation js-needs-validation"', $html);
        $this->assertStringContainsString('data-ajax-submit="true"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('name="page" value="products"', $html);
        $this->assertStringContainsString('name="action" value="store"', $html);
        $this->assertStringContainsString('name="id" value="99"', $html);
        $this->assertStringContainsString('name="mode" value="quick"', $html);
    }

    public function testGetInputNormalizesTimeAndTimestampAndHiddenName(): void
    {
        $timeInput = ObjectToForm::getInput(
            ['name' => 'appointment', 'type' => 'time', 'label' => 'Appointment'],
            '9.30'
        );
        $this->assertStringContainsString('type="time"', $timeInput);
        $this->assertStringContainsString('value="09:30"', $timeInput);
        $this->assertStringContainsString('name="data[appointment]"', $timeInput);

        $timestampInput = ObjectToForm::getInput(
            ['name' => 'published_at', 'type' => 'timestamp', 'label' => 'Published At'],
            1700000000
        );
        $this->assertStringContainsString('type="datetime-local"', $timestampInput);
        $this->assertStringContainsString('name="data[published_at]"', $timestampInput);
    }

    public function testRowAndSubmitHelpers(): void
    {
        $hiddenRow = ObjectToForm::row(
            ['name' => 'secret', 'type' => 'hidden', 'label' => 'Secret'],
            'abc'
        );
        $this->assertStringContainsString('type="hidden"', $hiddenRow);
        $this->assertStringNotContainsString('class="mb-3"', $hiddenRow);

        $textRow = ObjectToForm::row(
            ['name' => 'title', 'type' => 'string', 'label' => 'Title'],
            'Milk'
        );
        $this->assertStringContainsString('class="mb-3"', $textRow);
        $this->assertStringContainsString('name="data[title]"', $textRow);

        $button = ObjectToForm::submit('Save now', ['class' => 'w-100']);
        $this->assertStringContainsString('type="submit"', $button);
        $this->assertStringContainsString('w-100 btn btn-primary', $button);
        $this->assertStringContainsString('Save now', $button);

        $this->assertSame('</form>', ObjectToForm::end());
    }
}
