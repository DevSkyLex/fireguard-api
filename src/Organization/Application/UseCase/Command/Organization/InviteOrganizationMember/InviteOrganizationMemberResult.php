<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\InviteOrganizationMember;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase InviteOrganizationMemberResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InviteOrganizationMemberResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InviteOrganizationMemberResult class.
   *
   * @since 1.0.0
   *
   * @param string $invitationId the invitation identifier
   * @param string $organizationId the organization identifier
   * @param string $email the invited email
   * @param string $status the invitation status
   * @param string $invitedByUserId the inviter user identifier
   * @param DateTimeImmutable $expiresAt the invitation expiration datetime
   * @param DateTimeImmutable $createdAt the invitation creation datetime
   * @param DateTimeImmutable $updatedAt the invitation update datetime
   * @param list<string> $roleIds the invitation role identifiers
   * @param string $acceptUrl the public accept link (empty when not issued)
   */
  public function __construct(
    public string $invitationId,
    public string $organizationId,
    public string $email,
    public string $status,
    public string $invitedByUserId,
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public array $roleIds,
    public string $acceptUrl = '',
  ) {
  }
  // #endregion
}
