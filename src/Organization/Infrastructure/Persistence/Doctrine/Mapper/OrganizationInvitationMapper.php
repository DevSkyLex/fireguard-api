<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Mapper;

use LogicException;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationRecord};
use Shared\Domain\ValueObject\Email;

/**
 * Mapper OrganizationInvitationMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationInvitationMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine invitation record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationRecord $record the persistence record
   *
   * @return OrganizationInvitation the domain aggregate
   */
  public static function toDomain(OrganizationInvitationRecord $record): OrganizationInvitation
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Organization invitation record must reference an organization.');
    }

    return OrganizationInvitation::reconstitute(
      id: OrganizationInvitationId::fromString($record->id),
      organizationId: OrganizationId::fromString($record->organization->id),
      email: new Email($record->email),
      tokenHash: $record->tokenHash,
      invitedByUserId: $record->invitedByUserId,
      status: OrganizationInvitationStatus::from($record->status),
      expiresAt: $record->expiresAt,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      acceptedAt: $record->acceptedAt,
      acceptedByUserId: $record->acceptedByUserId,
      revokedAt: $record->revokedAt,
      revokedByUserId: $record->revokedByUserId,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps an organization invitation domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitation $invitation the domain aggregate
   *
   * @return OrganizationInvitationRecord the persistence record
   */
  public static function toRecord(OrganizationInvitation $invitation): OrganizationInvitationRecord
  {
    $record = new OrganizationInvitationRecord();
    $record->id = (string) $invitation->id();
    $record->email = (string) $invitation->email();
    $record->tokenHash = $invitation->tokenHash();
    $record->invitedByUserId = $invitation->invitedByUserId();
    $record->acceptedByUserId = $invitation->acceptedByUserId();
    $record->revokedByUserId = $invitation->revokedByUserId();
    $record->status = $invitation->status()->value;
    $record->expiresAt = $invitation->expiresAt();
    $record->acceptedAt = $invitation->acceptedAt();
    $record->revokedAt = $invitation->revokedAt();
    $record->createdAt = $invitation->createdAt();
    $record->updatedAt = $invitation->updatedAt();

    return $record;
  }
  // #endregion
}
