<?php

declare(strict_types=1);

use App\Hooks;
use App\Mail;
use PHPUnit\Framework\TestCase;

final class MailTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalHooks = [];

    protected function setUp(): void
    {
        $this->originalHooks = $this->getHooks();
        $this->setHooks([]);
    }

    protected function tearDown(): void
    {
        $this->setHooks($this->originalHooks);
    }

    public function testBasicFluentConfigurationApis(): void
    {
        $mail = new Mail();

        $this->assertInstanceOf(Mail::class, $mail->charset('ISO-8859-1'));
        $this->assertInstanceOf(Mail::class, $mail->subject('Unit test'));
        $this->assertInstanceOf(Mail::class, $mail->message('<b>Hello</b>'));
        $this->assertInstanceOf(Mail::class, $mail->isHTML(true));
    }

    public function testInvalidAddressSetsErrorState(): void
    {
        $mail = new Mail();
        $mail->to('invalid-address');

        $this->assertTrue($mail->hasError());
        $this->assertNotSame('', $mail->getError());
        $this->assertSame($mail->getError(), $mail->getLastError());
    }

    public function testMissingTemplateAndAttachmentSetError(): void
    {
        $mail = new Mail();

        $mail->loadTemplate(MILK_DIR . '/not-found-template.php');
        $this->assertTrue($mail->hasError());

        $mail->addAttachment(MILK_DIR . '/not-found-file.txt');
        $this->assertTrue($mail->hasError());
    }

    public function testSendCanBeCancelledByHook(): void
    {
        Hooks::set('mail_before_send', static fn () => false);

        $mail = new Mail();
        $mail->to('test@example.test')->subject('Subject')->message('Body');

        $result = $mail->send();
        $this->assertFalse($result);
        $this->assertSame('Send cancelled by hook', $mail->getLastError());
    }

    /**
     * @return array<string, mixed>
     */
    private function getHooks(): array
    {
        $reflection = new ReflectionClass(Hooks::class);
        $property = $reflection->getProperty('functions');

        /** @var array<string, mixed> $hooks */
        $hooks = $property->getValue();
        return $hooks;
    }

    /**
     * @param array<string, mixed> $hooks
     */
    private function setHooks(array $hooks): void
    {
        $reflection = new ReflectionClass(Hooks::class);
        $property = $reflection->getProperty('functions');
        $property->setValue(null, $hooks);
    }
}
