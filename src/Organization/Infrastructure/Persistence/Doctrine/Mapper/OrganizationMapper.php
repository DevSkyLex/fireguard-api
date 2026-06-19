<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Mapper;

use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{
  OrganizationId,
  OrganizationName,
  OrganizationSettings,
  OrganizationSlug,
  OrganizationStatus,
  PlanId
};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper OrganizationMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine organization record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationRecord $record the persistence record
   *
   * @return Organization the domain aggregate
   */
  public static function toDomain(OrganizationRecord $record): Organization
  {
    // Resolve status from explicit persisted value with a legacy fallback.
    $status = '' !== $record->status
      ? OrganizationStatus::from($record->status)
      : OrganizationStatus::fromIsActive($record->isActive);

    return Organization::reconstitute(
      id: OrganizationId::fromString($record->id),
      name: new OrganizationName($record->name),
      createdByUserId: $record->createdByUserId,
      isActive: $record->isActive,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      ownerUserId: $record->ownerUserId,
      slug: new OrganizationSlug($record->slug),
      status: $status,
      description: $record->description,
      logoUrl: $record->logoUrl,
      settings: OrganizationSettings::fromArray($record->settings),
      planId: null !== $record->planId ? PlanId::fromString($record->planId) : null,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps an organization domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param Organization $organization the domain aggregate
   *
   * @return OrganizationRecord the persistence record
   */
  public static function toRecord(Organization $organization): OrganizationRecord
  {
    $record = new OrganizationRecord();
    $record->id = (string) $organization->id();
    $record->name = (string) $organization->name();
    $record->slug = (string) $organization->slug();
    $record->ownerUserId = $organization->ownerUserId();
    $record->createdByUserId = $organization->createdByUserId();
    $record->status = $organization->status()->value;
    $record->isActive = $organization->isActive();
    $record->description = $organization->description();
    $record->logoUrl = $organization->logoUrl();
    $record->settings = $organization->settings()->toArray();
    $record->planId = null !== $organization->planId() ? (string) $organization->planId() : null;
    $record->createdAt = $organization->createdAt();
    $record->updatedAt = $organization->updatedAt();

    return $record;
  }
  // #endregion
}
