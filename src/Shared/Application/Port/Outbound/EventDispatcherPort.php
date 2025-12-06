<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Interface EventDispatcherPort
 *
 * Port for dispatching domain events.
 *
 * @category Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EventDispatcherPort
{
  /**
   * Method dispatch
   *
   * Dispatches a domain event.
   *
   * @access public
   * @since 1.0.0
   *
   * @param object $event The event to dispatch.
   *
   * @return void
   */
  public function dispatch(object $event): void;

  /**
   * Method dispatchAll
   *
   * Dispatches multiple domain events.
   *
   * @access public
   * @since 1.0.0
   *
   * @param list<object> $events The events to dispatch.
   *
   * @return void
   */
  public function dispatchAll(array $events): void;
}
