<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ResendOrganizationInvitationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendOrganizationInvitationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ResendOrganizationInvitationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $invitationId the invitation identifier
   * @param string $resentByUserId the resending user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $invitationId,
    public string $resentByUserId,
  ) {
  }
  // #endregion
}
