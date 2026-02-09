<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\ValueObject;

use Authorization\Domain\ValueObject\RoleName;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test RoleNameTest.
 *
 * @category Value Object Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleName::class)]
final class RoleNameTest extends TestCase
{
  // #region Valid Name Tests

  /**
   * Test creating RoleName with valid lowercase name.
   */
  #[Test]
  public function testCreateWithValidLowercaseName(): void
  {
    $name = 'admin';
    $roleName = new RoleName($name);

    $this->assertEquals($name, $roleName->value);
  }

  /**
   * Test creating RoleName with underscores.
   */
  #[Test]
  public function testCreateWithUnderscores(): void
  {
    $name = 'super_admin';
    $roleName = new RoleName($name);

    $this->assertEquals($name, $roleName->value);
  }

  /**
   * Test creating RoleName with numbers.
   */
  #[Test]
  public function testCreateWithNumbers(): void
  {
    $name = 'admin2';
    $roleName = new RoleName($name);

    $this->assertEquals($name, $roleName->value);
  }

  // #endregion

  // #region Invalid Name Tests

  /**
   * Test creating RoleName with uppercase throws exception.
   */
  #[Test]
  public function testCreateWithUppercaseThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleName('ADMIN');
  }

  /**
   * Test creating RoleName with empty string throws exception.
   */
  #[Test]
  public function testCreateWithEmptyStringThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleName('');
  }

  /**
   * Test creating RoleName with special characters throws exception.
   */
  #[Test]
  public function testCreateWithSpecialCharactersThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleName('admin@role');
  }

  // #endregion

  // #region Equality Tests

  /**
   * Test RoleName equality.
   */
  #[Test]
  public function testRoleNameEquality(): void
  {
    $roleName1 = new RoleName('admin');
    $roleName2 = new RoleName('admin');

    $this->assertTrue($roleName1->equals($roleName2));
  }

  /**
   * Test RoleName inequality.
   */
  #[Test]
  public function testRoleNameInequality(): void
  {
    $roleName1 = new RoleName('admin');
    $roleName2 = new RoleName('user');

    $this->assertFalse($roleName1->equals($roleName2));
  }

  /**
   * Test RoleName string casting.
   */
  #[Test]
  public function testRoleNameToString(): void
  {
    $roleName = new RoleName('admin');

    $this->assertSame('admin', (string) $roleName);
  }

  // #endregion
}
