<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Domain\Event\DomainEvent;

/**
 * Port EventBusPort
 *
 * Port used to send events
 * to the application.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EventBusPort
{
  //#region Methods
  /**
   * Method publish
   *
   * Publish events to the application.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DomainEvent ...$events The events to publish.
   *
   * @return void No return value.
   */
  public function publish(DomainEvent ...$events): void;
  //#endregion
}
