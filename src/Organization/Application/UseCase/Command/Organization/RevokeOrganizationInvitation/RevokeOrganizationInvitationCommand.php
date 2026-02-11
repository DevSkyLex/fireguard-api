<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RevokeOrganizationInvitation;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RevokeOrganizationInvitationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeOrganizationInvitationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RevokeOrganizationInvitationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $invitationId the invitation identifier
   * @param string $revokedByUserId the revoker user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $invitationId,
    public string $revokedByUserId,
  ) {
  }
  // #endregion
}
