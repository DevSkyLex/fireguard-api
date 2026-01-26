<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\ValueObject;

use Authorization\Domain\ValueObject\PermissionName;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PermissionNameTest.
 *
 * @category ValueObject Tests
 */
#[CoversClass(className: PermissionName::class)]
final class PermissionNameTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testMatchesWildcardAndExact(): void
  {
    $exact = new PermissionName('users.create');
    $wildcard = new PermissionName('users.*');
    $super = new PermissionName('*.*');

    self::assertTrue($exact->matches(new PermissionName('users.create')));
    self::assertTrue($wildcard->matches(new PermissionName('users.create')));
    self::assertTrue($super->matches(new PermissionName('users.delete')));
    self::assertFalse($exact->matches(new PermissionName('users.delete')));
  }

  #[Test]
  public function testResourceAndAction(): void
  {
    $permission = new PermissionName('users.create');

    self::assertSame('users', $permission->resource());
    self::assertSame('create', $permission->action());
  }

  #[Test]
  public function testSinglePartPermissionMatchesResource(): void
  {
    $resource = new PermissionName('admin');

    self::assertTrue($resource->matches(new PermissionName('admin.read')));
    self::assertSame('admin', $resource->resource());
    self::assertNull($resource->action());
  }

  #[Test]
  public function testActionWildcardMatchesAllResources(): void
  {
    $wildcard = new PermissionName('*.read');

    self::assertTrue($wildcard->matches(new PermissionName('users.read')));
    self::assertFalse($wildcard->matches(new PermissionName('users.write')));
  }

  #[Test]
  public function testEmptyValueThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new PermissionName('');
  }

  #[Test]
  public function testToStringReturnsValue(): void
  {
    $permission = new PermissionName('users.read');

    self::assertSame('users.read', (string) $permission);
  }

  #[Test]
  public function testEquals(): void
  {
    $permission = new PermissionName('users.read');
    $same = new PermissionName('users.read');
    $different = new PermissionName('users.write');

    self::assertTrue($permission->equals($same));
    self::assertFalse($permission->equals($different));
  }

  #[Test]
  public function testInvalidValueThrows(): void
  {
    $this->expectException(InvalidValueException::class);

    new PermissionName('invalid-permission');
  }
  // #endregion
}
