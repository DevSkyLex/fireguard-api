<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Config\ListChannels;

use Otp\Domain\ValueObject\OtpChannel;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ListChannelsHandler.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelsHandler implements QueryHandler
{
  // #region Methods
  /**
   * Method __invoke.
   *
   * Lists each OTP channel with its label and delivery requirement.
   *
   * @access public
   *
   * @param ListChannelsQuery $query the channel list query
   *
   * @return ListChannelsResult the available channel entries
   */
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
