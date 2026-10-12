<?php

declare(strict_types=1);

namespace User\Application\Contract\Event;

/**
 * Requests independent receipt erasure after permanent account deletion commits.
 *
 * @category Event
 */
final readonly class UserReceiptPurgeRequestedEvent
{
  /**
   * @param string $userId permanently removed account identity
   */
  public function __construct(public string $userId)
  {
  }
}
