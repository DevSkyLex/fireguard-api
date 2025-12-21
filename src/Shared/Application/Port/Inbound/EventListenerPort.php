<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use Shared\Application\Message\ResultMessage;

/**
 * Port EventListenerPort.
 *
 * Port used to send events
 * to the application.
 *
 * @category Inbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EventListenerPort
{
  // #region Methods
  /**
   * Method handle.
   *
   * Handle an incoming event.
   *
   * @since 1.0.0
   *
   * @param object $event the event to handle
   *
   * @return ?ResultMessage the result of the event
   */
  public function handle(object $event): ?ResultMessage;
  // #endregion
}
