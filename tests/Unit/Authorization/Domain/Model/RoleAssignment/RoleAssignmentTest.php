<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Model\RoleAssignment;

use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleId, SubjectType};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\TenantId;

/**
 * Test RoleAssignmentTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleAssignment::class)]
final class RoleAssignmentTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testAssignToUserCreatesAssignment(): void
  {
    $assignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('550e8400-e29b-41d4-a716-446655440000'),
      roleId: new RoleId('550e8400-e29b-41d4-a716-446655440001'),
      userId: 'user-123',
      tenantId: TenantId::fromString('550e8400-e29b-41d4-a716-446655440099'),
    );

    self::assertSame('user-123', $assignment->subjectId());
    self::assertSame(SubjectType::USER, $assignment->subjectType());
    self::assertFalse($assignment->isExpired());
    self::assertTrue($assignment->isActive());
    self::assertInstanceOf(DateTimeImmutable::class, $assignment->assignedAt());
  }

  #[Test]
  public function testIsExpiredReturnsTrueWhenPast(): void
  {
    $assignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('550e8400-e29b-41d4-a716-446655440010'),
      roleId: new RoleId('550e8400-e29b-41d4-a716-446655440011'),
      userId: 'user-456',
      expiresAt: new DateTimeImmutable('-1 day'),
    );

    self::assertTrue($assignment->isExpired());
    self::assertFalse($assignment->isActive());
  }

  #[Test]
  public function testReconstituteKeepsValuesAndEquals(): void
  {
    $id = new RoleAssignmentId('550e8400-e29b-41d4-a716-446655440020');
    $roleId = new RoleId('550e8400-e29b-41d4-a716-446655440021');
    $assignedAt = new DateTimeImmutable('-2 days');
    $expiresAt = new DateTimeImmutable('+2 days');

    $assignment = RoleAssignment::reconstitute(
      id: $id,
      roleId: $roleId,
      subjectType: SubjectType::USER,
      subjectId: 'user-789',
      tenantId: null,
      assignedAt: $assignedAt,
      expiresAt: $expiresAt,
    );

    $other = RoleAssignment::reconstitute(
      id: $id,
      roleId: $roleId,
      subjectType: SubjectType::USER,
      subjectId: 'user-789',
      tenantId: null,
      assignedAt: $assignedAt,
      expiresAt: $expiresAt,
    );

    self::assertSame($roleId, $assignment->roleId());
    self::assertTrue($assignment->equals($other));
  }
  // #endregion
}
