<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\GetChannel;

use Messaging\Application\Contract\Channel\ChannelView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetChannelResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChannelResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ChannelView $channel the channel view
   * @param int $unreadCount the acting member's unread count
   * @param bool $isFavorite whether the acting member favorited this channel (L1.5)
   */
  public function __construct(
    public ChannelView $channel,
    public int $unreadCount,
    public bool $isFavorite = false,
  ) {
  }
}
