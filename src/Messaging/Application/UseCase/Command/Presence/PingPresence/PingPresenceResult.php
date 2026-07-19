<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Presence\PingPresence;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase PingPresenceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PingPresenceResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $memberId the acting member's resolved identifier
   * @param DateTimeImmutable $lastSeenAt the timestamp written to the presence cache entry
   */
  public function __construct(
    public string $memberId,
    public DateTimeImmutable $lastSeenAt,
  ) {
  }
}
