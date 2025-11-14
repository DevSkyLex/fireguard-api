<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use DateTimeImmutable;

/**
 * Port ClockPort
 *
 * Port used to get the current
 * time in the application.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClockPort
{
  //#region Methods
  /**
   * Method now
   * @method now(): DateTimeImmutable
   *
   * Get the current time.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The current time.
   */
  public function now(): DateTimeImmutable;
  //#endregion
}
