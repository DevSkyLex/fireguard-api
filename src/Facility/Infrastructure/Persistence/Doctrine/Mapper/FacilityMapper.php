<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityStatus,
  FacilityType
};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;

/**
 * Mapper FacilityMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine facility record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the persistence record
   *
   * @return Facility the domain aggregate
   */
  public static function toDomain(FacilityRecord $record): Facility
  {
    return Facility::reconstitute(
      id: FacilityId::fromString($record->id),
      organizationId: FacilityOrganizationId::fromString($record->organizationId),
      parentFacilityId: null !== $record->parentFacilityId ? FacilityId::fromString($record->parentFacilityId) : null,
      type: FacilityType::from($record->type),
      name: new FacilityName($record->name),
      code: $record->code,
      status: FacilityStatus::from($record->status),
      address: $record->address,
      metadata: $record->metadata,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps a facility aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the domain aggregate
   *
   * @return FacilityRecord the persistence record
   */
  public static function toRecord(Facility $facility): FacilityRecord
  {
    $record = new FacilityRecord();
    $record->id = (string) $facility->id();
    $record->organizationId = (string) $facility->organizationId();
    $record->parentFacilityId = $facility->parentFacilityId()?->__toString();
    $record->type = $facility->type()->value;
    $record->name = (string) $facility->name();
    $record->code = $facility->code();
    $record->status = $facility->status()->value;
    $record->address = $facility->address();
    $record->metadata = $facility->metadata();
    $record->createdAt = $facility->createdAt();
    $record->updatedAt = $facility->updatedAt();

    return $record;
  }
  // #endregion
}
