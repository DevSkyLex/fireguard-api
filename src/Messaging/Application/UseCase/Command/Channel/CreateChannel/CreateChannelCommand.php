<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\CreateChannel;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateChannelCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateChannelCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param string $name the channel display name
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $name,
  ) {
  }
}
