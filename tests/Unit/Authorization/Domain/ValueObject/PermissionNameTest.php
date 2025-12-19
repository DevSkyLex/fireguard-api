<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\ValueObject;

use Authorization\Domain\ValueObject\PermissionName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test PermissionNameTest.
 *
 * @category Value Object Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PermissionName::class)]
final class PermissionNameTest extends TestCase
{
    // #region Valid Name Tests

    /**
     * Test creating PermissionName with resource.action format.
     */
    #[Test]
    public function testCreateWithValidResourceActionFormat(): void
    {
        $name = 'users.create';
        $permissionName = new PermissionName($name);

        $this->assertEquals($name, $permissionName->value);
    }

    /**
     * Test creating PermissionName with wildcard action.
     */
    #[Test]
    public function testCreateWithWildcardAction(): void
    {
        $name = 'users.*';
        $permissionName = new PermissionName($name);

        $this->assertEquals($name, $permissionName->value);
    }

    /**
     * Test creating PermissionName with super wildcard.
     */
    #[Test]
    public function testCreateWithSuperWildcard(): void
    {
        $name = '*.*';
        $permissionName = new PermissionName($name);

        $this->assertEquals($name, $permissionName->value);
    }

    // #endregion

    // #endregion

    // #region Matching Tests

    /**
     * Test exact match.
     */
    #[Test]
    public function testExactMatch(): void
    {
        $permissionName = new PermissionName('users.create');
        $required = new PermissionName('users.create');

        $this->assertTrue($permissionName->matches($required));
    }

    /**
     * Test wildcard matches specific action.
     */
    #[Test]
    public function testWildcardMatchesSpecificAction(): void
    {
        $permissionName = new PermissionName('users.*');
        $required = new PermissionName('users.create');

        $this->assertTrue($permissionName->matches($required));
    }

    /**
     * Test super wildcard matches any permission.
     */
    #[Test]
    public function testSuperWildcardMatchesAnyPermission(): void
    {
        $permissionName = new PermissionName('*.*');
        $required = new PermissionName('clients.delete');

        $this->assertTrue($permissionName->matches($required));
    }

    /**
     * Test wildcard does not match different resource.
     */
    #[Test]
    public function testWildcardDoesNotMatchDifferentResource(): void
    {
        $permissionName = new PermissionName('users.*');
        $required = new PermissionName('clients.read');

        $this->assertFalse($permissionName->matches($required));
    }

    /**
     * Test specific does not match different action.
     */
    #[Test]
    public function testSpecificDoesNotMatchDifferentAction(): void
    {
        $permissionName = new PermissionName('users.create');
        $required = new PermissionName('users.delete');

        $this->assertFalse($permissionName->matches($required));
    }

    // #endregion

    // #region Equality Tests

    /**
     * Test PermissionName equality.
     */
    #[Test]
    public function testPermissionNameEquality(): void
    {
        $permissionName1 = new PermissionName('users.create');
        $permissionName2 = new PermissionName('users.create');

        $this->assertTrue($permissionName1->equals($permissionName2));
    }

    /**
     * Test PermissionName inequality.
     */
    #[Test]
    public function testPermissionNameInequality(): void
    {
        $permissionName1 = new PermissionName('users.create');
        $permissionName2 = new PermissionName('users.delete');

        $this->assertFalse($permissionName1->equals($permissionName2));
    }

    // #endregion
}
