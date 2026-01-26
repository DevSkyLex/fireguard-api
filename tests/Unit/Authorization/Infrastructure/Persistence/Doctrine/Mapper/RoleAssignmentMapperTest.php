<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Mapper;

use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleId, SubjectType};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\RoleAssignmentMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleAssignmentRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\TenantId;

/**
 * Test RoleAssignmentMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: RoleAssignmentMapper::class)]
final class RoleAssignmentMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $record = new RoleAssignmentRecord();
    $record->id = '123e4567-e89b-12d3-a456-426614174000';
    $record->roleId = '223e4567-e89b-12d3-a456-426614174000';
    $record->subjectType = SubjectType::USER->value;
    $record->subjectId = 'user-1';
    $record->tenantId = '323e4567-e89b-12d3-a456-426614174000';
    $record->assignedAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->expiresAt = new DateTimeImmutable('2024-02-01 00:00:00');

    $mapper = new RoleAssignmentMapper();
    $assignment = $mapper->toDomain($record);

    self::assertSame($record->id, $assignment->id()->value);
    self::assertSame($record->roleId, $assignment->roleId()->value);
    self::assertSame($record->subjectId, $assignment->subjectId());
    self::assertSame((string) TenantId::fromString($record->tenantId), (string) $assignment->tenantId());
  }

  #[Test]
  public function testToRecordMapsAssignment(): void
  {
    $assignedAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $expiresAt = new DateTimeImmutable('2024-02-01 00:00:00');
    $tenantId = TenantId::fromString('323e4567-e89b-12d3-a456-426614174000');

    $assignment = RoleAssignment::reconstitute(
      id: new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174000'),
      roleId: new RoleId('223e4567-e89b-12d3-a456-426614174000'),
      subjectType: SubjectType::USER,
      subjectId: 'user-1',
      tenantId: $tenantId,
      assignedAt: $assignedAt,
      expiresAt: $expiresAt,
    );

    $mapper = new RoleAssignmentMapper();
    $record = $mapper->toRecord($assignment);

    self::assertSame($assignment->id()->value, $record->id);
    self::assertSame($assignment->roleId()->value, $record->roleId);
    self::assertSame($assignment->subjectId(), $record->subjectId);
    self::assertSame((string) $tenantId, $record->tenantId);
    self::assertEquals($assignedAt, $record->assignedAt);
    self::assertEquals($expiresAt, $record->expiresAt);
  }
  // #endregion
}
