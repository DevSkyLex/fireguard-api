<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeliverOrganizationInvitation;

use SensitiveParameter;
use Shared\Application\Message\CommandMessage;

/** Private queue payload. Treat the temporary acceptance URL as a credential. */
final readonly class DeliverOrganizationInvitationCommand implements CommandMessage
{
  public function __construct(
    public string $invitationId,
    #[SensitiveParameter]
    public string $acceptUrl,
    public string $tokenHash,
  ) {
  }
}
