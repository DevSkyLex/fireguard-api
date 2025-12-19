<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Interface EventDispatcherPort.
 *
 * Port for dispatching domain events.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EventDispatcherPort
{
    /**
     * Method dispatch.
     *
     * Dispatches a domain event.
     *
     * @since 1.0.0
     *
     * @param object $event the event to dispatch
     */
    public function dispatch(object $event): void;

    /**
     * Method dispatchAll.
     *
     * Dispatches multiple domain events.
     *
     * @since 1.0.0
     *
     * @param list<object> $events the events to dispatch
     */
    public function dispatchAll(array $events): void;
}
