<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use SensitiveParameter;

/** Enqueues delivery in the same main transaction as the new invitation. */
interface InvitationDeliveryQueuePort
{
  /**
   * Method enqueue.
   *
   * Enqueues invitation delivery for processing in the invitation creation transaction.
   *
   * @access public
   *
   * @param string $invitationId the invitation identifier
   * @param string $acceptUrl the acceptance URL passed to the delivery worker
   * @param string $tokenHash the invitation token hash used to validate current state
   *
   * @return void no return value
   */
  public function enqueue(string $invitationId, #[SensitiveParameter] string $acceptUrl, string $tokenHash): void;
}
