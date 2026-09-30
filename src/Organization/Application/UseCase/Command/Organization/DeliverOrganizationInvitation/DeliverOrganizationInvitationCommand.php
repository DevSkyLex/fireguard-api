<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeliverOrganizationInvitation;

use SensitiveParameter;
use Shared\Application\Message\CommandMessage;

/** Private queue payload. Treat the temporary acceptance URL as a credential. */
final readonly class DeliverOrganizationInvitationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries a queued invitation delivery payload for the owning invitation.
   *
   * @access public
   *
   * @param string $invitationId invitation whose email is being delivered
   * @param string $acceptUrl temporary acceptance URL; treat it as a credential
   * @param string $tokenHash hash binding this delivery to the current invitation token generation
   *
   * @return void
   */
  public function __construct(
    public string $invitationId,
    #[SensitiveParameter]
    public string $acceptUrl,
    public string $tokenHash,
  ) {
  }
  // #endregion
}
