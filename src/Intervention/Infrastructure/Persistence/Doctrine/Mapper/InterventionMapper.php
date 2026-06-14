<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use LogicException;
use Intervention\Domain\Model\Intervention\Intervention;
use Intervention\Domain\ValueObject\{InterventionPriority, InterventionStatus, InterventionType};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper InterventionMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionMapper
{
  /**
   * Method toDomain.
   *
   * Executes the to domain operation.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $record the record value
   *
   * @return Intervention the to domain result
   */
  public static function toDomain(InterventionRecord $record): Intervention
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Intervention record must reference an organization.');
    }

    return Intervention::reconstitute(
      id: $record->id,
      organizationId: $record->organization->id,
      type: InterventionType::from($record->type),
      name: $record->name,
      status: InterventionStatus::from($record->status),
      referencePackId: $record->referencePackId,
      siteId: $record->siteId,
      responsibleId: $record->responsibleId,
      participants: $record->participants,
      priority: InterventionPriority::from($record->priority),
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
   * @param Intervention $intervention the intervention value
   *
   * @return InterventionRecord the to record result
   */
  public static function toRecord(Intervention $intervention): InterventionRecord
  {
    return self::sync($intervention, new InterventionRecord());
  }

  /**
   * Method sync.
   *
   * Executes the sync operation.
   *
   * @since 1.0.0
   *
   * @param Intervention $intervention the intervention value
   * @param InterventionRecord $record the record value
   *
   * @return InterventionRecord the sync result
   */
  public static function sync(Intervention $intervention, InterventionRecord $record): InterventionRecord
  {
    $record->id = $intervention->id();
    $record->type = $intervention->type()->value;
    $record->name = $intervention->name();
    $record->status = $intervention->status()->value;
    $record->referencePackId = $intervention->referencePackId();
    $record->siteId = $intervention->siteId();
    $record->responsibleId = $intervention->responsibleId();
    $record->participants = $intervention->participants();
    $record->priority = $intervention->priority()->value;
    $record->plannedStartAt = $intervention->plannedStartAt();
    $record->dueAt = $intervention->dueAt();
    $record->reviewNote = $intervention->reviewNote();
    $record->revision = $intervention->revision();
    $record->createdAt = $intervention->createdAt();
    $record->updatedAt = $intervention->updatedAt();

    return $record;
  }
}
