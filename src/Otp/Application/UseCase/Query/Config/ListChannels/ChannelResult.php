<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Config\ListChannels;

/**
 * ChannelResult.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChannelResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $value the channel identifier
   * @param string $label the human-readable label
   * @param bool $requiresDelivery whether the channel requires active delivery
   */
  public function __construct(
    public string $value,
    public string $label,
    public bool $requiresDelivery,
  ) {
  }
  // #endregion
}
