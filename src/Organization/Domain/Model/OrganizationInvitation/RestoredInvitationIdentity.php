<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationInvitation;

use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId};
use Shared\Domain\ValueObject\Email;

/** Persisted invitation identity and token binding. */
final readonly class RestoredInvitationIdentity
{
  public function __construct(
    public OrganizationInvitationId $id,
    public OrganizationId $organizationId,
    public Email $email,
    public string $tokenHash,
    public string $invitedByUserId,
  ) {
  }
}
