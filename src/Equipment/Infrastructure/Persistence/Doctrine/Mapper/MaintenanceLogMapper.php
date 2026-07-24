<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId, MaintenanceLogSource};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentMaintenanceLogRecord;
use LogicException;

/**
 * Mapper MaintenanceLogMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceLogMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   */
  public static function toDomain(EquipmentMaintenanceLogRecord $record): EquipmentMaintenanceLog
  {
    if (null === $record->equipment) {
      throw new LogicException('Maintenance log record must reference an equipment.');
    }

    return EquipmentMaintenanceLog::reconstitute(
      id: MaintenanceLogId::fromString($record->id),
      equipmentId: EquipmentId::fromString($record->equipment->id),
      organizationId: EquipmentOrganizationId::fromString($record->organizationId),
      startedAt: $record->startedAt,
      completedAt: $record->completedAt,
      source: MaintenanceLogSource::from($record->source),
      interventionId: $record->interventionId,
      interventionNumber: $record->interventionNumber,
      workItemAction: $record->workItemAction,
      actorId: $record->actorId,
      summary: $record->summary,
    );
  }

  /**
   * Method toRecord.
   *
   * @since 1.0.0
   */
  public static function toRecord(EquipmentMaintenanceLog $log): EquipmentMaintenanceLogRecord
  {
    $record = new EquipmentMaintenanceLogRecord();
    $record->id = (string) $log->id();
    $record->organizationId = (string) $log->organizationId();
    $record->startedAt = $log->startedAt();
    $record->completedAt = $log->completedAt();
    $record->source = $log->source()->value;
    $record->interventionId = $log->interventionId();
    $record->interventionNumber = $log->interventionNumber();
    $record->workItemAction = $log->workItemAction();
    $record->actorId = $log->actorId();
    $record->summary = $log->summary();

    return $record;
  }
  // #endregion
}
