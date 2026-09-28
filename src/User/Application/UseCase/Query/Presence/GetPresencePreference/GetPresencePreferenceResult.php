<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Presence\GetPresencePreference;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetPresencePreferenceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresencePreferenceResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public bool $doNotDisturb, public int $revision, public bool $invisible = false)
  {
  }
}
