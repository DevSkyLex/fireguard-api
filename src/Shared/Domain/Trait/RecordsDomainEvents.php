<?php

declare(strict_types=1);

namespace Shared\Domain\Trait;

use Shared\Domain\Event\DomainEvent;

/**
 * Trait RecordsDomainEvents
 *
 * Provides the ability to record and release domain events
 * from aggregate roots.
 *
 * @category Trait
 * @package Shared\Domain\Trait
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait RecordsDomainEvents
{
  //#region Properties
  /**
   * Property domainEvents
   *
   * The recorded domain events.
   *
   * @access private
   * @since 1.0.0
   *
   * @var array<DomainEvent> $domainEvents
   */
  private array $domainEvents = [];
  //#endregion

  //#region Methods
  /**
   * Method recordEvent
   *
   * Records a domain event.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param DomainEvent $event The event to record.
   *
   * @return void No return value.
   */
  protected function recordEvent(DomainEvent $event): void
  {
    $this->domainEvents[] = $event;
  }

  /**
   * Method releaseEvents
   *
   * Returns all recorded events and clears the internal list.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<DomainEvent> The recorded events.
   */
  public function releaseEvents(): array
  {
    $events = $this->domainEvents;
    $this->domainEvents = [];
    return $events;
  }

  /**
   * Method hasRecordedEvents
   *
   * Checks if there are any recorded events.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if there are recorded events, false otherwise.
   */
  public function hasRecordedEvents(): bool
  {
    return !empty($this->domainEvents);
  }

  /**
   * Method clearRecordedEvents
   *
   * Clears all recorded events without returning them.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function clearRecordedEvents(): void
  {
    $this->domainEvents = [];
  }
  //#endregion
}
