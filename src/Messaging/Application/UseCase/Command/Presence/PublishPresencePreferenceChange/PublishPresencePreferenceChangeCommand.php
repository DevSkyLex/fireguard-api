<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Presence\PublishPresencePreferenceChange;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PublishPresencePreferenceChangeCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PublishPresencePreferenceChangeCommand implements CommandMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId)
  {
  }
}
