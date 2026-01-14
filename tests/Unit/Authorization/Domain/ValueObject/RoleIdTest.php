<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\ValueObject;

use Authorization\Domain\ValueObject\RoleId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test RoleIdTest.
 *
 * @category Value Object Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleId::class)]
final class RoleIdTest extends TestCase
{
  // #region Valid UUID Tests

  /**
   * Test creating RoleId with valid UUID v4.
   */
  #[Test]
  public function testCreateWithValidUuidV4(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $roleId = new RoleId($uuid);

    $this->assertEquals($uuid, $roleId->value);
  }

  /**
   * Test creating RoleId with valid UUID v7.
   */
  #[Test]
  public function testCreateWithValidUuidV7(): void
  {
    $uuid = '019364f1-9b6c-7d3e-8f4e-2a1b3c4d5e6f';
    $roleId = new RoleId($uuid);

    $this->assertEquals($uuid, $roleId->value);
  }

  // #endregion

  // #region Invalid UUID Tests

  /**
   * Test creating RoleId with invalid UUID throws exception.
   */
  #[Test]
  public function testCreateWithInvalidUuidThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleId('not-a-valid-uuid');
  }

  /**
   * Test creating RoleId with empty string throws exception.
   */
  #[Test]
  public function testCreateWithEmptyStringThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleId('');
  }

  // #endregion

  // #region Equality Tests

  /**
   * Test RoleId equality.
   */
  #[Test]
  public function testRoleIdEquality(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $roleId1 = new RoleId($uuid);
    $roleId2 = new RoleId($uuid);

    $this->assertTrue($roleId1->equals($roleId2));
  }

  /**
   * Test RoleId inequality.
   */
  #[Test]
  public function testRoleIdInequality(): void
  {
    $roleId1 = new RoleId('550e8400-e29b-41d4-a716-446655440000');
    $roleId2 = new RoleId('550e8400-e29b-41d4-a716-446655440001');

    $this->assertFalse($roleId1->equals($roleId2));
  }

  // #endregion
}
