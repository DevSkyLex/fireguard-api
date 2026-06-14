<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Persistence\Doctrine\Mapper;

use LogicException;
use Mission\Domain\Model\Mission\Mission;
use Mission\Domain\ValueObject\{MissionPriority, MissionStatus, MissionType};
use Mission\Infrastructure\Persistence\Doctrine\Record\MissionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper MissionMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionMapper
{
  /**
   * Method toDomain.
   *
   * Executes the to domain operation.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $record the record value
   *
   * @return Mission the to domain result
   */
  public static function toDomain(MissionRecord $record): Mission
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Mission record must reference an organization.');
    }

    return Mission::reconstitute(
      id: $record->id,
      organizationId: $record->organization->id,
      type: MissionType::from($record->type),
      name: $record->name,
      status: MissionStatus::from($record->status),
      referencePackId: $record->referencePackId,
      siteId: $record->siteId,
      responsibleId: $record->responsibleId,
      participants: $record->participants,
      priority: MissionPriority::from($record->priority),
      plannedStartAt: $record->plannedStartAt,
      dueAt: $record->dueAt,
      reviewNote: $record->reviewNote,
      revision: $record->revision,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Executes the to record operation.
   *
   * @since 1.0.0
   *
   * @param Mission $mission the mission value
   *
   * @return MissionRecord the to record result
   */
  public static function toRecord(Mission $mission): MissionRecord
  {
    return self::sync($mission, new MissionRecord());
  }

  /**
   * Method sync.
   *
   * Executes the sync operation.
   *
   * @since 1.0.0
   *
   * @param Mission $mission the mission value
   * @param MissionRecord $record the record value
   *
   * @return MissionRecord the sync result
   */
  public static function sync(Mission $mission, MissionRecord $record): MissionRecord
  {
    $record->id = $mission->id();
    $record->type = $mission->type()->value;
    $record->name = $mission->name();
    $record->status = $mission->status()->value;
    $record->referencePackId = $mission->referencePackId();
    $record->siteId = $mission->siteId();
    $record->responsibleId = $mission->responsibleId();
    $record->participants = $mission->participants();
    $record->priority = $mission->priority()->value;
    $record->plannedStartAt = $mission->plannedStartAt();
    $record->dueAt = $mission->dueAt();
    $record->reviewNote = $mission->reviewNote();
    $record->revision = $mission->revision();
    $record->createdAt = $mission->createdAt();
    $record->updatedAt = $mission->updatedAt();

    return $record;
  }
}
