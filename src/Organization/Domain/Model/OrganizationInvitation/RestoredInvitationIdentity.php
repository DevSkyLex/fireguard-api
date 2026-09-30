<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationInvitation;

use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId};
use Shared\Domain\ValueObject\Email;

/** Persisted invitation identity and token binding. */
final readonly class RestoredInvitationIdentity
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted invitation identity and its token binding.
   *
   * @access public
   *
   * @param OrganizationInvitationId $id invitation identifier
   * @param OrganizationId $organizationId organization receiving the invitation
   * @param Email $email email address invited to join
   * @param string $tokenHash hash of the invitation acceptance token
   * @param string $invitedByUserId user who sent the invitation
   *
   * @return void
   */
  public function __construct(
    public OrganizationInvitationId $id,
    public OrganizationId $organizationId,
    public Email $email,
    public string $tokenHash,
    public string $invitedByUserId,
  ) {
  }
  // #endregion
}
