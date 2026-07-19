<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\ListChannels;

use Messaging\Application\Contract\Channel\ChannelPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListChannelsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelsResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ChannelPage $page the channel page
   * @param list<string> $favoriteChannelIds the subset of this page's channel
   *                                         ids favorited by the acting member (L1.5)
   */
  public function __construct(
    public ChannelPage $page,
    public array $favoriteChannelIds = [],
  ) {
  }
}
