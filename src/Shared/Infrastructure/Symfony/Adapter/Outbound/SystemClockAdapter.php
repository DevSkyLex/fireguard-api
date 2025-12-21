<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use DateTimeImmutable;
use Shared\Application\Port\Outbound\ClockPort;

/**
 * Adapter SystemClock.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SystemClockAdapter implements ClockPort
{
  // #region Methods
  /**
   * Method now
   * {@inheritDoc}
   *
   * Returns the current date and time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the current date and time
   */
  public function now(): DateTimeImmutable
  {
    return new DateTimeImmutable(datetime: 'now');
  }
  // #endregion
}
