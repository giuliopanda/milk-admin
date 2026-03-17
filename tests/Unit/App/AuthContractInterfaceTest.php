<?php

declare(strict_types=1);

use App\AuthContractInterface;
use PHPUnit\Framework\TestCase;

final class AuthContractInterfaceTest extends TestCase
{
    public function testInterfaceShapeAndMethods(): void
    {
        $reflection = new ReflectionClass(AuthContractInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('getInstance'));
        $this->assertTrue($reflection->hasMethod('getUser'));
        $this->assertTrue($reflection->hasMethod('login'));
        $this->assertTrue($reflection->hasMethod('isAuthenticated'));
        $this->assertTrue($reflection->hasMethod('logout'));
    }

    public function testConcreteImplementationCanRespectContract(): void
    {
        $auth = new class implements AuthContractInterface {
            private static ?self $instance = null;
            private bool $authenticated = false;
            private ?object $user = null;

            public static function getInstance(): self
            {
                if (self::$instance === null) {
                    self::$instance = new self();
                }

                return self::$instance;
            }

            public function getUser($id = 0): mixed
            {
                return $this->user;
            }

            public function login($username_email = '', $password = '', $save_sessions = true): bool
            {
                if ($username_email === '' || $password === '') {
                    return false;
                }

                $this->authenticated = true;
                $this->user = (object) ['id' => 1, 'email' => (string) $username_email];
                return true;
            }

            public function isAuthenticated(): bool
            {
                return $this->authenticated;
            }

            public function logout(): bool
            {
                $this->authenticated = false;
                $this->user = null;
                return true;
            }
        };

        $this->assertFalse($auth->isAuthenticated());
        $this->assertFalse($auth->login('', ''));
        $this->assertTrue($auth->login('user@example.test', 'secret'));
        $this->assertTrue($auth->isAuthenticated());
        $this->assertSame('user@example.test', $auth->getUser()->email);
        $this->assertTrue($auth->logout());
        $this->assertFalse($auth->isAuthenticated());
        $this->assertNull($auth->getUser());
    }
}
