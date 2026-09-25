<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Facility\Domain\Model\Facility\{CanonicalFacility, CanonicalFacilityContent, CanonicalFacilityReference, CanonicalFacilityVersion};
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityRecordStatus, FacilityStatus, FacilityType};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper CanonicalFacilityMapper.
 *
 * Translates between `FacilityRecord` and the canonical model. Distinct from
 * {@see FacilityMapper}, which maps the same record onto the `Facility`
 * aggregate and carries a different — deliberately non-overlapping — set of
 * columns.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalFacilityMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the persisted record
   *
   * @return CanonicalFacility the canonical facility
   */
  public static function toDomain(FacilityRecord $record): CanonicalFacility
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Facility record must reference an organization.');
    }

    return CanonicalFacility::reconstitute(
      reference: new CanonicalFacilityReference(
        FacilityId::fromString($record->id),
        FacilityOrganizationId::fromString($record->organization->id),
        FacilityRecordStatus::from($record->recordStatus),
        $record->interventionId,
        $record->parentFacility?->id,
      ),
      content: new CanonicalFacilityContent(
        FacilityType::from($record->type),
        $record->name,
        $record->code,
        $record->address,
        $record->latitude,
        $record->longitude,
        $record->metadata,
      ),
      version: new CanonicalFacilityVersion(FacilityStatus::from($record->status), $record->revision, $record->updatedAt, $record->levelIndex),
    );
  }

  /**
   * Method applyTo.
   *
   * Writes back the eleven columns the canonical surface may change.
   *
   * The `parentFacility` ASSOCIATION is NOT set here: only the repository
   * holds an entity manager, and only it can turn an identifier into a
   * reference. `record_status`, `intervention_id`, `client_id`,
   * `organization`, `plan_geometry` and `created_at` are absent on purpose —
   * the canonical PATCH and DELETE never move them.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacility $facility the canonical facility
   * @param FacilityRecord $record the record to update
   */
  public static function applyTo(CanonicalFacility $facility, FacilityRecord $record): void
  {
    $record->type = $facility->type()->value;
    $record->name = $facility->name();
    $record->code = $facility->code();
    $record->address = $facility->address();
    $record->latitude = $facility->latitude();
    $record->longitude = $facility->longitude();
    $record->metadata = $facility->metadata();
    $record->levelIndex = $facility->levelIndex();
    $record->status = $facility->status()->value;
    // Doctrine increments and compares the persisted version in the same UPDATE.
    $record->updatedAt = $facility->updatedAt();
  }
  // #endregion
}
