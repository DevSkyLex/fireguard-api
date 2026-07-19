<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Presence\PingPresence;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PingPresenceCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PingPresenceCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
  ) {
  }
}
