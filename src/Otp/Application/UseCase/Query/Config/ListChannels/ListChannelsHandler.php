<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Config\ListChannels;

use Otp\Domain\ValueObject\OtpChannel;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ListChannelsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelsHandler implements QueryHandler
{
  // #region Methods
  public function __invoke(ListChannelsQuery $query): ListChannelsResult
  {
    $items = [];

    foreach (OtpChannel::cases() as $channel) {
      $items[] = new ChannelResult(
        value: $channel->value,
        label: $channel->getLabel(),
        requiresDelivery: $channel->requiresDelivery(),
      );
    }

    return new ListChannelsResult(items: $items);
  }
  // #endregion
}
