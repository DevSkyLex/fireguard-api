<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use SensitiveParameter;

/** Enqueues delivery in the same main transaction as the new invitation. */
interface InvitationDeliveryQueuePort
{
  public function enqueue(string $invitationId, #[SensitiveParameter] string $acceptUrl, string $tokenHash): void;
}
