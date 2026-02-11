<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations;

use DateTimeImmutable;

/**
 * UseCase GetOrganizationInvitationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInvitationResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationInvitationResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the invitation identifier
   * @param string $organizationId the organization identifier
   * @param string $email the invited email
   * @param string $status the invitation status
   * @param string $invitedByUserId the inviter user identifier
   * @param ?string $acceptedByUserId the acceptor user identifier
   * @param ?string $revokedByUserId the revoker user identifier
   * @param DateTimeImmutable $expiresAt the invitation expiration datetime
   * @param DateTimeImmutable $createdAt the invitation creation datetime
   * @param DateTimeImmutable $updatedAt the invitation update datetime
   * @param ?DateTimeImmutable $acceptedAt the invitation acceptance datetime
   * @param ?DateTimeImmutable $revokedAt the invitation revocation datetime
   * @param list<string> $roleIds the invitation role identifiers
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $email,
    public string $status,
    public string $invitedByUserId,
    public ?string $acceptedByUserId,
    public ?string $revokedByUserId,
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $acceptedAt,
    public ?DateTimeImmutable $revokedAt,
    public array $roleIds,
  ) {
  }
  // #endregion
}
