<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity,
  NonConformityStatus
};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use LogicException;

final class NonConformityMapper
{
  public static function toDomain(NonConformityRecord $record): NonConformity
  {
    if (!$record->inspection instanceof InspectionRecord) {
      throw new LogicException('Non-conformity record must reference an inspection.');
    }

    return NonConformity::reconstitute(
      id: NonConformityId::fromString($record->id),
      inspectionId: NonConformityInspectionId::fromString($record->inspection->id),
      description: $record->description,
      severity: NonConformitySeverity::from($record->severity),
      status: NonConformityStatus::from($record->status),
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      dueAt: $record->dueAt,
      resolvedAt: $record->resolvedAt,
      notes: $record->notes,
    );
  }

  public static function toRecord(NonConformity $nonConformity): NonConformityRecord
  {
    $record = new NonConformityRecord();
    $record->id = (string) $nonConformity->id();
    $record->description = $nonConformity->description();
    $record->severity = $nonConformity->severity()->value;
    $record->status = $nonConformity->status()->value;
    $record->dueAt = $nonConformity->dueAt();
    $record->resolvedAt = $nonConformity->resolvedAt();
    $record->notes = $nonConformity->notes();
    $record->createdAt = $nonConformity->createdAt();
    $record->updatedAt = $nonConformity->updatedAt();

    return $record;
  }
}
