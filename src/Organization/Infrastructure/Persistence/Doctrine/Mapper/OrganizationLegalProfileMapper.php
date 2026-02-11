<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Mapper;

use Organization\Domain\Model\OrganizationLegalProfile\OrganizationLegalProfile;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationLegalName, OrganizationRegistrationNumber, OrganizationVatNumber};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationLegalProfileRecord;

/**
 * Mapper OrganizationLegalProfileMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationLegalProfileMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine legal profile record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationLegalProfileRecord $record the persistence record
   *
   * @return OrganizationLegalProfile the domain aggregate
   */
  public static function toDomain(OrganizationLegalProfileRecord $record): OrganizationLegalProfile
  {
    return OrganizationLegalProfile::reconstitute(
      organizationId: OrganizationId::fromString($record->organizationId),
      legalName: new OrganizationLegalName($record->legalName),
      registrationNumber: null !== $record->registrationNumber ? new OrganizationRegistrationNumber($record->registrationNumber) : null,
      vatNumber: null !== $record->vatNumber ? new OrganizationVatNumber($record->vatNumber) : null,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps an organization legal profile domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param OrganizationLegalProfile $profile the domain aggregate
   *
   * @return OrganizationLegalProfileRecord the persistence record
   */
  public static function toRecord(OrganizationLegalProfile $profile): OrganizationLegalProfileRecord
  {
    $record = new OrganizationLegalProfileRecord();
    $record->organizationId = (string) $profile->organizationId();
    $record->legalName = (string) $profile->legalName();
    $record->registrationNumber = null !== $profile->registrationNumber() ? (string) $profile->registrationNumber() : null;
    $record->vatNumber = null !== $profile->vatNumber() ? (string) $profile->vatNumber() : null;
    $record->createdAt = $profile->createdAt();
    $record->updatedAt = $profile->updatedAt();

    return $record;
  }
  // #endregion
}
