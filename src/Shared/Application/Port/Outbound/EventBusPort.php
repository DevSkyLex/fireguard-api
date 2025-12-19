<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Domain\Event\DomainEvent;

/**
 * Port EventBusPort.
 *
 * Port used to send events
 * to the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EventBusPort
{
    // #region Methods
    /**
     * Method publish.
     *
     * Publish events to the application.
     *
     * @since 1.0.0
     *
     * @param DomainEvent ...$events The events to publish.
     *
     * @return void no return value
     */
    public function publish(DomainEvent ...$events): void;
    // #endregion
}
