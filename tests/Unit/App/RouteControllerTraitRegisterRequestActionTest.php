<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/App/RouteControllerTraitRegisterRequestActionTest.php
 */

declare(strict_types=1);

if (!defined('MILK_TEST_CONTEXT')) {
    define('MILK_TEST_CONTEXT', true);
}
if (!defined('MILK_API_CONTEXT')) {
    define('MILK_API_CONTEXT', true);
}

require_once dirname(__DIR__, 3) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use App\Abstracts\Traits\RouteControllerTrait;
use PHPUnit\Framework\TestCase;

final class RouteControllerTraitRegisterRequestActionTest extends TestCase
{
    public function testReturnsFalseWhenActionAlreadyRegisteredProgrammatically(): void
    {
        $controller = new class {
            use RouteControllerTrait;

            public ?object $controller = null;
            public array $loaded_extensions = [];
            public mixed $title = null;
            public mixed $model = null;
            public mixed $module = null;
            public mixed $page = null;

            public function access(): bool
            {
                return true;
            }

            public function getPermissionName(): string
            {
                return 'access';
            }

            public function handlerOne(): void {}
            public function handlerTwo(): void {}
        };

        $this->assertTrue($controller->registerRequestAction('test', 'handlerOne'));
        $this->assertFalse($controller->registerRequestAction('test', 'handlerTwo'));

        $routeMap = $this->getPrivateProperty($controller, 'routeMap');
        $this->assertSame('handlerOne', $routeMap['test'] ?? null);
    }

    public function testReturnsFalseWhenActionIsDeclaredViaAttributeOnSameClass(): void
    {
        $controller = new class {
            use RouteControllerTrait;

            public ?object $controller = null;
            public array $loaded_extensions = [];
            public mixed $title = null;
            public mixed $model = null;
            public mixed $module = null;
            public mixed $page = null;

            public function access(): bool
            {
                return true;
            }

            public function getPermissionName(): string
            {
                return 'access';
            }

            #[\App\Attributes\RequestAction('attr-action')]
            public function attrAction(): void {}

            public function handlerOne(): void {}
        };

        $this->assertFalse($controller->registerRequestAction('attr-action', 'handlerOne'));
    }

    private function getPrivateProperty(object $object, string $name): mixed
    {
        $ref = new ReflectionObject($object);
        while ($ref) {
            if ($ref->hasProperty($name)) {
                $scope = $ref->getName();
                $getter = \Closure::bind(
                    function () use ($name) {
                        return $this->$name;
                    },
                    $object,
                    $scope
                );
                return $getter();
            }
            $ref = $ref->getParentClass();
        }

        $this->fail("Property '{$name}' not found on object.");
    }
}
