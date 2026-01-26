<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Domain\ValueObject\PermissionName;

/**
 * Test PermissionNameTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PermissionName::class)]
final class PermissionNameTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testMatchesWildcard(): void
  {
    $permission = new PermissionName('users.*');

    self::assertTrue($permission->matches(new PermissionName('users.create')));
  }

  #[Test]
  public function testMatchesExactAndWildcardAll(): void
  {
    $exact = new PermissionName('users.create');
    $wildcard = new PermissionName('*.*');

    self::assertTrue($exact->matches(new PermissionName('users.create')));
    self::assertTrue($wildcard->matches(new PermissionName('users.delete')));
  }

  #[Test]
  public function testMatchesReturnsFalseWhenResourceDiffers(): void
  {
    $permission = new PermissionName('users.*');

    self::assertFalse($permission->matches(new PermissionName('clients.read')));
  }

  #[Test]
  public function testResourceAndActionAccessors(): void
  {
    $permission = new PermissionName('users.create');

    self::assertSame('users', $permission->resource());
    self::assertSame('create', $permission->action());
  }

  #[Test]
  public function testEqualsAndToString(): void
  {
    $permission = new PermissionName('users.read');

    self::assertSame('users.read', (string) $permission);
    self::assertTrue($permission->equals(new PermissionName('users.read')));
    self::assertFalse($permission->equals(new PermissionName('users.write')));
  }

  #[Test]
  public function testInvalidNameThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new PermissionName('invalid-name');
  }

  #[Test]
  public function testEmptyNameThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new PermissionName('');
  }
  // #endregion
}
