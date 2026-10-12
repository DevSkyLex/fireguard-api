<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use DateTimeImmutable;

/**
 * Interface IdempotencyRetentionPort
 *
 * Erases elapsed replay payloads while retaining durable deduplication tombstones.
 *
 * @category Outbound Port
 */
interface IdempotencyRetentionPort
{
  // #region Methods
  /**
   * Method eraseExpiredResults
   *
   * @access public
   *
   * @param DateTimeImmutable $now replay expiry cutoff
   *
   * @return int erased payload count
   */
  public function eraseExpiredResults(DateTimeImmutable $now): int;
  // #endregion
}
