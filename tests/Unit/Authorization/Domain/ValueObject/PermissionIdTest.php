<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\ValueObject;

use Authorization\Domain\ValueObject\PermissionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PermissionIdTest.
 *
 * @category Value Object Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PermissionId::class)]
final class PermissionIdTest extends TestCase
{
  // #region Valid UUID Tests

  /**
   * Test creating PermissionId with valid UUID.
   */
  #[Test]
  public function testCreateWithValidUuid(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = new PermissionId($uuid);

    $this->assertEquals($uuid, $permissionId->value);
  }

  // #endregion

  // #region Invalid UUID Tests

  /**
   * Test creating PermissionId with invalid UUID throws exception.
   */
  #[Test]
  public function testCreateWithInvalidUuidThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new PermissionId('not-a-valid-uuid');
  }

  // #endregion

  // #region Equality Tests

  /**
   * Test PermissionId equality.
   */
  #[Test]
  public function testPermissionIdEquality(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId1 = new PermissionId($uuid);
    $permissionId2 = new PermissionId($uuid);

    $this->assertTrue($permissionId1->equals($permissionId2));
  }

  /**
   * Test PermissionId inequality.
   */
  #[Test]
  public function testPermissionIdInequality(): void
  {
    $permissionId1 = new PermissionId('550e8400-e29b-41d4-a716-446655440000');
    $permissionId2 = new PermissionId('550e8400-e29b-41d4-a716-446655440001');

    $this->assertFalse($permissionId1->equals($permissionId2));
  }

  // #endregion
}
