<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event DomainEvent
 *
 * Event for domain events.
 *
 * @category Domain Event
 * @package Shared\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface DomainEvent
{
  //#region Methods
  /**
   * Method eventId
   *
   * Get the event id.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Uuid The event id.
   */
  public function eventId(): Uuid;

  /**
   * Method occurredAt
   *
   * Get the occurred at.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The occurred at.
   */
  public function occurredAt(): DateTimeImmutable;

  /**
   * Method aggregateId
   *
   * Returns the identifier of the aggregate
   * that produced the event.
   *
   * @access public
   *
   * @return string The aggregate identifier.
   */
  public function aggregateId(): string;

  /**
   * Method aggregateType
   *
   * Returns the aggregate type emitting the event.
   *
   * @access public
   *
   * @return string The aggregate type.
   */
  public function aggregateType(): string;

  /**
   * Method payload
   *
   * Returns the event payload as an array.
   *
   * @access public
   *
   * @return array<string, mixed> The event payload.
   */
  public function payload(): array;
  //#endregion
}

