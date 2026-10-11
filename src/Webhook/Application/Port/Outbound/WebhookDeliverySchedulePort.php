<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound;

use DateTimeImmutable;

/**
 * Interface WebhookDeliverySchedulePort
 *
 * Atomically reserves and enqueues one wake, joining the caller's main transaction.
 * Sender errors roll back reservations and business writes together.
 *
 * @category Outbound Port
 */
interface WebhookDeliverySchedulePort
{
  // #region Methods
  /**
   * Method enqueue
   *
   * @access public
   *
   * @param string $id delivery identifier
   * @param int $generation exact generation to wake
   * @param DateTimeImmutable $dueAt earliest eligible delivery time
   *
   * @return bool whether this call reserved and enqueued the wake
   */
  public function enqueue(string $id, int $generation, DateTimeImmutable $dueAt): bool;
  // #endregion
}
