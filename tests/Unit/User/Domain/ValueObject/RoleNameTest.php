<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Domain\ValueObject\RoleName;

/**
 * Test RoleNameTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleName::class)]
final class RoleNameTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateWithValidName(): void
  {
    $name = new RoleName('admin');

    $this->assertSame('admin', $name->value);
  }

  #[Test]
  public function testInvalidNameThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleName('bad-name');
  }

  #[Test]
  public function testEmptyNameThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleName('');
  }

  #[Test]
  public function testToStringReturnsValue(): void
  {
    $name = new RoleName('admin');

    $this->assertSame('admin', (string) $name);
  }

  #[Test]
  public function testEqualsComparesValue(): void
  {
    $name = new RoleName('admin');
    $same = new RoleName('admin');
    $different = new RoleName('reader');

    $this->assertTrue($name->equals($same));
    $this->assertFalse($name->equals($different));
  }
  // #endregion
}
