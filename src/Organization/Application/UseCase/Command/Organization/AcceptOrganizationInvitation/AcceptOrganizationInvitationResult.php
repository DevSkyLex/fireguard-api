<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AcceptOrganizationInvitationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AcceptOrganizationInvitationResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AcceptOrganizationInvitationResult class.
   *
   * @since 1.0.0
   *
   * @param string $invitationId the invitation identifier
   * @param string $memberId the organization member identifier
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   * @param list<string> $roleIds the assigned role identifiers
   * @param bool $isActive whether membership is active
   * @param DateTimeImmutable $joinedAt the membership joined datetime
   */
  public function __construct(
    public string $invitationId,
    public string $memberId,
    public string $organizationId,
    public string $userId,
    public array $roleIds,
    public bool $isActive,
    public DateTimeImmutable $joinedAt,
  ) {
  }
  // #endregion
}
