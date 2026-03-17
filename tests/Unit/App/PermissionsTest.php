<?php

declare(strict_types=1);

use App\Hooks;
use App\Permissions;
use PHPUnit\Framework\TestCase;

final class PermissionsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalPermissions = [];
    /** @var array<string, mixed> */
    private array $originalGroupTitle = [];
    /** @var array<string, mixed> */
    private array $originalUserPermissions = [];
    /** @var array<string, mixed> */
    private array $originalExclusiveGroups = [];
    /** @var array<string, mixed> */
    private array $originalHooks = [];

    protected function setUp(): void
    {
        $this->originalPermissions = Permissions::$permissions;
        $this->originalGroupTitle = Permissions::$group_title;
        $this->originalUserPermissions = Permissions::$user_permissions;
        $this->originalExclusiveGroups = Permissions::$exclusive_groups;
        $this->originalHooks = $this->getHooks();

        Permissions::$permissions = [];
        Permissions::$group_title = [];
        Permissions::$user_permissions = [];
        Permissions::$exclusive_groups = [];
        $this->setHooks([]);
    }

    protected function tearDown(): void
    {
        Permissions::$permissions = $this->originalPermissions;
        Permissions::$group_title = $this->originalGroupTitle;
        Permissions::$user_permissions = $this->originalUserPermissions;
        Permissions::$exclusive_groups = $this->originalExclusiveGroups;
        $this->setHooks($this->originalHooks);
    }

    public function testSetAndGetPermissionsAndGroups(): void
    {
        Permissions::set('auth', [
            'manage' => 'Manage users',
            'delete' => 'Delete users',
        ], 'Authentication');

        $this->assertSame(['manage' => 'Manage users', 'delete' => 'Delete users'], Permissions::get('auth'));
        $this->assertSame('Authentication', Permissions::getGroupTitle('auth'));
        $this->assertSame(['auth' => 'Authentication'], Permissions::getGroups());
    }

    public function testSetGroupTitleAndGetFallback(): void
    {
        Permissions::setGroupTitle('custom', 'Custom Group');
        $this->assertSame('Custom Group', Permissions::getGroupTitle('custom'));
        $this->assertSame('', Permissions::getGroupTitle('missing'));
    }

    public function testExclusiveGroupKeepsOnlyOneActivePermission(): void
    {
        Permissions::set('status', [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ], 'Status', true);

        $this->assertTrue(Permissions::isExclusiveGroup('status'));

        Permissions::setUserPermissions('status', [
            'draft' => true,
            'published' => true,
            'archived' => true,
        ]);

        $user = Permissions::getUserPermissions();
        $this->assertTrue($user['status']['draft']);
        $this->assertFalse($user['status']['published']);
        $this->assertFalse($user['status']['archived']);

        Permissions::setExclusiveGroup('status', false);
        $this->assertFalse(Permissions::isExclusiveGroup('status'));
    }

    public function testCheckForUserFlagsAdminAndNormalPermissions(): void
    {
        Permissions::set('_user', [
            'is_admin' => 'Admin',
            'is_guest' => 'Guest',
        ], 'User');
        Permissions::set('posts', [
            'view' => 'View posts',
            'edit' => 'Edit posts',
        ], 'Posts');

        Permissions::setUserPermissions('_user', ['is_admin' => false, 'is_guest' => true]);
        Permissions::setUserPermissions('posts', ['view' => true, 'edit' => false]);

        $this->assertTrue(Permissions::check('_user.is_guest'));
        $this->assertFalse(Permissions::check('_user.is_authenticated'));
        $this->assertTrue(Permissions::check('posts.view'));
        $this->assertFalse(Permissions::check('posts.edit'));

        Permissions::setUserPermissions('_user', ['is_admin' => true, 'is_guest' => false]);
        $this->assertTrue(Permissions::check('posts.edit'));
    }

    public function testCheckCanRunHookForPostProcessing(): void
    {
        Permissions::set('_user', ['is_admin' => 'Admin', 'is_guest' => 'Guest'], 'User');
        Permissions::set('docs', ['read' => 'Read docs'], 'Docs');
        Permissions::setUserPermissions('_user', ['is_admin' => false, 'is_guest' => false]);
        Permissions::setUserPermissions('docs', ['read' => true]);

        Hooks::set('permissioncheckdocsread', static fn ($result) => !$result);

        $this->assertFalse(Permissions::check('docs.read', 'docsread'));
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
