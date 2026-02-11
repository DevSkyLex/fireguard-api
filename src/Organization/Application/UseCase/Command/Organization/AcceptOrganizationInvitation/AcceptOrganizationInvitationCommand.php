<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AcceptOrganizationInvitationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AcceptOrganizationInvitationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AcceptOrganizationInvitationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $token the invitation token
   * @param string $userId the authenticated user identifier
   * @param string $userEmail the authenticated user email
   */
  public function __construct(
    public string $token,
    public string $userId,
    public string $userEmail,
  ) {
  }
  // #endregion
}
