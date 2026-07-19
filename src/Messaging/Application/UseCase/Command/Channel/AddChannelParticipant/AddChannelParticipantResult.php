<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\AddChannelParticipant;

use Messaging\Application\Contract\Channel\ParticipantView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddChannelParticipantResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddChannelParticipantResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ParticipantView $participant the added participant view
   */
  public function __construct(
    public ParticipantView $participant,
  ) {
  }
}
