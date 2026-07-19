<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\UpdateChannel;

use Messaging\Application\Contract\Channel\ChannelView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateChannelResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateChannelResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ChannelView $channel the updated channel view
   */
  public function __construct(
    public ChannelView $channel,
  ) {
  }
}
