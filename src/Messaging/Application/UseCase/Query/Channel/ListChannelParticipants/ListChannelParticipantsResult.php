<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\ListChannelParticipants;

use Messaging\Application\Contract\Channel\ParticipantView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListChannelParticipantsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelParticipantsResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<ParticipantView> $participants the channel's participants
   */
  public function __construct(
    public array $participants,
  ) {
  }
}
