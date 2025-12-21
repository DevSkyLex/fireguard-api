<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use DateTimeImmutable;

/**
 * Port ClockPort.
 *
 * Port used to get the current
 * time in the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClockPort
{
  // #region Methods
  /**
   * Method now.
   *
   * Get the current time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the current time
   */
  public function now(): DateTimeImmutable;
  // #endregion
}
