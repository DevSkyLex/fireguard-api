<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Mapper;

use Authorization\Domain\Model\RoleAssignment;
use Authorization\Domain\ValueObject\RoleAssignmentId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleAssignmentRecord;
use Shared\Domain\ValueObject\TenantId;

/**
 * Mapper RoleAssignmentMapper
 * @final
 *
 * Maps between RoleAssignment domain model and RoleAssignmentRecord Doctrine entity.
 *
 * @category Mapper
 * @package Authorization\Infrastructure\Persistence\Doctrine\Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleAssignmentMapper
{
  //#region Methods
  /**
   * Method toDomain
   *
   * Converts a RoleAssignmentRecord to a 
   * RoleAssignment domain model.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignmentRecord $record The record to convert.
   *
   * @return RoleAssignment The domain model.
   */
  public function toDomain(RoleAssignmentRecord $record): RoleAssignment
  {
    return RoleAssignment::reconstitute(
      id: new RoleAssignmentId(value: $record->id),
      roleId: new RoleId(value: $record->roleId),
      subjectType: SubjectType::from(value: $record->subjectType),
      subjectId: $record->subjectId,
      tenantId: $record->tenantId !== null ? TenantId::fromString(value: $record->tenantId) : null,
      assignedAt: $record->assignedAt,
      expiresAt: $record->expiresAt,
    );
  }

  /**
   * Method toRecord
   *
   * Converts a RoleAssignment domain model to a 
   * RoleAssignmentRecord.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignment $assignment The domain model to convert.
   * @param RoleAssignmentRecord|null $record Existing record to update, or null to create new.
   *
   * @return RoleAssignmentRecord The Doctrine record.
   */
  public function toRecord(RoleAssignment $assignment, ?RoleAssignmentRecord $record = null): RoleAssignmentRecord
  {
    $record ??= new RoleAssignmentRecord();

    $record->id = $assignment->id()->value;
    $record->roleId = $assignment->roleId()->value;
    $record->subjectType = $assignment->subjectType()->value;
    $record->subjectId = $assignment->subjectId();
    $record->tenantId = $assignment->tenantId() !== null ? (string) $assignment->tenantId() : null;
    $record->assignedAt = $assignment->assignedAt();
    $record->expiresAt = $assignment->expiresAt();

    return $record;
  }
  //#endregion
}
