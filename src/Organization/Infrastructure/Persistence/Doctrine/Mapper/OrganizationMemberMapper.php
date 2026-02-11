<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Mapper;

use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationMemberRecord;

/**
 * Mapper OrganizationMemberMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMemberMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine member record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRecord $record the persistence record
   *
   * @return OrganizationMember the domain aggregate
   */
  public static function toDomain(OrganizationMemberRecord $record): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: OrganizationMemberId::fromString($record->id),
      organizationId: OrganizationId::fromString($record->organizationId),
      userId: $record->userId,
      isActive: $record->isActive,
      joinedAt: $record->joinedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps an organization member domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param OrganizationMember $member the domain aggregate
   *
   * @return OrganizationMemberRecord the persistence record
   */
  public static function toRecord(OrganizationMember $member): OrganizationMemberRecord
  {
    $record = new OrganizationMemberRecord();
    $record->id = (string) $member->id();
    $record->organizationId = (string) $member->organizationId();
    $record->userId = $member->userId();
    $record->isActive = $member->isActive();
    $record->joinedAt = $member->joinedAt();

    return $record;
  }
  // #endregion
}
