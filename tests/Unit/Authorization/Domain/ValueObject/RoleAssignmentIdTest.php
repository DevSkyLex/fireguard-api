<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\ValueObject;

use Authorization\Domain\ValueObject\RoleAssignmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test RoleAssignmentIdTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleAssignmentId::class)]
final class RoleAssignmentIdTest extends TestCase
{
  // #region Valid UUID Tests
  #[Test]
  public function testCreateWithValidUuid(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $id = new RoleAssignmentId($uuid);

    $this->assertSame($uuid, $id->value);
  }
  // #endregion

  // #region Invalid UUID Tests
  #[Test]
  public function testCreateWithInvalidUuidThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new RoleAssignmentId('invalid');
  }
  // #endregion

  // #region Equality Tests
  #[Test]
  public function testRoleAssignmentIdEquality(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440001';
    $id1 = new RoleAssignmentId($uuid);
    $id2 = new RoleAssignmentId($uuid);

    $this->assertTrue($id1->equals($id2));
  }

  #[Test]
  public function testRoleAssignmentIdInequality(): void
  {
    $id1 = new RoleAssignmentId('550e8400-e29b-41d4-a716-446655440002');
    $id2 = new RoleAssignmentId('550e8400-e29b-41d4-a716-446655440003');

    $this->assertFalse($id1->equals($id2));
  }
  // #endregion
}
