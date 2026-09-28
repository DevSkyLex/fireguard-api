<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\Presence\UpdatePresencePreference;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdatePresencePreferenceCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePresencePreferenceCommand implements CommandMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId, public ?bool $doNotDisturb, public ?bool $invisible = null)
  {
  }
}
