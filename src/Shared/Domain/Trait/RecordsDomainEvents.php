<?php

declare(strict_types=1);

namespace Shared\Domain\Trait;

use Shared\Domain\Event\DomainEvent;

/**
 * Trait RecordsDomainEvents.
 *
 * Provides the ability to record and release domain events
 * from aggregate roots.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait RecordsDomainEvents
{
    // #region Properties
    /**
     * Property domainEvents.
     *
     * The recorded domain events.
     *
     * @since 1.0.0
     *
     * @var array<DomainEvent>
     */
    private array $domainEvents = [];
    // #endregion

    // #region Methods
    /**
     * Method recordEvent.
     *
     * Records a domain event.
     *
     * @since 1.0.0
     *
     * @param DomainEvent $event the event to record
     *
     * @return void no return value
     */
    protected function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Method releaseEvents.
     *
     * Returns all recorded events and clears the internal list.
     *
     * @since 1.0.0
     *
     * @return array<DomainEvent> the recorded events
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Method hasRecordedEvents.
     *
     * Checks if there are any recorded events.
     *
     * @since 1.0.0
     *
     * @return bool true if there are recorded events, false otherwise
     */
    public function hasRecordedEvents(): bool
    {
        return !empty($this->domainEvents);
    }

    /**
     * Method clearRecordedEvents.
     *
     * Clears all recorded events without returning them.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    public function clearRecordedEvents(): void
    {
        $this->domainEvents = [];
    }
    // #endregion
}
