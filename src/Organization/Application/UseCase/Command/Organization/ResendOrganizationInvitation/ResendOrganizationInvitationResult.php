<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ResendOrganizationInvitationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendOrganizationInvitationResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ResendOrganizationInvitationResult class.
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
   * @param string $acceptUrl the fresh public accept link
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
    public string $acceptUrl,
  ) {
  }
  // #endregion
}
