<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId, ChecklistStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistItemRecord, ChecklistRecord};

final class ChecklistMapper
{
  /**
   * @param list<ChecklistItemRecord> $itemRecords
   */
  public static function toDomain(ChecklistRecord $record, array $itemRecords = []): Checklist
  {
    $items = [];

    foreach ($itemRecords as $itemRecord) {
      $items[] = ChecklistItem::reconstitute(
        id: $itemRecord->id,
        label: $itemRecord->label,
        position: $itemRecord->position,
        required: $itemRecord->required,
        description: $itemRecord->description,
      );
    }

    return Checklist::reconstitute(
      id: ChecklistId::fromString($record->id),
      organizationId: ChecklistOrganizationId::fromString($record->organizationId),
      name: $record->name,
      version: $record->version,
      status: ChecklistStatus::from($record->status),
      items: $items,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  public static function toRecord(Checklist $checklist): ChecklistRecord
  {
    $record = new ChecklistRecord();
    $record->id = (string) $checklist->id();
    $record->organizationId = (string) $checklist->organizationId();
    $record->name = $checklist->name();
    $record->version = $checklist->version();
    $record->status = $checklist->status()->value;
    $record->createdAt = $checklist->createdAt();
    $record->updatedAt = $checklist->updatedAt();

    return $record;
  }

  /**
   * @return list<ChecklistItemRecord>
   */
  public static function toItemRecords(Checklist $checklist): array
  {
    $records = [];

    foreach ($checklist->items() as $item) {
      $record = new ChecklistItemRecord();
      $record->id = $item->id();
      $record->checklistId = (string) $checklist->id();
      $record->label = $item->label();
      $record->position = $item->position();
      $record->required = $item->required();
      $record->description = $item->description();
      $records[] = $record;
    }

    return $records;
  }
}
