<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Presence\GetPresence;

use Messaging\Application\Contract\Presence\MemberPresenceView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetPresenceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresenceResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<MemberPresenceView> $presences one view per requested member id, in the SAME order as requested
   */
  public function __construct(
    public array $presences,
  ) {
  }
}
