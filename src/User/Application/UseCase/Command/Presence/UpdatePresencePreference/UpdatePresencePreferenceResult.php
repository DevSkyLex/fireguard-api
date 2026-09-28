<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\Presence\UpdatePresencePreference;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdatePresencePreferenceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePresencePreferenceResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public bool $doNotDisturb, public int $revision, public bool $invisible = false)
  {
  }
}
