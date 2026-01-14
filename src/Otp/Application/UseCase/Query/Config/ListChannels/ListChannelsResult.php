<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Config\ListChannels;

use Shared\Application\Message\ResultMessage;

/**
 * Result ListChannelsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param list<ChannelResult> $items the channel items
   */
  public function __construct(
    public array $items,
  ) {
  }
  // #endregion
}
