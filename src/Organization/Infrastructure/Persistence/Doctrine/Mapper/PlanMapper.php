<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Mapper;

use Organization\Domain\Catalog\OrganizationQuotaCatalog;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Organization\Infrastructure\Persistence\Doctrine\Record\PlanRecord;

use function is_int;

/**
 * Mapper PlanMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine plan record to a domain aggregate. Unknown or malformed
   * persisted limit entries are dropped so that retiring a quota resource never
   * breaks plan loading.
   *
   * @since 1.0.0
   *
   * @param PlanRecord $record the persistence record
   *
   * @return Plan the domain aggregate
   */
  public static function toDomain(PlanRecord $record): Plan
  {
    $limits = [];
    foreach ($record->limits as $key => $value) {
      if (OrganizationQuotaCatalog::isValid((string) $key) && is_int($value) && $value >= 0) {
        $limits[(string) $key] = $value;
      }
    }

    return Plan::reconstitute(
      id: PlanId::fromString($record->id),
      key: new PlanKey($record->key),
      name: $record->name,
      limits: $limits,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      description: $record->description,
      isActive: $record->isActive,
      isDefault: $record->isDefault,
      sortOrder: $record->sortOrder,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps a plan domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param Plan $plan the domain aggregate
   *
   * @return PlanRecord the persistence record
   */
  public static function toRecord(Plan $plan): PlanRecord
  {
    $record = new PlanRecord();
    $record->id = (string) $plan->id();
    $record->key = (string) $plan->key();
    $record->name = $plan->name();
    $record->description = $plan->description();
    $record->limits = $plan->limits();
    $record->isActive = $plan->isActive();
    $record->isDefault = $plan->isDefault();
    $record->sortOrder = $plan->sortOrder();
    $record->createdAt = $plan->createdAt();
    $record->updatedAt = $plan->updatedAt();

    return $record;
  }
  // #endregion
}
