<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use Shared\Application\Message\ResultMessage;

/**
 * Port EventListenerPort
 *
 * Port used to send events
 * to the application.
 *
 * @category Inbound Port
 * @package Shared\Application\Port\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EventListenerPort
{
  //#region Methods
  /**
   * Method handle
   *
   * Handle an incoming event.
   *
   * @access public
   * @since 1.0.0
   *
   * @param object $event The event to handle.
   *
   * @return ?ResultMessage The result of the event.
   */
  public function handle(object $event): ?ResultMessage;
  //#endregion
}
